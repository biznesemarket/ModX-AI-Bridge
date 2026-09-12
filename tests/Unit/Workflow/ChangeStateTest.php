<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\Workflow;
use AIBridge\Workflow\ChangeState;
use PHPUnit\Framework\TestCase;
final class ChangeStateTest extends TestCase {
 public function testValidTransitions():void{
  $valid=[
   ChangeState::DRAFT=>[ChangeState::PENDING_APPROVAL,ChangeState::CANCELLED],
   ChangeState::PENDING_APPROVAL=>[ChangeState::APPROVED,ChangeState::REJECTED,ChangeState::CANCELLED],
   ChangeState::APPROVED=>[ChangeState::EXECUTING,ChangeState::CANCELLED],
   ChangeState::EXECUTING=>[ChangeState::COMPLETED,ChangeState::FAILED],
   ChangeState::FAILED=>[ChangeState::EXECUTING,ChangeState::CANCELLED],
  ];
  foreach($valid as $from=>$targets)foreach($targets as $to)self::assertTrue(ChangeState::canTransition($from,$to),"expected {$from} -> {$to}");
 }
 public function testInvalidTransitions():void{
  $invalid=[
   [ChangeState::DRAFT,ChangeState::APPROVED],[ChangeState::DRAFT,ChangeState::EXECUTING],[ChangeState::DRAFT,ChangeState::COMPLETED],[ChangeState::DRAFT,ChangeState::FAILED],
   [ChangeState::PENDING_APPROVAL,ChangeState::EXECUTING],[ChangeState::PENDING_APPROVAL,ChangeState::COMPLETED],[ChangeState::PENDING_APPROVAL,ChangeState::FAILED],
   [ChangeState::APPROVED,ChangeState::COMPLETED],[ChangeState::APPROVED,ChangeState::FAILED],[ChangeState::APPROVED,ChangeState::APPROVED],
   [ChangeState::EXECUTING,ChangeState::APPROVED],[ChangeState::EXECUTING,ChangeState::PENDING_APPROVAL],[ChangeState::EXECUTING,ChangeState::CANCELLED],
   [ChangeState::FAILED,ChangeState::APPROVED],[ChangeState::FAILED,ChangeState::COMPLETED],
  ];
  foreach($invalid as [$from,$to])self::assertFalse(ChangeState::canTransition($from,$to),"expected {$from} -> {$to} to be rejected");
 }
 public function testTerminalStatesHaveNoOutgoingTransitions():void{
  foreach([ChangeState::REJECTED,ChangeState::COMPLETED,ChangeState::CANCELLED] as $terminal){
   self::assertTrue(ChangeState::terminal($terminal));
   foreach([ChangeState::DRAFT,ChangeState::PENDING_APPROVAL,ChangeState::APPROVED,ChangeState::EXECUTING,ChangeState::FAILED,ChangeState::COMPLETED,ChangeState::REJECTED,ChangeState::CANCELLED] as $to){
    self::assertFalse(ChangeState::canTransition($terminal,$to),"terminal {$terminal} must not move to {$to}");
   }
  }
 }
 public function testNonTerminalStates():void{
  foreach([ChangeState::DRAFT,ChangeState::PENDING_APPROVAL,ChangeState::APPROVED,ChangeState::EXECUTING,ChangeState::FAILED] as $state)self::assertFalse(ChangeState::terminal($state));
 }
}
