<?php

declare(strict_types=1);

namespace AIBridge\Queue;

/**
 * A job exceeded its time budget.
 *
 * Extends NonRetryableJobException on purpose: the handler may already have
 * committed part of its work, so automatically requeueing a timeout would
 * replay a mutation. Timeout is a terminal failure.
 */
final class JobTimeoutException extends NonRetryableJobException {}
