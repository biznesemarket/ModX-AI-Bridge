<?php
declare(strict_types=1);
namespace AIBridge\Verification;
final class VerificationResult {
 public function __construct(private readonly bool $valid, private readonly array $expected=[], private readonly array $actual=[], private readonly array $mismatches=[], private readonly array $warnings=[]) {}
 public function toArray(): array { return ['valid'=>$this->valid,'expected'=>$this->expected,'actual'=>$this->actual,'mismatches'=>$this->mismatches,'warnings'=>$this->warnings]; }
}
