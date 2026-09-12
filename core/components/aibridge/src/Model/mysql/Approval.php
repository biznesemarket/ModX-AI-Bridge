<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Approval extends \AIBridge\Model\Approval
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_approvals',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'change_id' => NULL,
            'status' => 'pending',
            'requested_by' => NULL,
            'requested_at' => NULL,
            'decided_by' => NULL,
            'decided_at' => NULL,
            'comment' => NULL,
        ),
        'fieldMeta' => 
        array (
            'change_id' => 
            array (
                'dbtype' => 'int',
                'phptype' => 'integer',
                'null' => false,
            ),
            'status' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '32',
                'phptype' => 'string',
                'null' => false,
                'default' => 'pending',
            ),
            'requested_by' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'requested_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => false,
            ),
            'decided_by' => 
            array (
                'dbtype' => 'varchar',
                'precision' => '190',
                'phptype' => 'string',
                'null' => true,
            ),
            'decided_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
            'comment' => 
            array (
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => true,
            ),
        ),
        'indexes' => 
        array (
            'change_id' => 
            array (
                'alias' => 'change_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'change_id' => 
                    array (
                    ),
                ),
            ),
            'status' => 
            array (
                'alias' => 'status',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'status' => 
                    array (
                    ),
                ),
            ),
        ),
    );

}
