<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class IdempotencyKey extends \AIBridge\Model\IdempotencyKey
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_idempotency',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'idempotency_key' => NULL,
            'principal_id' => NULL,
            'operation' => NULL,
            'request_hash' => NULL,
            'status' => 'in_progress',
            'response_json' => NULL,
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
            'idempotency_key' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'principal_id' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'operation' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'request_hash' => 
            array (
                'dbtype' => 'char',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'status' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
                'default' => 'in_progress',
            ),
            'response_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'created_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
        ),
        'indexes' => 
        array (
            'idempotency_unique' => 
            array (
                'alias' => 'idempotency_unique',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'profile_id' => 
                    array (
                    ),
                    'idempotency_key' => 
                    array (
                    ),
                    'principal_id' => 
                    array (
                    ),
                    'operation' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
