<?php

declare(strict_types=1);

namespace AIBridge\Tests\Unit\Queue;

use AIBridge\Queue\JobState;
use PHPUnit\Framework\TestCase;

final class JobStateTest extends TestCase
{
    public function testAllowedTransitions(): void
    {
        self::assertTrue(JobState::canTransition(JobState::QUEUED, JobState::RUNNING));
        self::assertTrue(JobState::canTransition(JobState::RUNNING, JobState::COMPLETED));
        self::assertTrue(JobState::canTransition(JobState::RUNNING, JobState::QUEUED));
        self::assertFalse(JobState::canTransition(JobState::COMPLETED, JobState::RUNNING));
    }

    public function testTerminalStates(): void
    {
        self::assertTrue(JobState::terminal(JobState::COMPLETED));
        self::assertTrue(JobState::terminal(JobState::CANCELLED));
        self::assertFalse(JobState::terminal(JobState::FAILED));
    }
}
