<?php

declare(strict_types=1);

namespace AIBridge\Queue;

/**
 * Terminal job failure: the handler must not be retried.
 *
 * Not `final` so JobTimeoutException can share the non-retryable contract.
 */
class NonRetryableJobException extends \RuntimeException {}
