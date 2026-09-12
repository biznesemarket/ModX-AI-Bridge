<?php
declare(strict_types=1);
namespace AIBridge\AI;

final class AiContract
{
    public static function operation(string $operation,array $inputSchema,array $outputSchema,string $risk='low'): array { return ['contractVersion'=>'1.0','operation'=>$operation,'risk'=>$risk,'input'=>['schema'=>$inputSchema],'output'=>['schema'=>$outputSchema],'execution'=>['validate'=>true,'securityDecision'=>true,'audit'=>true,'idempotencyRequired'=>$risk!=='low']]; }
}
