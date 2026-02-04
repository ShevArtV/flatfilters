<?php

$xpdo_meta_map['ffConfigResource'] = array(
    'package' => 'flatfilters',
    'version' => '1.1',
    'table' => 'ff_config_resources',
    'extends' => 'xPDOSimpleObject',
    'fields' =>
        array(
            'resource_id' => null,
            'config_id' => null,
        ),
    'tableMeta' =>
        array(
            'engine' => 'InnoDB',
        ),
    'fieldMeta' =>
        array(
            'resource_id' =>
                array(
                    'dbtype' => 'int',
                    'precision' => '10',
                    'attributes' => 'unsigned',
                    'phptype' => 'integer',
                    'null' => false,
                ),
            'config_id' =>
                array(
                    'dbtype' => 'int',
                    'precision' => '10',
                    'attributes' => 'unsigned',
                    'phptype' => 'integer',
                    'null' => false,
                    'index' => 'index',
                ),
        ),
    'indexes' =>
        array(
            'item' =>
                array(
                    'alias' => 'item',
                    'primary' => false,
                    'unique' => true,
                    'type' => 'BTREE',
                    'columns' =>
                        array(
                            'config_id' =>
                                array(
                                    'length' => '',
                                    'collation' => 'A',
                                    'null' => false,
                                ),
                            'resource_id' =>
                                array(
                                    'length' => '',
                                    'collation' => 'A',
                                    'null' => false,
                                ),
                        ),
                ),
        ),
);
