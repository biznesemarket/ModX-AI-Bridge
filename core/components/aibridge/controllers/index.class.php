<?php

declare(strict_types=1);

use MODX\Revolution\modExtraManagerController;

abstract class AIBridgeManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return ['aibridge:default'];
    }

    public function checkPermissions()
    {
        return true;
    }
}

class IndexManagerController extends AIBridgeManagerController
{
    public static function getDefaultController()
    {
        return 'home';
    }
}
