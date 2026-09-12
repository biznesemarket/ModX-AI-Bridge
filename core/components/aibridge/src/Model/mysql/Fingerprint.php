<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Fingerprint extends \AIBridge\Model\Fingerprint
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_fingerprints',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'algorithm' => NULL,
            'fingerprint' => NULL,
            'snapshot_json' => NULL,
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
            'algorithm' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'fingerprint' => 
            array (
                'dbtype' => 'char',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'snapshot_json' => 
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
