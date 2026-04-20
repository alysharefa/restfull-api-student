<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class JwtService
{
    public function issue(User $user): string
    {
        $now = time();
        $ttl = $this->ttlSeconds();

        $payload = [
            'iss' => config('jwt.issuer'),
            'sub' => $user->id,
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => (string) Str::uuid(),
        ];

        return $this->encode($payload);
    }

    public function decode(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            throw new InvalidArgumentException('Invalid token format.');
        }

        [$headerSegment, $payloadSegment, $signatureSegment] = $segments;

        $header = $this->decodeJson($headerSegment);
        $payload = $this->decodeJson($payloadSegment);
        $signature = $this->base64UrlDecode($signatureSegment);
        $expectedSignature = hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret(), true);

        if (($header['alg'] ?? null) !== 'HS256' || ($header['typ'] ?? null) !== 'JWT') {
            throw new InvalidArgumentException('Invalid token header.');
        }

        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Invalid token signature.');
        }

        if (! isset($payload['exp']) || $payload['exp'] < time()) {
            throw new InvalidArgumentException('Token has expired.');
        }

        return $payload;
    }

    public function ttlSeconds(): int
    {
        return (int) config('jwt.ttl', 60) * 60;
    }

    private function encode(array $payload): string
    {
        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $headerSegment = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadSegment = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $headerSegment.'.'.$payloadSegment, $this->secret(), true);

        return $headerSegment.'.'.$payloadSegment.'.'.$this->base64UrlEncode($signature);
    }

    private function decodeJson(string $segment): array
    {
        $decoded = $this->base64UrlDecode($segment);
        $payload = json_decode($decoded, true);

        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid token payload.');
        }

        return $payload;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Unable to decode token segment.');
        }

        return $decoded;
    }

    private function secret(): string
    {
        $secret = (string) config('jwt.secret');

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }
}
