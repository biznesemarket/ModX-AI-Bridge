<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Snapshot extends \AIBridge\Model\Snapshot
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_snapshots',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'resource_id' => NULL,
            'operation' => NULL,
            'data_json' => NULL,
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
            'resource_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => true,
            ),
            'operation' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'data_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => false,
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
