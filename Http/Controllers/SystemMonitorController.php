<?php

namespace Modules\SystemMonitor\Http\Controllers;

use App\SendLog;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SystemMonitor\Models\CronLog;
use Modules\SystemMonitor\Models\EmailViewLog;

class SystemMonitorController extends Controller
{
    public function cronLogs(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }

        $logs = CronLog::where('started_at', '>=', now()->subHours(48))
            ->orderBy('started_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json(['logs' => $logs]);
    }

    public function emailLogs(Request $request)
    {
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            abort(403);
        }

        $type = $request->get('type', 'sent');

        if ($type === 'sent') {
            $logs = SendLog::where('created_at', '>=', now()->subHours(48))
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        } else {
            $logs = Thread::where('type', Thread::TYPE_CUSTOMER)
                ->where('state', Thread::STATE_PUBLISHED)
                ->where('created_at', '>=', now()->subHours(48))
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        }

        return response()->json(['logs' => $logs]);
    }
}
