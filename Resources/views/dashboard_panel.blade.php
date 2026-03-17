<div class="system-monitor-panels margin-top" style="margin-top: 30px;">

    {{-- ═══ SECURITY STATUS (top, always visible) ═══ --}}
    @if(count($securityAlerts) > 0)
    <div style="margin-bottom: 15px; padding: 15px; background: #fdf2f2; border: 2px solid #d9534f; border-radius: 4px;">
        <h4 style="margin: 0 0 10px 0; color: #a94442; font-size: 14px;">
            <i class="glyphicon glyphicon-exclamation-sign"></i> Security Alert
        </h4>
        <p style="font-size: 12px; color: #a94442; margin-bottom: 8px;">
            The following non-admin users can see <strong>ALL conversations</strong> in their mailboxes (not restricted to assigned/watching only):
        </p>
        <div style="display: flex; flex-wrap: wrap; gap: 6px;">
            @foreach($securityAlerts as $alertUser)
                <span style="padding: 3px 10px; background: #d9534f; color: #fff; border-radius: 3px; font-size: 12px; font-weight: 600;">
                    {{ $alertUser->getFullName() }}
                </span>
            @endforeach
        </div>
        <p style="font-size: 11px; color: #999; margin: 8px 0 0 0;">
            Fix: Go to each user's <strong>Permissions</strong> page and enable "User can only see conversations assigned to them or that they watch."
        </p>
    </div>
    @else
    <div style="margin-bottom: 15px; padding: 10px 15px; background: #dff0d8; border: 1px solid #3c763d; border-radius: 4px; font-size: 12px; color: #3c763d;">
        <i class="glyphicon glyphicon-ok-sign"></i> <strong>Security OK</strong> — All non-admin users are restricted to assigned/watched conversations only.
    </div>
    @endif

    {{-- ═══ RECENTLY VIEWED + TELEGRAM side by side ═══ --}}
    <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">

        {{-- Recently Viewed --}}
        <div style="flex: 1; min-width: 300px;">
            <h4 style="color: #666; font-size: 14px; border-bottom: 1px solid #e5e5e5; padding-bottom: 6px; margin-bottom: 10px;">
                <i class="glyphicon glyphicon-eye-open"></i> Recently Viewed
                <small style="color: #aaa; font-size: 11px; margin-left: 4px;">48h</small>
            </h4>
            @if(count($recentViews) > 0)
            <div style="border: 1px solid #ddd; border-radius: 4px; max-height: 250px; overflow-y: auto;">
                <table style="width: 100%; font-size: 11px; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 5px 8px; text-align: left; font-weight: 600; color: #888;">Agent</th>
                            <th style="padding: 5px 8px; text-align: left; font-weight: 600; color: #888;">Ticket</th>
                            <th style="padding: 5px 8px; text-align: left; font-weight: 600; color: #888;">Subject</th>
                            <th style="padding: 5px 8px; text-align: right; font-weight: 600; color: #888;">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentViews as $rv)
                            @php
                                $rvUser = $rvUsers->get($rv->user_id);
                                $rvConv = $rvConvs->get($rv->conversation_id);
                            @endphp
                            <tr style="border-top: 1px solid #eee;">
                                <td style="padding: 4px 8px;">{{ $rvUser ? $rvUser->getFullName() : 'User #'.$rv->user_id }}</td>
                                <td style="padding: 4px 8px;">
                                    @if($rvConv)
                                        <a href="{{ route('conversations.view', ['id' => $rvConv->id]) }}" style="color: #337ab7; font-weight: 600;">#{{ $rvConv->number }}</a>
                                    @else
                                        #{{ $rv->conversation_id }}
                                    @endif
                                </td>
                                <td style="padding: 4px 8px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #666;">
                                    {{ $rvConv ? $rvConv->subject : '—' }}
                                </td>
                                <td style="padding: 4px 8px; text-align: right; white-space: nowrap; color: #999;">
                                    {{ $rv->viewed_at->format('M j g:i A T') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div style="padding: 15px; text-align: center; color: #999; border: 1px solid #ddd; border-radius: 4px;">No recent views recorded.</div>
            @endif
        </div>

        {{-- Telegram Notifications --}}
        <div style="flex: 1; min-width: 300px;">
            <h4 style="color: #666; font-size: 14px; border-bottom: 1px solid #e5e5e5; padding-bottom: 6px; margin-bottom: 10px;">
                <i class="glyphicon glyphicon-send"></i> Telegram Notifications
                <small style="color: #aaa; font-size: 11px; margin-left: 4px;">48h</small>
            </h4>
            @if(count($telegramLogs) > 0)
            <div style="border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                @foreach($telegramLogs as $tl)
                    @php $isFailed = ($tl->status === 'failed'); @endphp
                    <div style="padding: 8px 10px; border-bottom: 1px solid #eee; font-size: 11px;{{ $isFailed ? ' background: #fdf2f2;' : '' }}">
                        {{-- Line 1: Ticket + Subject --}}
                        @if($tl->conversation_number)
                            <div style="margin-bottom: 3px;">
                                <a href="{{ $tl->conversation_id ? route('conversations.view', ['id' => $tl->conversation_id]) : '#' }}" style="color: #337ab7; font-weight: 700; font-size: 12px;">#{{ $tl->conversation_number }}</a>
                                @if($tl->subject)
                                    <span style="color: #333; font-weight: 600; font-size: 12px; margin-left: 4px;">{{ $tl->subject }}</span>
                                @endif
                            </div>
                        @endif
                        {{-- Line 2: Time + Event + To + Status --}}
                        <div>
                            <span style="color: #aaa;">{{ $tl->created_at ? $tl->created_at->format('M j g:i A T') : '—' }}</span>
                            <span style="color: {{ $isFailed ? '#d9534f' : '#5cb85c' }}; font-weight: 600; margin-left: 6px;">{{ $tl->event_type }}</span>
                            <span style="color: #666; margin-left: 6px;">→
                                @if($tl->telegram_username)
                                    <span style="color: #5bc0de;">{{ '@' }}{{ $tl->telegram_username }}</span>
                                @else
                                    {{ $tl->telegram_chat_id }}
                                @endif
                            </span>
                            <span style="font-size: 10px; padding: 1px 5px; border-radius: 2px; margin-left: 6px; background: {{ $isFailed ? '#f2dede' : '#dff0d8' }}; color: {{ $isFailed ? '#a94442' : '#3c763d' }};">{{ $tl->status }}</span>
                            @if($tl->response_time_ms)
                                <span style="color: #bbb; margin-left: 4px;">{{ $tl->response_time_ms }}ms</span>
                            @endif
                        </div>
                        {{-- Line 3: Message preview --}}
                        @if($tl->message_text)
                            <div style="margin-top: 4px; font-size: 11px; color: #555; padding: 4px 8px; background: #f8f8f8; border-radius: 3px; border-left: 3px solid #5bc0de; max-height: 60px; overflow: hidden;">
                                {{ \Illuminate\Support\Str::limit(strip_tags($tl->message_text), 200) }}
                            </div>
                        @endif
                        {{-- Error --}}
                        @if($isFailed && $tl->error_message)
                            <div style="margin-top: 3px; font-size: 10px; color: #a94442; padding: 3px 8px; background: #fdf2f2; border-radius: 3px;">{{ $tl->error_message }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @else
            <div style="padding: 15px; text-align: center; color: #999; border: 1px solid #ddd; border-radius: 4px;">No Telegram notifications in the last 48 hours.</div>
            @endif
        </div>

    </div>

    {{-- ═══ SYSTEM ACTIVITY (below, scrollable) ═══ --}}
    <h3 style="margin-bottom: 15px; color: #666; font-size: 16px; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px;">
        <i class="glyphicon glyphicon-dashboard"></i> System Activity
        <small style="color: #aaa; font-size: 12px; margin-left: 8px;">Last 48 hours</small>
    </h3>

    {{-- Cron Status Bar --}}
    @if(count($cronSummary) > 0)
    <div style="margin-bottom: 15px; display: flex; gap: 8px; flex-wrap: wrap;">
        @foreach($cronSummary as $command => $log)
            @php
                $short = str_replace('freescout:', '', $command);
                $isOk = $log->status === 'completed';
            @endphp
            <span style="font-size: 11px; padding: 3px 8px; border-radius: 3px; background: {{ $isOk ? '#dff0d8' : '#f2dede' }}; color: {{ $isOk ? '#3c763d' : '#a94442' }};">
                {{ $short }}
                @if($log->started_at)
                    — {{ $log->started_at->diffForHumans() }}
                @endif
            </span>
        @endforeach
    </div>
    @endif

    {{-- Activity Feed --}}
    <div style="border: 1px solid #ddd; border-radius: 4px; overflow-x: hidden;">
        @if(count($events) > 0)
            @php $lastDate = ''; $eventCount = 0; @endphp
            @foreach($events as $i => $event)
                @php
                    if ($eventCount >= 50) break;
                    $eventCount++;
                    $date = $event['time']->format('M j, Y');
                    $showDate = ($date !== $lastDate);
                    $lastDate = $date;
                    $conv = $event['conv'] ?? null;
                    $thread = $event['thread'] ?? null;
                    $chain = $event['chain'] ?? collect();
                    $hasBody = !empty($event['has_body']) && $thread && !empty($thread->body);
                    $hasChain = $chain->count() > 0;
                    $deliveryLogs = $event['delivery_logs'] ?? collect();
                    $isIncoming = ($event['type'] ?? '') === 'customer_reply';
                    $isApiCreated = ($event['type'] ?? '') === 'api_created';
                    $isError = ($event['type'] ?? '') === 'error' || ($event['type'] ?? '') === 'login_failed' || (($event['type'] ?? '') === 'email_delivery' && ($event['color'] ?? '') === '#d9534f');
                @endphp

                @if($showDate)
                    <div style="background: #f5f5f5; padding: 4px 12px; font-size: 11px; font-weight: 600; color: #888; border-bottom: 1px solid #eee; position: sticky; top: 0; z-index: 1;">
                        {{ $date }}
                    </div>
                @endif

                <div class="{{ ($hasBody || $hasChain) ? 'sm-expand-btn' : '' }}" data-target="#sm-chain-{{ $i }}" style="padding: 10px 12px; border-bottom: 1px solid #f0f0f0; font-size: 12px; cursor: {{ ($hasBody || $hasChain) ? 'pointer' : 'default' }};{{ $isApiCreated ? ' background: #f8f0ff;' : ($isIncoming ? ' background: #f0f7ff;' : '') }}{{ $isError ? ' background: #fdf2f2;' : '' }}{{ $loop->last ? ' border-bottom: none;' : '' }}">
                    {{-- Row with icon + content --}}
                    <div style="display: flex; align-items: flex-start; gap: 8px;">
                        <i class="glyphicon {{ $event['icon'] }}" style="color: {{ $event['color'] }}; margin-top: 3px; flex-shrink: 0; width: 14px;"></i>

                        <div style="flex: 1; min-width: 0;">
                            {{-- Line 1: Subject (if conversation) or label --}}
                            @if($conv)
                                <div style="margin-bottom: 3px;">
                                    <a href="{{ route('conversations.view', ['id' => $conv->id]) }}" style="color: #337ab7; font-weight: 700; font-size: 13px;">#{{ $conv->number }}</a>
                                    <span style="color: #333; font-weight: 600; font-size: 13px; margin-left: 4px;">{{ $conv->subject }}</span>
                                </div>
                            @endif

                            {{-- Line 2: Time + Type + Actor --}}
                            <div style="margin-bottom: 2px;">
                                <span style="color: #aaa; font-size: 11px; margin-right: 6px;">{{ $event['time']->format('g:i A T') }}</span>
                                <span style="font-weight: 600; color: {{ $event['color'] }}; font-size: 11px;">{{ $event['label'] }}</span>
                                @if(!empty($event['actor']))
                                    <span style="color: #666; font-size: 11px;"> — {{ $event['actor'] }}</span>
                                @endif
                                @if(!empty($event['actor_email']))
                                    <span style="color: #999; font-size: 10px;">&lt;{{ $event['actor_email'] }}&gt;</span>
                                @endif
                            </div>

                            {{-- Line 3: Message preview (always visible) --}}
                            @if($hasBody && $thread && !empty($thread->body))
                                @php
                                    $preview = \Illuminate\Support\Str::limit(strip_tags($thread->body), 150);
                                @endphp
                                <div style="margin-top: 4px; font-size: 12px; color: #555; line-height: 1.4; padding: 6px 10px; background: {{ $isIncoming ? '#e3eef8' : '#f5f5f5' }}; border-radius: 3px; border-left: 3px solid {{ $event['color'] }};">
                                    {{ $preview }}
                                </div>
                            @endif

                            {{-- Line 4: Delivery status badges --}}
                            @if($deliveryLogs->count() > 0)
                                <div style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
                                    @foreach($deliveryLogs->take(5) as $dl)
                                        <span style="font-size: 10px; padding: 2px 6px; border-radius: 2px; background: {{ $dl->isSuccessStatus() ? '#dff0d8' : ($dl->isErrorStatus() ? '#f2dede' : '#f5f5f5') }}; color: {{ $dl->isSuccessStatus() ? '#3c763d' : ($dl->isErrorStatus() ? '#a94442' : '#777') }};">
                                            {{ $dl->getStatusName() }} → {{ $dl->email }}
                                        </span>
                                    @endforeach
                                    @if($deliveryLogs->count() > 5)
                                        <span style="font-size: 10px; color: #999;">+{{ $deliveryLogs->count() - 5 }} more</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Login/auth details --}}
                            @if(!empty($event['properties']) && (($event['type'] ?? '') === 'user_activity' || ($event['type'] ?? '') === 'login_failed'))
                                @php
                                    $props = $event['properties'];
                                    $ip = $props['ip'] ?? null;
                                    $geo = null;
                                    if ($ip) {
                                        try {
                                            $cacheKey = 'geoip_' . md5($ip);
                                            $geo = \Cache::remember($cacheKey, 3600, function() use ($ip) {
                                                $json = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,regionName,city,isp,org,as');
                                                return $json ? json_decode($json, true) : null;
                                            });
                                        } catch (\Exception $e) {}
                                    }
                                @endphp
                                <div style="margin-top: 4px; font-size: 11px; padding: 6px 10px; background: {{ ($event['type'] ?? '') === 'login_failed' ? '#fdf2f2' : '#f0f8ff' }}; border-radius: 3px; border-left: 3px solid {{ ($event['type'] ?? '') === 'login_failed' ? '#d9534f' : '#5bc0de' }};">
                                    @if(!empty($props['email']))
                                        <div style="margin-bottom: 3px;"><strong style="color: #a94442;">Email attempted:</strong> <span style="color: #555;">{{ $props['email'] }}</span></div>
                                    @endif
                                    @if($ip)
                                        <div style="margin-bottom: 2px;">
                                            <strong>IP:</strong> <span style="color: #555;">{{ $ip }}</span>
                                            @if($geo && ($geo['status'] ?? '') === 'success')
                                                <span style="margin-left: 8px; color: #666;">
                                                    <strong>Location:</strong> {{ $geo['city'] ?? '' }}{{ !empty($geo['regionName']) ? ', '.$geo['regionName'] : '' }}{{ !empty($geo['country']) ? ', '.$geo['country'] : '' }}
                                                </span>
                                                @if(!empty($geo['isp']))
                                                    <span style="margin-left: 8px; color: #888;"><strong>ISP:</strong> {{ $geo['isp'] }}</span>
                                                @endif
                                                @if(!empty($geo['org']) && $geo['org'] !== $geo['isp'])
                                                    <span style="margin-left: 8px; color: #888;"><strong>Org:</strong> {{ $geo['org'] }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                    @if(!empty($props['user_agent']))
                                        <div style="color: #888;"><strong>Device:</strong> {{ $props['user_agent'] }}</div>
                                    @endif
                                    @if(!empty($props['session_id']))
                                        <div style="color: #aaa;"><strong>Session:</strong> {{ $props['session_id'] }}</div>
                                    @endif
                                </div>
                            @endif

                            {{-- API creation details --}}
                            @if(!empty($event['api_details']))
                                @php $api = $event['api_details']; @endphp
                                <div style="margin-top: 4px; font-size: 11px; padding: 8px 10px; background: #f3e8ff; border-radius: 3px; border-left: 3px solid #9b59b6;">
                                    <div style="margin-bottom: 4px; font-weight: 700; color: #7b3fa0; font-size: 12px;">
                                        <i class="glyphicon glyphicon-cloud" style="margin-right: 3px;"></i> Created via API
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2px 16px;">
                                        <div><strong>API Key:</strong> <span style="color: #555;">{{ $api['api_key_name'] }}</span></div>
                                        <div><strong>Endpoint:</strong> <span style="color: #555;">{{ $api['endpoint'] ?? 'N/A' }}</span></div>
                                        <div><strong>IP:</strong> <span style="color: #555;">{{ $api['ip'] ?? 'N/A' }}</span></div>
                                        <div>
                                            <strong>Location:</strong>
                                            <span style="color: #555;">
                                                @if(!empty($api['city']) && !empty($api['country']))
                                                    {{ $api['city'] }}, {{ $api['country'] }}
                                                @elseif(!empty($api['country']))
                                                    {{ $api['country'] }}
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>
                                        <div><strong>Client:</strong> <span style="color: #555;">{{ $api['device'] ?? 'Unknown' }}</span></div>
                                        <div><strong>Response:</strong> <span style="color: #555;">{{ $api['response_time_ms'] ?? 0 }}ms</span></div>
                                    </div>
                                    @if(!empty($api['user_agent']))
                                        <div style="margin-top: 3px; color: #999; font-size: 10px; word-break: break-all;"><strong>User-Agent:</strong> {{ $api['user_agent'] }}</div>
                                    @endif
                                    @if(!empty($api['request_body']))
                                        @php
                                            $reqBody = $api['request_body'];
                                            $decoded = json_decode($reqBody, true);
                                            if ($decoded) {
                                                // Remove the body field (too long) for summary
                                                unset($decoded['body']);
                                                $reqBody = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                                            }
                                        @endphp
                                        <details style="margin-top: 4px;">
                                            <summary style="cursor: pointer; color: #9b59b6; font-size: 10px; font-weight: 600;">Request Payload</summary>
                                            <pre style="margin-top: 3px; font-size: 10px; color: #555; background: #ede3f7; padding: 6px 8px; border-radius: 3px; max-height: 120px; overflow: auto; white-space: pre-wrap;">{{ $reqBody }}</pre>
                                        </details>
                                    @endif
                                </div>
                            @endif

                            {{-- Error detail --}}
                            @if(!empty($event['error_detail']))
                                <pre style="font-size: 10px; color: #a94442; background: #fdf2f2; padding: 4px 8px; margin: 4px 0 0; border-radius: 3px; max-height: 60px; overflow: auto;">{{ $event['error_detail'] }}</pre>
                            @endif
                        </div>

                        {{-- Expand chevron indicator --}}
                        @if($hasBody || $hasChain)
                            <i class="glyphicon glyphicon-chevron-down" style="flex-shrink: 0; font-size: 10px; color: #ccc; margin-top: 4px;"></i>
                        @endif
                    </div>

                    {{-- Expandable email chain (5 layers deep) --}}
                    @if($hasBody || $hasChain)
                        <div id="sm-chain-{{ $i }}" style="display: none; margin-top: 8px;">
                            @if($hasChain)
                                @foreach($chain as $ci => $ct)
                                    @php
                                        $isCurrent = ($thread && $ct->id === $thread->id);
                                        $ctIsCustomer = ($ct->type == \App\Thread::TYPE_CUSTOMER);
                                        $ctIsNote = ($ct->type == \App\Thread::TYPE_NOTE);
                                        $ctActor = '';
                                        $ctEmail = '';
                                        if ($ctIsCustomer) {
                                            $ctCust = isset($custMap) ? $custMap->get($ct->created_by_customer_id) : null;
                                            if (!$ctCust && isset($custMap)) $ctCust = $custMap->get($ct->customer_id);
                                            $ctActor = $ctCust ? $ctCust->getFullName(true) : ($ct->from ?: 'Customer');
                                            $ctEmail = $ctCust ? $ctCust->getMainEmail() : $ct->from;
                                        } else {
                                            $ctUser = isset($userMap) ? $userMap->get($ct->created_by_user_id) : null;
                                            $ctActor = $ctUser ? $ctUser->getFullName() : 'Agent';
                                            $ctEmail = $ctUser ? $ctUser->email : '';
                                        }
                                        $bgColor = $isCurrent ? '#fffbe6' : ($ctIsCustomer ? '#f0f7ff' : ($ctIsNote ? '#fef9e7' : '#f9f9f9'));
                                        $borderColor = $isCurrent ? '#f0c36d' : ($ctIsCustomer ? '#b8d4f0' : '#eee');
                                        $typeLabel = $ctIsCustomer ? 'INCOMING' : ($ctIsNote ? 'NOTE' : 'OUTGOING');
                                        $typeLabelColor = $ctIsCustomer ? '#337ab7' : ($ctIsNote ? '#f0ad4e' : '#5cb85c');
                                    @endphp
                                    <div style="margin-bottom: 6px; padding: 8px 12px; background: {{ $bgColor }}; border: 1px solid {{ $borderColor }}; border-radius: 3px;{{ $isCurrent ? ' border-left: 3px solid #f0ad4e;' : '' }}">
                                        <div style="font-size: 11px; color: #888; margin-bottom: 4px; display: flex; justify-content: space-between;">
                                            <span>
                                                <span style="font-weight: 700; color: {{ $typeLabelColor }}; font-size: 10px; text-transform: uppercase; padding: 1px 4px; border-radius: 2px; background: {{ $ctIsCustomer ? '#e8f0fe' : ($ctIsNote ? '#fef3cd' : '#e8f5e9') }};">{{ $typeLabel }}</span>
                                                <strong style="color: #555; margin-left: 4px;">{{ $ctActor }}</strong>
                                                @if($ctEmail)
                                                    <span style="color: #aaa;">&lt;{{ $ctEmail }}&gt;</span>
                                                @endif
                                            </span>
                                            <span style="white-space: nowrap;">{{ $ct->created_at->format('M j g:i A T') }}</span>
                                        </div>
                                        @php
                                            $safeBody = $ct->body ?? '';
                                            // Strip tags to plain text to prevent unclosed HTML from breaking the page DOM
                                            $plainBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $safeBody)));
                                            $plainBody = preg_replace('/\n{3,}/', "\n\n", $plainBody);
                                        @endphp
                                        <div style="font-size: 12px; line-height: 1.5; word-wrap: break-word; max-height: 200px; overflow-y: auto; white-space: pre-wrap; color: #444;">{{ $plainBody }}</div>
                                    </div>
                                @endforeach
                            @elseif($hasBody)
                                {{-- Single thread body fallback --}}
                                <div style="padding: 8px 12px; background: #fafafa; border: 1px solid #eee; border-radius: 3px; max-height: 300px; overflow-y: auto;">
                                    @if(!empty($event['actor_email']))
                                        <div style="font-size: 11px; color: #888; margin-bottom: 6px;">
                                            <b>From:</b> {{ $event['actor_email'] }}
                                            @if($conv)
                                                | <b>To:</b> {{ $conv->mailbox ? $conv->mailbox->email : '' }}
                                            @endif
                                            | <b>Date:</b> {{ $event['time']->format('M j, Y g:i A') }}
                                        </div>
                                    @endif
                                    @php
                                        $safeBody2 = $thread->body ?? '';
                                        $plainBody2 = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $safeBody2)));
                                        $plainBody2 = preg_replace('/\n{3,}/', "\n\n", $plainBody2);
                                    @endphp
                                    <div style="font-size: 12px; line-height: 1.5; word-wrap: break-word; white-space: pre-wrap; color: #444;">{{ $plainBody2 }}</div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
            @if(count($events) > 50)
                <div style="padding: 8px 12px; text-align: center; color: #999; font-size: 11px; border-top: 1px solid #eee;">
                    Showing 50 of {{ count($events) }} events
                </div>
            @endif
        @else
            <div style="padding: 30px; text-align: center; color: #999;">No activity recorded in the last 48 hours.</div>
        @endif
    </div>
</div>
