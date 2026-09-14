<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class RateLimitBucket extends \AIBridge\Model\RateLimitBucket
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_rate_limits',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'bucket_key' => NULL,
            'window_start' => NULL,
            'requests' => 0,
            'expires_at' => NULL,
        ),
        'fieldMeta' => 
        array (
            'profile_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'bucket_key' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'window_start' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'requests' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ),
            'expires_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
        ),
        'indexes' => 
        array (
            'bucket_unique' => 
            array (
                'alias' => 'bucket_unique',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'profile_id' => 
                    array (
                    ),
                    'bucket_key' => 
                    array (
                    ),
                    'window_start' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
