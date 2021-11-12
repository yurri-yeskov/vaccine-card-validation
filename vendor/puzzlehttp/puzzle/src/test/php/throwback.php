<?php

__throwback::$config = array(

    'name'         => 'puzzle',
    'autoload'     => dirname(__FILE__) . '/../../main/php',
    'dependencies' => array(
        array('puzzlehttp/streams', 'git://github.com/puzzlehttp/streams.git', 'src/main/php'),
    )
);
