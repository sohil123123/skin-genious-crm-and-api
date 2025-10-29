<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use Spatie\Permission\Models\Permission;
use App\Models\Role;

if (!function_exists('get_user_ip')) {
    /**
     * Get validated user IP address
     */
    function get_user_ip(): string
    {
        $request = app(Request::class);
        $ip = $request->ip();

        // Check forwarded headers
        if ($forwarded = $request->header('HTTP_X_FORWARDED_FOR')) {
            $ips = explode(',', $forwarded);
            $ip = trim($ips[0]);
        } elseif ($clientIp = $request->header('HTTP_CLIENT_IP')) {
            $ip = $clientIp;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : ($request->ip() ?: '127.0.0.1');
    }
}

if (!function_exists('is_public_ip')) {
    /**
     * Check if IP is public (not private or reserved)
     */
    function is_public_ip(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}

if (!function_exists('get_user_location')) {
    /**
     * Get user location from IP using geoplugin
     */
    function get_user_location(?string $ip = null): ?array
    {
        $ip = $ip ?? get_user_ip();

        if (!is_public_ip($ip)) {
            return null;
        }

        try {
            $response = Http::timeout(10)->get('http://www.geoplugin.net/json.gp', ['ip' => $ip]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['geoplugin_latitude'], $data['geoplugin_longitude'])) {
                    return [
                        'latitude' => (float) $data['geoplugin_latitude'],
                        'longitude' => (float) $data['geoplugin_longitude'],
                        'country' => $data['geoplugin_countryName'] ?? null,
                        'city' => $data['geoplugin_city'] ?? null,
                        'ip' => $ip,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Geolocation failed for IP {$ip}: {$e->getMessage()}");
        }

        return null;
    }
}

if (!function_exists('remove_empty_value')) {
    function remove_empty_value($array){
        return array_values(array_filter($array));
    }
}


// ---------------------------------- Filament Functions ------------------------------

if (!function_exists('has_clinic_related_role')) {
    function has_clinic_related_role(?array $roleIds): bool
    {
        $roles = Role::whereIn('id', $roleIds ?? [])->pluck('name')->toArray();

        return in_array('clinic_manager', $roles)
            || in_array('user', $roles)
            || in_array('therapist', $roles);
    }
}

if (!function_exists('has_user_related_role')) {
    function has_user_related_role(?array $roleIds): bool
    {
        $roles = Role::whereIn('id', $roleIds ?? [])->pluck('name')->toArray();

        return in_array('user', $roles);
    }
}
