<?php

namespace Modules\SystemMonitor\Providers;

use App\Conversation;
use App\SendLog;
use App\Thread;
use Illuminate\Support\ServiceProvider;
use Modules\SystemMonitor\Models\CronLog;
use Modules\SystemMonitor\Models\EmailViewLog;

class SystemMonitorServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/../Http/routes.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'systemmonitor');

        $this->registerCronTracking();
        $this->registerEmailViewTracking();
        $this->registerDashboardPanel();
        $this->registerDashboardJs();
        $this->registerCronLogCleanup();
        $this->registerThreadSmtpStatus();
        $this->registerLoginEnhancement();
    }

    public function register()
    {
        //
    }

    /**
     * Wrap scheduled events with before/after callbacks to log cron runs.
     */
    protected function registerCronTracking()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            foreach ($schedule->events() as $event) {
                $rawCommand = $event->command ?? $event->description ?? 'unknown';
                $commandName = $this->extractCommandName($rawCommand);

                $event->before(function () use ($commandName) {
                    try {
                        $logId = CronLog::logStart($commandName);
                        \Cache::put('cron_log_' . md5($commandName), [
                            'id'    => $logId,
                            'start' => microtime(true),
                        ], 60);
                    } catch (\Exception $e) {
                        // Silently fail to avoid breaking cron
                    }
                });

                $event->after(function () use ($commandName) {
                    try {
                        $data = \Cache::get('cron_log_' . md5($commandName));
                        if ($data) {
                            $durationMs = round((microtime(true) - $data['start']) * 1000);
                            CronLog::logFinish($data['id'], 'completed', null, $durationMs);
                            \Cache::forget('cron_log_' . md5($commandName));
                        }
                    } catch (\Exception $e) {
                        // Silently fail
                    }
                });
            }

            return $schedule;
        }, 30, 1);
    }

    /**
     * Extract the artisan command name from a full command string.
     */
    protected function extractCommandName($raw)
    {
        // e.g. "/opt/alt/php82/usr/bin/php" "artisan" freescout:fetch-emails --identifier=...
        if (preg_match('/artisan[\'"\s]+(\S+)/', $raw, $m)) {
            return $m[1];
        }
        // Callback-based events
        if (strpos($raw, 'Callback') !== false || strpos($raw, 'Closure') !== false) {
            return 'callback';
        }
        // Last resort: return last segment
        $parts = preg_split('/\s+/', trim($raw));
        return end($parts) ?: $raw;
    }

    /**
     * Track conversation views via existing hooks.
     */
    protected function registerEmailViewTracking()
    {
        \Eventy::addAction('conversation.view.start', function ($conversation, $request) {
            $user = auth()->user();
            if (!$user) {
                return;
            }
            try {
                EmailViewLog::logView($user->id, $conversation->id);
            } catch (\Exception $e) {
                // Silently fail
            }
        }, 20, 2);

        \Eventy::addAction('conversation.view.finish', function ($conversationId, $userId, $durationSeconds) {
            try {
                EmailViewLog::logFinish($conversationId, $userId, $durationSeconds);
            } catch (\Exception $e) {
                // Silently fail
            }
        }, 20, 3);
    }

    /**
     * Inject dashboard panels via the dashboard.after filter.
     */
    protected function registerDashboardPanel()
    {
        \Eventy::addFilter('dashboard.after', function ($html) {
            $user = auth()->user();
            if (!$user || !$user->isAdmin()) {
                return $html;
            }

            try {
                $data = $this->getDashboardData();
                $panelHtml = view('systemmonitor::dashboard_panel', $data)->render();
                return $html . $panelHtml;
            } catch (\Exception $e) {
                return $html . '<div class="alert alert-danger margin-top">SystemMonitor error: ' . e($e->getMessage()) . '</div>';
            }
        }, 20, 1);
    }

    /**
     * Register expand-button JS via the javascript hook (CSP-safe with nonce).
     */
    protected function registerDashboardJs()
    {
        \Eventy::addAction('javascript', function () {
            ?>
            if (!window._smBound) {
                window._smBound = true;
                $(document).on('click', '.sm-expand-btn', function(e) {
                    // Don't toggle if clicking a link inside the row
                    if ($(e.target).closest('a').length) return;
                    e.preventDefault();
                    var $el = $(this);
                    var $target = $($el.data('target'));
                    if ($target.length) {
                        $target.slideToggle(150);
                        $el.find('.glyphicon-chevron-down, .glyphicon-chevron-up').toggleClass('glyphicon-chevron-down glyphicon-chevron-up');
                    }
                });
            }
            <?php
        });
    }

    /**
     * Cleanup old logs daily.
     */
    protected function registerCronLogCleanup()
    {
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->call(function () {
                try {
                    CronLog::where('started_at', '<', now()->subDays(30))->delete();
                    EmailViewLog::where('viewed_at', '<', now()->subDays(30))->delete();
                } catch (\Exception $e) {
                    // Silently fail
                }
            })->daily();

            return $schedule;
        }, 31, 1);
    }

    /**
     * Enhance login activity logs with user_agent, session, headers, device info.
     */
    protected function registerLoginEnhancement()
    {
        // Listen to Laravel auth events and update the most recent activity log
        \Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $this->enhanceLatestActivityLog();
        });

        \Event::listen(\Illuminate\Auth\Events\Failed::class, function ($event) {
            $this->enhanceLatestActivityLog();
        });
    }

    protected function enhanceLatestActivityLog()
    {
        try {
            $request = request();
            if (!$request) return;

            // Get the most recent activity log entry (just created by FreeScout core)
            $latest = \App\ActivityLog::where('log_name', 'users')
                ->orderBy('id', 'desc')
                ->first();

            if (!$latest || $latest->created_at->diffInSeconds(now()) > 5) return;

            $props = $latest->properties ? (is_array($latest->properties) ? $latest->properties : $latest->properties->toArray()) : [];
            $props['user_agent'] = $request->header('User-Agent', '');
            $props['session_id'] = substr(session()->getId() ?: '', 0, 16);
            $props['accept_language'] = $request->header('Accept-Language', '');
            $props['referer'] = $request->header('Referer', '');

            $latest->properties = $props;
            $latest->save();
        } catch (\Exception $e) {
            // Silently fail
        }
    }

    /**
     * Show SMTP delivery status on outgoing thread messages.
     */
    protected function registerThreadSmtpStatus()
    {
        \Eventy::addAction('thread.meta', function ($thread, $loop, $threads, $conversation, $mailbox) {
            $user = auth()->user();
            if (!$user || !$user->isAdmin()) {
                return;
            }

            // Only on outgoing messages (user replies to customer)
            if ($thread->type != Thread::TYPE_MESSAGE) {
                return;
            }

            try {
                $logs = SendLog::where('thread_id', $thread->id)
                    ->orderBy('created_at', 'desc')
                    ->get();

                // Separate actual customer replies (mail_type=1) from agent notifications (mail_type=2)
                $customerLogs = $logs->filter(function ($l) { return $l->mail_type == 1; });
                $notifyLogs = $logs->filter(function ($l) { return $l->mail_type != 1; });

                if ($customerLogs->isEmpty() && $notifyLogs->isEmpty()) {
                    echo '<div style="margin-top:8px;padding:6px 10px;background:#f5f5f5;border-radius:3px;font-size:11px;color:#999;">'
                        . '<i class="glyphicon glyphicon-question-sign"></i> No SMTP log for this message'
                        . '</div>';
                    return;
                }

                $threadId = $thread->id;

                // Show summary line — always use the customer delivery, not agent notifications
                $latestLog = $customerLogs->first() ?: $logs->first();
                if ($latestLog->isSuccessStatus()) {
                    $badgeClass = 'label-success';
                    $icon = 'ok-sign';
                } elseif ($latestLog->isErrorStatus()) {
                    $badgeClass = 'label-danger';
                    $icon = 'exclamation-sign';
                } else {
                    $badgeClass = 'label-warning';
                    $icon = 'time';
                }

                echo '<div style="margin-top:8px;padding:8px 12px;background:#f9f9f9;border:1px solid #eee;border-radius:3px;font-size:12px;">';
                echo '<div class="sm-expand-btn" data-target="#smtp-detail-' . $threadId . '" style="cursor:pointer;">';
                echo '<i class="glyphicon glyphicon-' . $icon . '" style="margin-right:4px;"></i>';
                echo '<span class="label ' . $badgeClass . '" style="font-size:10px;">' . e($latestLog->getStatusName()) . '</span> ';
                echo '<span style="color:#666;">to <strong>' . e($latestLog->email) . '</strong></span> ';
                echo '<span style="color:#999;">— ' . $latestLog->created_at->format('M d H:i:s') . '</span> ';
                if ($latestLog->smtp_queue_id) {
                    echo '<span style="color:#aaa;"> — Queue: ' . e($latestLog->smtp_queue_id) . '</span>';
                }
                echo ' <i class="glyphicon glyphicon-chevron-down" style="font-size:9px;color:#ccc;margin-left:4px;"></i>';
                echo '</div>';

                // Expandable detail table
                echo '<div id="smtp-detail-' . $threadId . '" style="display:none;margin-top:8px;">';
                echo '<table style="width:100%;font-size:11px;border-collapse:collapse;">';
                echo '<tr style="background:#f0f0f0;"><th style="padding:4px 6px;text-align:left;">To</th>'
                    . '<th style="padding:4px 6px;text-align:left;">Type</th>'
                    . '<th style="padding:4px 6px;text-align:left;">Status</th>'
                    . '<th style="padding:4px 6px;text-align:left;">Time</th>'
                    . '<th style="padding:4px 6px;text-align:left;">Queue ID</th>'
                    . '<th style="padding:4px 6px;text-align:left;">Details</th></tr>';

                // Show customer deliveries first
                foreach ($customerLogs as $log) {
                    if ($log->isSuccessStatus()) {
                        $rowBadge = 'label-success';
                    } elseif ($log->isErrorStatus()) {
                        $rowBadge = 'label-danger';
                    } else {
                        $rowBadge = 'label-default';
                    }

                    echo '<tr style="border-top:1px solid #eee;">';
                    echo '<td style="padding:4px 6px;">' . e($log->email) . '</td>';
                    echo '<td style="padding:4px 6px;"><span style="color:#337ab7;font-weight:600;">Customer Reply</span></td>';
                    echo '<td style="padding:4px 6px;"><span class="label ' . $rowBadge . '" style="font-size:10px;">' . e($log->getStatusName()) . '</span></td>';
                    echo '<td style="padding:4px 6px;white-space:nowrap;">' . $log->created_at->format('M d H:i:s') . '</td>';
                    echo '<td style="padding:4px 6px;">' . e($log->smtp_queue_id ?? '—') . '</td>';
                    echo '<td style="padding:4px 6px;color:#999;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                        . e($log->status_message ?? '—') . '</td>';
                    echo '</tr>';
                }

                // Then show agent notifications (dimmed)
                if ($notifyLogs->count()) {
                    echo '<tr style="border-top:2px solid #ddd;"><td colspan="6" style="padding:4px 6px;font-size:10px;color:#999;font-weight:600;">Agent Notifications (internal)</td></tr>';
                    foreach ($notifyLogs as $log) {
                        if ($log->isSuccessStatus()) {
                            $rowBadge = 'label-success';
                        } elseif ($log->isErrorStatus()) {
                            $rowBadge = 'label-danger';
                        } else {
                            $rowBadge = 'label-default';
                        }

                        $agentName = '';
                        if ($log->user_id) {
                            $agent = \App\User::find($log->user_id);
                            $agentName = $agent ? $agent->getFullName() : 'User #' . $log->user_id;
                        }

                        echo '<tr style="border-top:1px solid #eee;opacity:0.6;">';
                        echo '<td style="padding:4px 6px;">' . e($log->email) . ($agentName ? ' <span style="color:#999;">(' . e($agentName) . ')</span>' : '') . '</td>';
                        echo '<td style="padding:4px 6px;"><span style="color:#999;">Notification</span></td>';
                        echo '<td style="padding:4px 6px;"><span class="label ' . $rowBadge . '" style="font-size:10px;">' . e($log->getStatusName()) . '</span></td>';
                        echo '<td style="padding:4px 6px;white-space:nowrap;">' . $log->created_at->format('M d H:i:s') . '</td>';
                        echo '<td style="padding:4px 6px;">' . e($log->smtp_queue_id ?? '—') . '</td>';
                        echo '<td style="padding:4px 6px;color:#999;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">'
                            . e($log->status_message ?? '—') . '</td>';
                        echo '</tr>';
                    }
                }

                echo '</table></div></div>';
            } catch (\Exception $e) {
                // Silently fail
            }
        }, 20, 5);
    }

    /**
     * Gather all data for the dashboard panels.
     */
    protected function getDashboardData()
    {
        $since = now()->subHours(48);
        $events = collect();

        // ── 1. Threads: customer replies, agent replies, notes, status/assignment changes ──
        $threads = Thread::where('created_at', '>=', $since)
            ->where('state', Thread::STATE_PUBLISHED)
            ->whereIn('type', [
                Thread::TYPE_CUSTOMER,
                Thread::TYPE_MESSAGE,
                Thread::TYPE_NOTE,
                Thread::TYPE_LINEITEM,
            ])
            ->orderBy('created_at', 'desc')
            ->limit(150)
            ->get();

        // Preload conversations, users, customers
        $convIds = $threads->pluck('conversation_id')->unique()->filter()->toArray();
        $convMap = $convIds ? Conversation::whereIn('id', $convIds)->get()->keyBy('id') : collect();
        $userIds = $threads->pluck('created_by_user_id')->merge($threads->pluck('user_id'))->unique()->filter()->toArray();
        $userMap = $userIds ? \App\User::whereIn('id', $userIds)->get()->keyBy('id') : collect();
        $custIds = $threads->pluck('created_by_customer_id')->merge($threads->pluck('customer_id'))->unique()->filter()->toArray();
        $custMap = $custIds ? \App\Customer::whereIn('id', $custIds)->get()->keyBy('id') : collect();

        // Preload email chains: last 5 threads per conversation for expandable view
        $chainMap = collect();
        if ($convIds) {
            foreach ($convIds as $cid) {
                $chain = Thread::where('conversation_id', $cid)
                    ->where('state', Thread::STATE_PUBLISHED)
                    ->whereIn('type', [Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE, Thread::TYPE_NOTE])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get();
                $chainMap->put($cid, $chain);
            }
        }

        // Track which thread IDs we've already seen to avoid duplicates
        $seenThreadIds = [];

        foreach ($threads as $t) {
            $conv = $convMap->get($t->conversation_id);
            $event = [
                'time' => $t->created_at,
                'conv' => $conv,
                'thread' => $t,
                'chain' => $chainMap->get($t->conversation_id, collect()),
            ];

            // Detect API-created conversations/threads (source_type = 3)
            $isApiCreated = false;
            if ($conv && $conv->source_type == 3 && $t->first) {
                $isApiCreated = true;
            } elseif ($t->source_type == 3) {
                $isApiCreated = true;
            }

            switch ($t->type) {
                case Thread::TYPE_CUSTOMER:
                    $cust = $custMap->get($t->created_by_customer_id) ?: $custMap->get($t->customer_id);
                    if ($isApiCreated) {
                        $event['type'] = 'api_created';
                        $event['icon'] = 'glyphicon-cloud';
                        $event['color'] = '#9b59b6';
                        $event['actor'] = $cust ? $cust->getFullName(true) : ($t->from ?: 'Customer');
                        $event['actor_email'] = $cust ? $cust->getMainEmail() : $t->from;
                        $event['label'] = 'API Created Ticket';
                        $event['has_body'] = true;
                    } else {
                        $event['type'] = 'customer_reply';
                        $event['icon'] = 'glyphicon-envelope';
                        $event['color'] = '#337ab7';
                        $event['actor'] = $cust ? $cust->getFullName(true) : ($t->from ?: 'Customer');
                        $event['actor_email'] = $cust ? $cust->getMainEmail() : $t->from;
                        $event['label'] = 'Customer Reply';
                        $event['has_body'] = true;
                    }
                    break;

                case Thread::TYPE_MESSAGE:
                    $user = $userMap->get($t->created_by_user_id);
                    $event['type'] = 'agent_reply';
                    $event['icon'] = 'glyphicon-share-alt';
                    $event['color'] = '#5cb85c';
                    $event['actor'] = $user ? $user->getFullName() : 'Agent';
                    $event['actor_email'] = $user ? $user->email : '';
                    $event['label'] = 'Agent Reply';
                    $event['has_body'] = true;
                    break;

                case Thread::TYPE_NOTE:
                    $user = $userMap->get($t->created_by_user_id);
                    $event['type'] = 'note';
                    $event['icon'] = 'glyphicon-edit';
                    $event['color'] = '#f0ad4e';
                    $event['actor'] = $user ? $user->getFullName() : 'Agent';
                    $event['label'] = 'Note Added';
                    $event['has_body'] = true;
                    break;

                case Thread::TYPE_LINEITEM:
                    $user = $userMap->get($t->created_by_user_id);
                    $event['has_body'] = false;
                    $event['color'] = '#999';
                    $event['actor'] = $user ? $user->getFullName() : 'System';

                    if ($t->action_type == Thread::ACTION_TYPE_STATUS_CHANGED) {
                        $event['type'] = 'status_changed';
                        $event['icon'] = 'glyphicon-random';
                        $event['label'] = 'Status → ' . Conversation::statusCodeToName((int) $t->action_data);
                    } elseif ($t->action_type == Thread::ACTION_TYPE_USER_CHANGED) {
                        $event['type'] = 'assigned';
                        $event['icon'] = 'glyphicon-user';
                        $assignee = $t->action_data ? $userMap->get((int) $t->action_data) : null;
                        $event['label'] = 'Assigned → ' . ($assignee ? $assignee->getFullName() : 'Unassigned');
                    } elseif ($t->action_type == Thread::ACTION_TYPE_MOVED_FROM_MAILBOX) {
                        $event['type'] = 'moved';
                        $event['icon'] = 'glyphicon-log-out';
                        $event['label'] = 'Moved';
                    } elseif ($t->action_type == Thread::ACTION_TYPE_MERGED) {
                        $event['type'] = 'merged';
                        $event['icon'] = 'glyphicon-indent-left';
                        $event['label'] = 'Merged';
                    } elseif ($t->action_type == Thread::ACTION_TYPE_DELETED_TICKET) {
                        $event['type'] = 'deleted';
                        $event['icon'] = 'glyphicon-trash';
                        $event['color'] = '#d9534f';
                        $event['label'] = 'Deleted';
                    } elseif ($t->action_type == Thread::ACTION_TYPE_RESTORE_TICKET) {
                        $event['type'] = 'restored';
                        $event['icon'] = 'glyphicon-repeat';
                        $event['label'] = 'Restored';
                    } else {
                        $event['type'] = 'lineitem';
                        $event['icon'] = 'glyphicon-info-sign';
                        $event['label'] = 'Updated';
                    }
                    break;

                default:
                    continue 2;
            }

            // For API-created events, look up the corresponding api_logs entry
            if ($isApiCreated) {
                try {
                    $apiLogEntry = null;
                    // Find the API log that created this conversation (POST to conversations endpoint within ±30 seconds)
                    if (class_exists('\\Modules\\ApiWebhooks\\Models\\ApiLog')) {
                        $apiLogEntry = \Modules\ApiWebhooks\Models\ApiLog::where('method', 'POST')
                            ->where('endpoint', 'LIKE', '%conversations%')
                            ->where('status_code', '>=', 200)
                            ->where('status_code', '<', 300)
                            ->where('created_at', '>=', $t->created_at->copy()->subSeconds(30))
                            ->where('created_at', '<=', $t->created_at->copy()->addSeconds(30))
                            ->orderBy('created_at', 'desc')
                            ->first();
                    }

                    if ($apiLogEntry) {
                        $apiKeyModel = $apiLogEntry->apiKey;
                        $event['api_details'] = [
                            'api_key_name' => $apiKeyModel ? $apiKeyModel->name : 'Unknown Key',
                            'ip' => $apiLogEntry->ip,
                            'country' => $apiLogEntry->country,
                            'city' => $apiLogEntry->city,
                            'user_agent' => $apiLogEntry->user_agent,
                            'device' => $apiLogEntry->device_summary,
                            'response_time_ms' => $apiLogEntry->response_time_ms,
                            'endpoint' => $apiLogEntry->endpoint,
                            'request_body' => $apiLogEntry->request_body,
                        ];
                    }
                } catch (\Exception $e) {
                    // ApiWebhooks module may not be installed
                }
            }

            $seenThreadIds[$t->id] = true;
            $events->push($event);
        }

        // ── 2. Send logs — attach to existing thread events as delivery_logs, only keep orphans ──
        $sendLogs = SendLog::where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        // Group send logs by thread_id
        $slByThread = $sendLogs->groupBy('thread_id');

        // Attach delivery logs to existing thread events
        $eventsArray = $events->all();
        foreach ($eventsArray as $idx => &$event) {
            $t = $event['thread'] ?? null;
            if ($t && isset($slByThread[$t->id])) {
                $event['delivery_logs'] = $slByThread[$t->id];
                $slByThread->forget($t->id);
            }
        }
        unset($event);
        $events = collect($eventsArray);

        // Any remaining send logs without a matching thread event become standalone entries
        foreach ($slByThread as $threadId => $logs) {
            if (!$threadId) continue;
            foreach ($logs as $sl) {
                // Skip internal agent notifications — they clutter the feed
                if ($sl->mail_type != 1) continue;

                $slThread = Thread::find($sl->thread_id);
                $conv = $slThread ? $convMap->get($slThread->conversation_id) : null;
                if (!$conv && $slThread) {
                    $conv = Conversation::find($slThread->conversation_id);
                    if ($conv) $convMap->put($conv->id, $conv);
                }

                $event = [
                    'time' => $sl->created_at,
                    'conv' => $conv,
                    'thread' => $slThread,
                    'chain' => $conv ? ($chainMap->get($conv->id) ?: collect()) : collect(),
                    'type' => 'email_delivery',
                    'icon' => 'glyphicon-send',
                    'actor' => $sl->email,
                    'has_body' => (bool) $slThread,
                    'delivery_logs' => collect([$sl]),
                ];

                if ($sl->isErrorStatus()) {
                    $event['color'] = '#d9534f';
                    $event['label'] = 'Delivery Failed — ' . $sl->getStatusName();
                } elseif ($sl->isSuccessStatus()) {
                    $event['color'] = '#5cb85c';
                    $event['label'] = 'Email Delivered — ' . $sl->email;
                } else {
                    $event['color'] = '#777';
                    $event['label'] = 'Email ' . $sl->getStatusName() . ' — ' . $sl->email;
                }

                $events->push($event);
            }
        }

        // ── 3. Activity logs (logins, errors, system events) ──
        // Only keep last login per user + failed logins + errors (no login spam)
        $activityLogs = \App\ActivityLog::where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $seenLogins = []; // track user_id => true for dedup
        foreach ($activityLogs as $al) {
            $desc = $al->getEventDescription();
            $isLogin = ($al->log_name === 'users' && stripos($desc, 'Logged in') !== false);
            $isLogout = ($al->log_name === 'users' && stripos($desc, 'Logged out') !== false);

            // Skip duplicate logins — only show the most recent per user
            if ($isLogin || $isLogout) {
                $uid = $al->causer_id ?: 0;
                if (isset($seenLogins[$uid])) continue;
                $seenLogins[$uid] = true;
            }

            $event = [
                'time' => $al->created_at,
                'conv' => null,
                'thread' => null,
                'has_body' => false,
                'actor' => '',
                'chain' => collect(),
            ];

            if ($al->causer_id && $al->causer_type) {
                $causer = $userMap->get($al->causer_id);
                if (!$causer && $al->causer_type === 'App\\User') {
                    $causer = \App\User::find($al->causer_id);
                    if ($causer) $userMap->put($causer->id, $causer);
                }
                $event['actor'] = $causer ? $causer->getFullName() : 'User #' . $al->causer_id;
            }

            // Extract properties for login details
            $props = $al->properties;
            $propsArray = [];
            if ($props) {
                $propsArray = is_array($props) ? $props : (is_object($props) ? json_decode(json_encode($props), true) : []);
            }
            $event['properties'] = $propsArray;

            if ($al->log_name === 'users') {
                $isFailed = ($al->description === 'login_failed' || stripos($desc, 'Failed') !== false);
                $event['type'] = $isFailed ? 'login_failed' : 'user_activity';
                $event['icon'] = $isFailed ? 'glyphicon-ban-circle' : 'glyphicon-log-in';
                $event['color'] = $isFailed ? '#d9534f' : '#5bc0de';
                $event['label'] = $desc;
            } elseif (strpos($al->log_name, 'error') !== false) {
                $event['type'] = 'error';
                $event['icon'] = 'glyphicon-exclamation-sign';
                $event['color'] = '#d9534f';
                $event['label'] = $desc;
                $props = $al->properties;
                if ($props) {
                    $event['error_detail'] = is_array($props) ? json_encode($props, JSON_PRETTY_PRINT) : (string) $props;
                }
            } else {
                $event['type'] = 'system';
                $event['icon'] = 'glyphicon-cog';
                $event['color'] = '#999';
                $event['label'] = $desc ?: $al->description;
            }

            $events->push($event);
        }

        // ── 4. Cron status summary ──
        $cronLogs = CronLog::where('started_at', '>=', now()->subHours(24))
            ->orderBy('started_at', 'desc')
            ->limit(50)
            ->get();

        $cronSummary = [];
        foreach ($cronLogs as $log) {
            if (!isset($cronSummary[$log->command])) {
                $cronSummary[$log->command] = $log;
            }
        }

        // Sort all events by time descending
        $events = $events->sortByDesc('time')->values();

        // ── 5. Recently viewed emails ──
        $recentViews = EmailViewLog::where('viewed_at', '>=', $since)
            ->orderBy('viewed_at', 'desc')
            ->limit(20)
            ->get();
        $rvConvIds = $recentViews->pluck('conversation_id')->unique()->filter()->toArray();
        $rvConvs = $rvConvIds ? Conversation::whereIn('id', $rvConvIds)->get()->keyBy('id') : collect();
        $rvUserIds = $recentViews->pluck('user_id')->unique()->filter()->toArray();
        $rvUsers = $rvUserIds ? \App\User::whereIn('id', $rvUserIds)->get()->keyBy('id') : collect();

        // ── 6. Telegram notification log ──
        $telegramLogs = collect();
        try {
            $telegramLogs = \Modules\TelegramNotifications\Models\TelegramLog::where('created_at', '>=', $since)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();
        } catch (\Exception $e) {
            // Module may not have table yet
        }

        // ── 7. Security status — non-admin users without assigned-only restriction ──
        $securityAlerts = [];
        try {
            $restrictedIds = array_filter(explode(',', config('app.show_only_assigned_conversations') ?? ''));
            $nonAdminUsers = \App\User::where('role', \App\User::ROLE_USER)
                ->whereNull('deleted_at')
                ->get(['id', 'first_name', 'last_name']);
            foreach ($nonAdminUsers as $u) {
                if (!in_array($u->id, $restrictedIds)) {
                    $securityAlerts[] = $u;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return compact('events', 'cronSummary', 'userMap', 'custMap', 'recentViews', 'rvConvs', 'rvUsers', 'telegramLogs', 'securityAlerts');
    }
}
