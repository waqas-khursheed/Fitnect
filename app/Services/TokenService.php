<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TokenService
{
    private static $appAccessKey;
    private static $appSecret;
    private $managementToken;

    public function __construct()
    {
        self::$appAccessKey = config('app.100ms_app_access_key');
        self::$appSecret = config('app.100ms_app_secret_key');

        $this->managementToken = $this->getManagementToken(true);
    }


    // Generate a new management token if forced or if the existing token is expired
    public function getManagementToken($forceNew = false)
    {
        if ($forceNew || $this->isTokenExpired($this->managementToken)) {
            $payload = [
                'access_key' => self::$appAccessKey,
                'type' => 'management',
                'version' => 2,
                'iat' => time() - 100, // 10 seconds earlier
                'nbf' => time() - 100, // 10 seconds earlier
            ];

            $this->managementToken = $this->signPayloadToToken($payload);
        }

        return $this->managementToken;
    }

    // Sign the payload with the app secret to generate a JWT token
    private function signPayloadToToken(array $payload)
    {
        $payload['jti'] = Str::uuid()->toString();
        return JWT::encode($payload, self::$appSecret, 'HS256');
    }


    // Check if the token is expired or will expire soon
    private function isTokenExpired($token)
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$appSecret, 'HS256'));
            $buffer = 30; // Buffer time in seconds
            $currentTime = time();

            // Check if the token's `exp` exists and if it's close to expiring
            return !isset($decoded->exp) || ($decoded->exp + $buffer < $currentTime);
        } catch (\Exception $e) {
            // If decoding fails, consider the token as expired
            Log::error('Error decoding token: ' . $e->getMessage());
            return true;
        }
    }

}
