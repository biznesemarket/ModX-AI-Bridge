<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Job extends \AIBridge\Model\Job
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_jobs',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'type' => NULL,
            'status' => 'queued',
            'payload_json' => NULL,
            'result_json' => NULL,
            'error_json' => NULL,
            'attempts' => 0,
            'max_attempts' => 3,
            'timeout_seconds' => 300,
            'available_at' => NULL,
            'locked_at' => NULL,
            'locked_by' => NULL,
            'progress' => 0,
            'progress_json' => NULL,
            'idempotency_key' => NULL,
            'principal_id' => NULL,
            'request_id' => NULL,
            'created_at' => NULL,
            'started_at' => NULL,
            'finished_at' => NULL,
        ),
        'fieldMeta' => 
        array (
            'profile_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'type' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => false,
            ),
            'status' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
                'default' => 'queued',
            ),
            'payload_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'result_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'error_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'attempts' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ),
            'max_attempts' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 3,
            ),
            'timeout_seconds' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 300,
            ),
            'available_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
            'locked_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
            'locked_by' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'progress' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
                'default' => 0,
            ),
            'progress_json' => 
            array (
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ),
            'idempotency_key' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'principal_id' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'request_id' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'created_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
            'started_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
            'finished_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
        ),
        'indexes' => 
        array (
            'profile_id_status_available' => 
            array (
                'alias' => 'profile_id_status_available',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'profile_id' => 
                    array (
                    ),
                    'status' => 
                    array (
                    ),
                    'available_at' => 
                    array (
                    ),
                ),
            ),
            'status_available' => 
            array (
                'alias' => 'status_available',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'status' => 
                    array (
                    ),
                    'available_at' => 
                    array (
                    ),
                ),
            ),
            'locked_at' => 
            array (
                'alias' => 'locked_at',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'status' => 
                    array (
                    ),
                    'locked_at' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
