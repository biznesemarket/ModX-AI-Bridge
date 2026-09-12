<?php
declare(strict_types=1);
namespace AIBridge\SDK;
final class ApiException extends \RuntimeException { public function __construct(string $message, public readonly int $status=0, public readonly ?string $errorCode=null, public readonly array $details=[]) { parent::__construct($message,$status); } }
