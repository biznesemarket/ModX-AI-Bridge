<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\Workflow;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;
final class ChangeStateTest extends TestCase {
 public function testValidTransitions():void{$this->assertTrue(ChangeState::canTransition('draft','pending_approval'));$this->assertTrue(ChangeState::canTransition('pending_approval','approved'));$this->assertTrue(ChangeState::canTransition('approved','executing'));$this->assertTrue(ChangeState::canTransition('executing','completed'));}
 public function testInvalidTransitions():void{$this->assertFalse(ChangeState::canTransition('draft','approved'));$this->assertFalse(ChangeState::canTransition('rejected','approved'));}
}
