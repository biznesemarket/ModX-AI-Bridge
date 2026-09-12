<?php

declare(strict_types=1);

class AIBridgeHomeManagerController extends AIBridgeManagerController
{
    public function process(array $scriptProperties = [])
    {
        return '<div id="aibridge-app"></div><div id="aibridge-resource-workspace"></div>';
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('aibridge');
    }

    public function loadCustomCssJs()
    {
        $assetsUrl = $this->modx->getOption(
            'aibridge_assets_url',
            null,
            $this->modx->getOption('assets_url') . 'components/aibridge/'
        );

        $this->addCss(rtrim($assetsUrl, '/') . '/css/manager.css');
        $this->addJavascript(rtrim($assetsUrl, '/') . '/js/manager.js');
    }
}
