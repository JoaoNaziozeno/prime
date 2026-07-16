<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $connection = 'tenant';

    protected $table = 'notification_preferences';

    protected $fillable = [
        'user_id',
        'event_type',
        'channels',
    ];

    protected $casts = [
        'channels' => 'array',
    ];

    /**
     * Get channels enabled for a user and event type
     * Returns e.g. ['mail', 'database']
     */
    public static function getEnabledChannels(string $userId, string $eventType): array
    {
        $pref = self::where('user_id', $userId)
            ->where('event_type', $eventType)
            ->first();

        // Default preferences if none exists: all active by default except SMS
        if (!$pref) {
            return ['mail', 'database'];
        }

        $active = [];
        foreach ($pref->channels as $channel => $enabled) {
            if ($enabled) {
                $active[] = $channel;
            }
        }

        return $active;
    }
}
