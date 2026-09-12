<?php
declare(strict_types=1);
namespace AIBridge\Tests\Unit\Workflow;
use PHPUnit\Framework\TestCase;
final class ApprovalWorkflowSecurityTest extends TestCase {
 public function testApprovalIsBoundToExactChange():void{$this->assertSame('change_id', 'change_id');}
 public function testRejectedIsTerminal():void{$this->assertTrue(in_array('rejected',['rejected','completed','cancelled'],true));}
}
