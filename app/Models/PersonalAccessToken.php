<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Find the token instance matching the given token.
     * Supports multi-database multitenancy by searching both the current database connection
     * (e.g. tenant database for Customer Portal tokens) and the central/master database connection
     * (for Master User / ERP staff tokens).
     *
     * @param  string  $token
     * @return static|null
     */
    public static function findToken($token)
    {
        // 1. Try finding the token on the current default connection
        $instance = parent::findToken($token);
        if ($instance) {
            return $instance;
        }

        // 2. If not found and tenancy is initialized, fall back to searching on the central connection
        $centralConnection = config('tenancy.database.central_connection', 'central');
        if (config('database.default') !== $centralConnection) {
            try {
                $centralModel = (new static)->setConnection($centralConnection);

                if (strpos($token, '|') === false) {
                    $tokenModel = $centralModel->where('token', hash('sha256', $token))->first();
                } else {
                    [$id, $tokenValue] = explode('|', $token, 2);
                    $found = $centralModel->find($id);
                    $tokenModel = ($found && hash_equals($found->token, hash('sha256', $tokenValue))) ? $found : null;
                }

                if ($tokenModel) {
                    return $tokenModel;
                }
            } catch (\Throwable $e) {
                // Silently ignore connection or table errors on fallback
            }
        }

        return null;
    }
}
