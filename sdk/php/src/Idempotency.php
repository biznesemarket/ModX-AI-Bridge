<?php
declare(strict_types=1);
namespace AIBridge\SDK;
final class Idempotency { public static function key(): string { return bin2hex(random_bytes(16)); } }
