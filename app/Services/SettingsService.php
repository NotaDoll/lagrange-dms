<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public function get(string $group, string $key, $default = null)
    {
        $setting = $this->cachedGroup($group)[$key] ?? null;

        if (! $setting) {
            return $default;
        }

        return $setting['is_secret'] && $setting['value'] !== null
            ? Crypt::decryptString($setting['value'])
            : $setting['value'];
    }

    /** Returns [key => value] for a whole group, decrypting secrets. */
    public function getGroup(string $group): array
    {
        return collect($this->cachedGroup($group))
            ->mapWithKeys(fn ($setting, $key) => [
                $key => $setting['is_secret'] && $setting['value'] !== null
                    ? Crypt::decryptString($setting['value'])
                    : $setting['value'],
            ])
            ->all();
    }

    public function set(string $group, string $key, ?string $value, bool $secret = false): void
    {
        Setting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $secret && $value !== null ? Crypt::encryptString($value) : $value,
                'is_secret' => $secret,
            ]
        );

        Cache::forget("settings.group.{$group}");
    }

    /**
     * Plain array cache — NOT an Eloquent Collection — to avoid unserialize()
     * failures if the Setting model class changes shape between cache writes/reads.
     *
     * @return array<string, array{value: ?string, is_secret: bool}>
     */
    private function cachedGroup(string $group): array
    {
        return Cache::rememberForever(
            "settings.group.{$group}",
            fn () => Setting::where('group', $group)
                ->get()
                ->keyBy('key')
                ->map(fn (Setting $setting) => [
                    'value' => $setting->value,
                    'is_secret' => $setting->is_secret,
                ])
                ->all()
        );
    }
}