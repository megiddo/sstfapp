<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\VerifiedGoogleUser;

final class GoogleJwtIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    private const ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    public function __construct(
        private readonly string $audience,
        private readonly GoogleCertsProviderInterface $certs,
        private readonly ClockInterface $clock,
    ) {
    }

    public function verify(string $idToken): VerifiedGoogleUser
    {
        if ($this->audience === '') {
            throw new InvalidGoogleIdTokenException();
        }

        if ($idToken === '' || substr_count($idToken, '.') !== 2) {
            throw new InvalidGoogleIdTokenException();
        }

        [$headerPart, $payloadPart, $signaturePart] = explode('.', $idToken);
        $header = $this->decodeJsonObject($headerPart);
        $payload = $this->decodeJsonObject($payloadPart);
        $signature = $this->base64UrlDecode($signaturePart);
        if ($header === null || $payload === null || $signature === false) {
            throw new InvalidGoogleIdTokenException();
        }

        $alg = $header['alg'] ?? null;
        if ($alg !== 'RS256') {
            throw new InvalidGoogleIdTokenException();
        }

        $kid = $header['kid'] ?? null;
        if (!is_string($kid) || $kid === '') {
            throw new InvalidGoogleIdTokenException();
        }

        $keys = $this->certs->publicKeys();
        if (!isset($keys[$kid]) || !is_string($keys[$kid]) || $keys[$kid] === '') {
            throw new InvalidGoogleIdTokenException();
        }

        $verified = openssl_verify(
            $headerPart . '.' . $payloadPart,
            $signature,
            $keys[$kid],
            OPENSSL_ALGO_SHA256,
        );
        if ($verified !== 1) {
            throw new InvalidGoogleIdTokenException();
        }

        $issuer = $payload['iss'] ?? null;
        if (!is_string($issuer) || !in_array($issuer, self::ISSUERS, true)) {
            throw new InvalidGoogleIdTokenException();
        }

        $audience = $payload['aud'] ?? null;
        if ($audience !== $this->audience) {
            throw new InvalidGoogleIdTokenException();
        }

        $expiresAt = $payload['exp'] ?? null;
        if (!is_int($expiresAt)) {
            throw new InvalidGoogleIdTokenException();
        }
        if ($expiresAt <= $this->clock->now()->getTimestamp()) {
            throw new InvalidGoogleIdTokenException();
        }

        $email = $payload['email'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            throw new InvalidGoogleIdTokenException();
        }

        $subject = $payload['sub'] ?? null;
        if (!is_string($subject) || $subject === '') {
            throw new InvalidGoogleIdTokenException();
        }

        return new VerifiedGoogleUser(
            email: $email,
            emailVerified: ($payload['email_verified'] ?? false) === true,
            subject: $subject,
            audience: $this->audience,
            issuer: $issuer,
            expiresAt: $expiresAt,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(string $part): ?array
    {
        $json = $this->base64UrlDecode($part);
        if ($json === false) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function base64UrlDecode(string $data): string|false
    {
        $padded = strtr($data, '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad > 0) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        return base64_decode($padded, true);
    }
}
