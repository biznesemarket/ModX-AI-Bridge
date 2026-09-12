<?php

declare(strict_types=1);

use MODX\Revolution\modExtraManagerController;

class IndexManagerController extends modExtraManagerController
{
    public static function getDefaultController()
    {
        return 'home';
    }

    public function getLanguageTopics()
    {
        return ['aibridge:default'];
    }

    public function checkPermissions()
    {
        return true;
    }
}
