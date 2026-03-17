<?php

namespace Modules\SystemMonitor\Models;

use App\Conversation;
use App\User;
use Illuminate\Database\Eloquent\Model;

class EmailViewLog extends Model
{
    public $timestamps = false;

    protected $table = 'email_view_logs';

    protected $fillable = [
        'user_id', 'conversation_id', 'duration_seconds', 'viewed_at',
    ];

    protected $dates = ['viewed_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public static function logView($userId, $conversationId)
    {
        return self::create([
            'user_id'         => $userId,
            'conversation_id' => $conversationId,
            'viewed_at'       => now(),
        ]);
    }

    public static function logFinish($conversationId, $userId, $durationSeconds)
    {
        $log = self::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where('duration_seconds', 0)
            ->orderBy('viewed_at', 'desc')
            ->first();

        if ($log) {
            $log->duration_seconds = $durationSeconds;
            $log->save();
        }
    }
}
