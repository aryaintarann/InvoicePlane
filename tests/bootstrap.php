<?php

require dirname(__DIR__) . '/vendor/autoload.php';
define('BASEPATH', dirname(__DIR__) . '/vendor/pocketarc/codeigniter/system/');
define('APPPATH', dirname(__DIR__) . '/application/');
foreach (['Whatsapp_policy', 'Fonnte_adapter'] as $library) {
    $path = APPPATH . 'modules/notifications/libraries/' . $library . '.php';
    if (is_file($path)) {
        require_once $path;
    }
}
