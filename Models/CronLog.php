<?php

namespace Modules\SystemMonitor\Models;

use Illuminate\Database\Eloquent\Model;

class CronLog extends Model
{
    public $timestamps = false;

    protected $table = 'cron_logs';

    protected $fillable = [
        'command', 'status', 'output', 'duration_ms', 'started_at', 'finished_at',
    ];

    protected $dates = ['started_at', 'finished_at'];

    public static function logStart($command)
    {
        $log = self::create([
            'command'    => $command,
            'status'     => 'started',
            'started_at' => now(),
        ]);
        return $log->id;
    }

    public static function logFinish($id, $status = 'completed', $output = null, $durationMs = 0)
    {
        $log = self::find($id);
        if ($log) {
            $log->status      = $status;
            $log->output       = $output ? substr($output, 0, 5000) : null;
            $log->duration_ms  = $durationMs;
            $log->finished_at  = now();
            $log->save();
        }
    }
}
