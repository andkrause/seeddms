<?php

spl_autoload_register(function ($class) {
    // Simulate a host application's existing autoloader.
});

require dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('PEAR_LOG_ERR')) {
    define('PEAR_LOG_ERR', 3);
}

if (!class_exists('SeedDMS_ExtBase')) {
    class SeedDMS_ExtBase {}
}

require dirname(__DIR__) . '/class.llmclassifier.php';
