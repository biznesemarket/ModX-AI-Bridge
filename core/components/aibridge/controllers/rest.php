<?php

declare(strict_types=1);

/**
 * REST boundary placeholder.
 *
 * Do not place domain logic here. The production implementation will route
 * authenticated requests to application services.
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'component' => 'modx-ai-bridge',
    'status' => 'stub',
    'message' => 'REST API entry point is not enabled in the skeleton.',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
