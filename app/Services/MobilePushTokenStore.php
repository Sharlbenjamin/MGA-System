<?php

namespace App\Services;

use App\Models\MobilePushToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MobilePushTokenStore
{
    /**
     * @return list<string>
     */
    public static function allowedNotificationSounds(): array
    {
        return ['default', 'none', 'mga_bell.wav', 'mga_chime.wav', 'mga_alert.wav'];
    }

    public static function normalizeNotificationSound(?string $sound): string
    {
        $sound = trim((string) $sound);
        if ($sound === '') {
            return 'default';
        }

        return in_array($sound, self::allowedNotificationSounds(), true) ? $sound : 'default';
    }

    public function put(int $userId, string $token, string $platform, ?string $notificationSound = null): void
    {
        $token = trim($token);
        if ($token === '' || ! str_starts_with($token, 'ExponentPushToken')) {
            return;
        }
        $notificationSound = self::normalizeNotificationSound($notificationSound);

        if (Schema::hasTable('mobile_push_tokens')) {
            $payload = ['platform' => $platform];
            if (Schema::hasColumn('mobile_push_tokens', 'notification_sound')) {
                $payload['notification_sound'] = $notificationSound;
            }
            MobilePushToken::query()->updateOrCreate(
                ['user_id' => $userId, 'token' => $token],
                $payload,
            );

            $extra = MobilePushToken::query()
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->skip(8)
                ->pluck('id');

            if ($extra->isNotEmpty()) {
                MobilePushToken::query()->whereIn('id', $extra)->delete();
            }

            return;
        }

        $items = $this->cacheAll($userId);
        $items[$token] = [
            'platform' => $platform,
            'notification_sound' => $notificationSound,
            'updated_at' => now()->toIso8601String(),
        ];
        if (count($items) > 8) {
            $items = array_slice($items, -8, null, true);
        }
        Cache::put($this->cacheKey($userId), $items, now()->addDays(90));
    }

    public function forget(int $userId, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        if (Schema::hasTable('mobile_push_tokens')) {
            MobilePushToken::query()
                ->where('user_id', $userId)
                ->where('token', $token)
                ->delete();

            return;
        }

        $items = $this->cacheAll($userId);
        unset($items[$token]);
        Cache::put($this->cacheKey($userId), $items, now()->addDays(90));
    }

    public function forgetToken(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        if (Schema::hasTable('mobile_push_tokens')) {
            MobilePushToken::query()->where('token', $token)->delete();

            return;
        }

        // Cache fallback: scan keys is not practical; token will expire on next successful put sweep.
    }

    /**
     * @return list<string>
     */
    public function forUser(int $userId): array
    {
        return array_map(
            fn (array $target) => $target['token'],
            $this->pushTargetsForUser($userId),
        );
    }

    /**
     * @return list<array{token: string, notification_sound: string}>
     */
    public function pushTargetsForUser(int $userId): array
    {
        if (Schema::hasTable('mobile_push_tokens')) {
            $hasSound = Schema::hasColumn('mobile_push_tokens', 'notification_sound');
            $columns = $hasSound ? ['token', 'notification_sound'] : ['token'];

            return MobilePushToken::query()
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get($columns)
                ->map(fn (MobilePushToken $row) => [
                    'token' => $row->token,
                    'notification_sound' => self::normalizeNotificationSound($hasSound ? ($row->notification_sound ?? 'default') : 'default'),
                ])
                ->all();
        }

        $items = $this->cacheAll($userId);
        $targets = [];
        foreach ($items as $token => $meta) {
            if (! is_string($token) || ! str_starts_with($token, 'ExponentPushToken')) {
                continue;
            }
            $sound = is_array($meta) ? ($meta['notification_sound'] ?? 'default') : 'default';
            $targets[] = [
                'token' => $token,
                'notification_sound' => self::normalizeNotificationSound($sound),
            ];
        }

        return $targets;
    }

    public function updateNotificationSound(int $userId, string $notificationSound, ?string $token = null): void
    {
        $notificationSound = self::normalizeNotificationSound($notificationSound);

        if (Schema::hasTable('mobile_push_tokens') && Schema::hasColumn('mobile_push_tokens', 'notification_sound')) {
            $query = MobilePushToken::query()->where('user_id', $userId);
            if ($token !== null && trim($token) !== '') {
                $query->where('token', trim($token));
            }
            $query->update(['notification_sound' => $notificationSound]);

            return;
        }

        $items = $this->cacheAll($userId);
        foreach ($items as $key => $meta) {
            if ($token !== null && $key !== trim($token)) {
                continue;
            }
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['notification_sound'] = $notificationSound;
            $items[$key] = $meta;
        }
        Cache::put($this->cacheKey($userId), $items, now()->addDays(90));
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function cacheAll(int $userId): array
    {
        $items = Cache::get($this->cacheKey($userId), []);

        return is_array($items) ? $items : [];
    }

    protected function cacheKey(int $userId): string
    {
        return 'mobile-push-tokens:'.$userId;
    }
}
