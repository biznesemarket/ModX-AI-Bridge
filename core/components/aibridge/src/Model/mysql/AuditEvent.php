<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class AuditEvent extends \AIBridge\Model\AuditEvent
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_audit',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'event' => NULL,
            'actor_type' => NULL,
            'actor_id' => NULL,
            'operation' => NULL,
            'resource_id' => NULL,
            'request_id' => NULL,
            'context_json' => NULL,
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
            'event' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'actor_type' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
            ),
            'actor_id' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'operation' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => true,
            ),
            'resource_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => true,
            ),
            'request_id' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'context_json' => 
            array (
                'dbtype' => 'text',
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
            'profile_id_request_id' => 
            array (
                'alias' => 'profile_id_request_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'profile_id' => 
                    array (
                    ),
                    'request_id' => 
                    array (
                    ),
                ),
            ),
            'request_id' => 
            array (
                'alias' => 'request_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'request_id' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
