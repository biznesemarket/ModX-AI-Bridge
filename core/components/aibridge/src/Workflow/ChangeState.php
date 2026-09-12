<?php
declare(strict_types=1);
namespace AIBridge\Workflow;
final class ChangeState {
 public const DRAFT='draft', PENDING_APPROVAL='pending_approval', APPROVED='approved', REJECTED='rejected', EXECUTING='executing', COMPLETED='completed', FAILED='failed', CANCELLED='cancelled';
 private const TRANSITIONS=[
  self::DRAFT=>[self::PENDING_APPROVAL,self::CANCELLED],
  self::PENDING_APPROVAL=>[self::APPROVED,self::REJECTED,self::CANCELLED],
  self::APPROVED=>[self::EXECUTING,self::CANCELLED],
  self::EXECUTING=>[self::COMPLETED,self::FAILED],
  self::FAILED=>[self::EXECUTING,self::CANCELLED],
  self::REJECTED=>[], self::COMPLETED=>[], self::CANCELLED=>[]
 ];
 public static function canTransition(string $from,string $to):bool{return in_array($to,self::TRANSITIONS[$from]??[],true);}
 public static function terminal(string $state):bool{return in_array($state,[self::REJECTED,self::COMPLETED,self::CANCELLED],true);}
}
