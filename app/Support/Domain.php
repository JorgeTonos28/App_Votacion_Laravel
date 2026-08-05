<?php

namespace App\Support;

use Illuminate\Support\Str;

final class Domain
{
    public const EVENT_STATUSES = ['Draft', 'Scheduled', 'LobbyOpen', 'Live', 'Paused', 'Finished', 'Published', 'Archived'];

    public const PRESENTATION_STATUSES = ['Pending', 'OnStage', 'VotingOpen', 'VotingClosed', 'Scored', 'Skipped', 'Disqualified'];

    public const ACCESS_MODES = ['Device', 'IndividualCode', 'AttendeeList', 'Hybrid'];

    public const RESULTS_VISIBILITIES = ['Hidden', 'ParticipationOnly', 'LiveAverage', 'PartialRanking', 'PublishedOnly'];

    public const COMMENT_MODES = ['Hidden', 'Optional', 'Required'];

    public const VOTER_STATUSES = ['Active', 'Revoked', 'Blocked'];

    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public static function uuid(): string
    {
        return (string) Str::uuid();
    }

    public static function normalizeCode(?string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($value ?? ''))) ?? '';
    }

    public static function randomCode(int $length = 6): string
    {
        $value = '';
        for ($i = 0; $i < $length; $i++) {
            $value .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $value;
    }

    public static function jurorCode(): string
    {
        $raw = self::randomCode(8);

        return substr($raw, 0, 4).'-'.substr($raw, 4);
    }

    public static function hashSecret(string $secret): string
    {
        return password_hash(self::normalizeCode($secret), PASSWORD_ARGON2ID);
    }

    public static function verifySecret(string $secret, ?string $hash): bool
    {
        return $hash !== null && password_verify(self::normalizeCode($secret), $hash);
    }

    public static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function technicalHash(?string $value): string
    {
        return substr(strtoupper(hash('sha256', $value ?? '')), 0, 32);
    }
}
