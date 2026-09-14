<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$required = [
    '_build/build.php',
    '_build/config.inc.php',
    '_build/elements/menus.php',
    '_build/resolvers/install-model.resolver.php',
    'core/components/aibridge/bootstrap.php',
    'core/components/aibridge/controllers/index.class.php',
    'core/components/aibridge/controllers/home.class.php',
    'core/components/aibridge/controllers/home.class.php',
    'core/components/aibridge/processors/health.class.php',
    'core/components/aibridge/src/Model/Token.php',
    'core/components/aibridge/src/Model/metadata.mysql.php',
];

$failed = [];

$removed = [
    'core/components/aibridge/src/Validators/RequestValidator.php',
    'core/components/aibridge/src/Middleware/AuthenticationMiddleware.php',
    'core/components/aibridge/src/Middleware/AuthorizationMiddleware.php',
    'core/components/aibridge/src/Middleware/RateLimitMiddleware.php',
    'core/components/aibridge/src/Services/AssetService.php',
    'core/components/aibridge/src/Services/SchemaService.php',
    'core/components/aibridge/src/Processors/ResourceCreateProcessor.php',
    'core/components/aibridge/src/Processors/ResourceUpdateProcessor.php',
    'core/components/aibridge/src/Processors/ResourceDeleteProcessor.php',
    'core/components/aibridge/src/Processors/ResourcePreviewProcessor.php',
    'core/components/aibridge/src/Processors/ResourcePublishProcessor.php',
    'core/components/aibridge/src/Processors/ResourceValidateProcessor.php',
    'core/components/aibridge/src/Processors/SiteSchemaProcessor.php',
    'core/components/aibridge/src/Processors/SiteFingerprintProcessor.php',
];

foreach ($required as $relative) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $relative)) {
        $failed[] = $relative;
    }
}

foreach ($removed as $relative) {
    if (is_file($root . DIRECTORY_SEPARATOR . $relative)) {
        $failed[] = $relative . ' must stay removed (dead stub, Iteration 65)';
    }
}

$cacheInvalidation = (string) file_get_contents($root . '/core/components/aibridge/src/Services/CacheInvalidationService.php');
if (str_contains($cacheInvalidation, "refresh(['db' => []])")) {
    $failed[] = 'CacheInvalidationService must not flush the whole db cache partition (Defect #41)';
}

$build = file_get_contents($root . '/_build/build.php');

if (str_contains($build, 'modAction')) {
    $failed[] = '_build/build.php contains legacy modAction';
}

if (!str_contains($build, "require __DIR__ . '/config.inc.php'")) {
    $failed[] = '_build/build.php does not use config.inc.php';
}

if ($failed) {
    fwrite(STDERR, "Static contract FAILED\n");
    foreach ($failed as $failure) {
        fwrite(STDERR, " - {$failure}\n");
    }
    exit(1);
}

echo "Static contract: PASS\n";
