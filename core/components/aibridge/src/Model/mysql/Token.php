<?php
namespace AIBridge\Model\mysql;

use xPDO\xPDO;

class Token extends \AIBridge\Model\Token
{

    public static $metaMap = array (
        'package' => 'AIBridge\\Model',
        'version' => '3.0',
        'table' => 'aibridge_tokens',
        'extends' => 'xPDO\\Om\\xPDOSimpleObject',
        'tableMeta' => 
        array (
            'engine' => 'InnoDB',
        ),
        'fields' => 
        array (
            'profile_id' => NULL,
            'name' => NULL,
            'token_hash' => NULL,
            'status' => 'active',
            'expires_at' => NULL,
            'last_used_at' => NULL,
            'scopes_json' => '[]',
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
            'token_hash' => 
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
                'default' => 'active',
            ),
            'expires_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
            'last_used_at' => 
            array (
                'dbtype' => 'datetime',
                'phptype' => 'datetime',
                'null' => true,
            ),
            'scopes_json' => 
            array (
                'dbtype' => 'text',
                'phptype' => 'string',
                'null' => false,
                'default' => '[]',
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
            'profile_id' => 
            array (
                'alias' => 'profile_id',
                'primary' => false,
                'unique' => false,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'profile_id' => 
                    array (
                    ),
                ),
            ),
            'token_hash' => 
            array (
                'alias' => 'token_hash',
                'primary' => false,
                'unique' => true,
                'type' => 'BTREE',
                'columns' => 
                array (
                    'token_hash' => 
                    array (
                        'length' => '64',
                    ),
                ),
            ),
        ),
    );

}
