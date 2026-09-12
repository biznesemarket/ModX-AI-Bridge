<?php
declare(strict_types=1);
$manifest=[
 'project'=>'modx-ai-bridge',
 'iteration'=>20,
 'certification'=>'STABLE',
 'required_runtime'=>true,
 'modx_target'=>'3.2.x',
 'primary_modx'=>'3.2.2-pl',
 'gates'=>['static','unit','api_contract','mcp_contract','security','modx_runtime','queue_concurrency','multi_site_isolation','idempotency','rollback','verification','migration','artifact_integrity','production_security'],
 'generated_at'=>gmdate('c'),
];
echo json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
