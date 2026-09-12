<?php

declare(strict_types=1);

$modx->regClientCSS($modx->getOption('aibridge_assets_url') . 'css/manager.css');
$modx->regClientStartupScript($modx->getOption('aibridge_assets_url') . 'js/manager.js');

return <<<HTML
<div class="aibridge-manager">
    <header class="aibridge-manager__header">
        <h1>ModX AI Bridge</h1>
        <span class="aibridge-status">Skeleton / Development</span>
    </header>

    <section class="aibridge-card">
        <h2>AI Control Layer</h2>
        <p>
            Manager shell for Site Intelligence, contracts, policies, audit,
            jobs and API configuration.
        </p>
    </section>
</div>
HTML;
