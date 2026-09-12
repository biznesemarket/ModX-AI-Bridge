<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Policy extends \AIBridge\Model\Policy
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_policies',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'name' => NULL,
            'rules_json' => NULL,
            'status' => 'active',
            'created_at' => NULL,
        ),
        'fieldMeta' => 
        array (
            'profile_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'name' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'rules_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => false,
            ),
            'status' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
                'default' => 'active',
            ),
            'created_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
        ),
    );

}
