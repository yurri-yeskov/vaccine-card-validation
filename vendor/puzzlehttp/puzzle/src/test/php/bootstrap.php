<?php

require dirname(__FILE__) . '/../../../vendor/autoload.php';
require dirname(__FILE__) . '/Server.php';

if (!function_exists('puzzle_get_path')) {
    require dirname(__FILE__) . '/../../../src/main/php/puzzle/functions.php';
}

if (!function_exists('puzzle_stream_create')) {
    require dirname(__FILE__) . '/../../../vendor/puzzlehttp/streams/src/main/php/puzzle/stream/functions.php';
}

function __shutdown_puzzle_tests()
{
    if (puzzle_test_Server::$started) {
        puzzle_test_Server::stop();
    }
}

register_shutdown_function('__shutdown_puzzle_tests');
