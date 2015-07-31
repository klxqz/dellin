<?php

return array(
    'dellinplugin' => array(
        'code' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'region_id' => array('int', 11, 'null' => 0),
        'region' => array('varchar', 255, 'null' => 0, 'default' => ''),
        'city_id' => array('int', 11, 'null' => 0),
        'city' => array('varchar', 255, 'null' => 0, 'default' => ''),
        ':keys' => array(
            'city' => 'city',
        ),
    ),
);
