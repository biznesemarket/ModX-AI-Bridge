<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class ChangeRequest extends \AIBridge\Model\ChangeRequest
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_change_requests',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'operation' => NULL,
            'resource_id' => NULL,
            'status' => 'draft',
            'input_json' => NULL,
            'before_json' => NULL,
            'after_json' => NULL,
            'diff_json' => NULL,
            'qa_json' => NULL,
            'approval_id' => NULL,
            'job_id' => NULL,
            'requested_by' => NULL,
            'request_id' => NULL,
            'rejection_reason' => NULL,
            'created_at' => NULL,
            'updated_at' => NULL,
        ),
        'fieldMeta' => 
        array (
            'profile_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'operation' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '64',
                'phptype' => 'string',
                'null' => false,
            ),
            'resource_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => true,
            ),
            'status' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
                'default' => 'draft',
            ),
            'input_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => false,
            ),
            'before_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'after_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'diff_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'qa_json' => 
            array (
                'dbtype' => 'longtext',
                'phptype' => 'string',
                'null' => true,
            ),
            'approval_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => true,
            ),
            'job_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => true,
            ),
            'requested_by' => 
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
            'rejection_reason' => 
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
            'updated_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
        ),
        'indexes' => 
        array (
            'profile_status' => 
            array (
                'alias' => 'profile_status',
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
                ),
            ),
            'approval_id' => 
            array (
                'alias' => 'approval_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'approval_id' => 
                    array (
                    ),
                ),
            ),
            'job_id' => 
            array (
                'alias' => 'job_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'job_id' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
