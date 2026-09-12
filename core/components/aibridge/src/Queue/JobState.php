<?php

declare(strict_types=1);

namespace AIBridge\Queue;

final class JobState
{
    public const QUEUED = 'queued';
    public const RUNNING = 'running';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    private const TRANSITIONS = [
        self::QUEUED => [self::RUNNING, self::CANCELLED],
        self::RUNNING => [self::COMPLETED, self::FAILED, self::CANCELLED, self::QUEUED],
        self::FAILED => [self::QUEUED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function terminal(string $state): bool
    {
        return in_array($state, [self::COMPLETED, self::CANCELLED], true);
    }
}
