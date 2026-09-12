<?php

declare(strict_types=1);

namespace AIBridge\Security;

use MODX\Revolution\modX;

final class RateLimiter
{
    public function __construct(private readonly modX $modx) {}

    /**
     * Fixed-window limiter backed by the Extra table. The increment is atomic
     * on MySQL/MariaDB, unlike a read-modify-write cache counter.
     */
    public function consume(string $key, int $limit, int $windowSeconds = 60, int $profileId = 0): array
    {
        if ($limit < 1) throw new \InvalidArgumentException('Rate limit must be positive.');
        $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;
        $expiresAt = date('Y-m-d H:i:s', $windowStart + $windowSeconds + 1);
        $bucketKey = hash('sha256', $key);
        $table = $this->modx->getTableName('AIBridge\\Model\\RateLimitBucket');
        $sql = sprintf(
            'INSERT INTO %s (profile_id, bucket_key, window_start, requests, expires_at) VALUES (%d, %s, %d, 1, %s) '
            . 'ON DUPLICATE KEY UPDATE requests = requests + 1, expires_at = VALUES(expires_at)',
            $table,
            $profileId,
            $this->quote($bucketKey),
            $windowStart,
            $this->quote($expiresAt)
        );
        if (!$this->modx->query($sql)) throw new \RuntimeException('Rate limiter persistence failed.');

        $stmt = $this->modx->query(sprintf(
            'SELECT requests FROM %s WHERE bucket_key=%s AND window_start=%d LIMIT 1',
            $table,
            $this->quote($bucketKey),
            $windowStart
        ));
        $requests = (int)($stmt ? $stmt->fetchColumn() : 0);
        $allowed = $requests <= $limit;
        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => max(0, $limit - $requests),
            'retry_after' => $allowed ? 0 : max(1, $windowStart + $windowSeconds - time()),
        ];
    }

    private function quote(string $value): string
    {
        return $this->modx->quote($value);
    }
}
