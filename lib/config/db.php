<?php

return array(
    'shop_kitchenaids_category' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'url' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'description' => array('text', 'null' => 0),
        'parent_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('id'),
        ),
    ),
    'shop_kitchenaids_product' => array(
        'id' => array('int', 11, 'null' => 0, 'autoincrement' => 1),
        'url' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'sku' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'price' => array('int', 11, 'null' => 0),
        'summary' => array('text', 'null' => 0),
        'description' => array('text', 'null' => 0),
        'title' => array('text', 'null' => 0),
        'meta_keywords' => array('text', 'null' => 0),
        'meta_description' => array('text', 'null' => 0),
        'video' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'instruction' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'category_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'PRIMARY' => array('id'),
        ),
    ),
    'shop_kitchenaids_feature' => array(
        'product_id' => array('int', 11, 'null' => 0),
        'name' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'value' => array('varchar', 255, 'null' => 0, 'default' => ''),
        ':keys' => array(
            'product_id' => 'product_id',
        ),
    ),
    'shop_kitchenaids_categories' => array(
        'category_id' => array('int', 11, 'null' => 0),
        'product_id' => array('int', 11, 'null' => 0),
        ':keys' => array(
            'category_id' => 'category_id',
            'product_id' => 'product_id',
        ),
    ),
);
