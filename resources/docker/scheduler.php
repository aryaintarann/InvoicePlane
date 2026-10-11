<?php

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

function run_docker_scheduler_tick(ClientInterface $http, string $cron_key): string
{
    if (trim($cron_key) === '') {
        return 'cron_key_missing';
    }
    try {
        $response = $http->request('GET', 'http://app/index.php/invoices/cron/recur/' . rawurlencode($cron_key), [
            'connect_timeout' => 3,
            'timeout'         => 55,
            'allow_redirects' => false,
            'http_errors'     => false,
        ]);
    } catch (GuzzleException $e) {
        return 'cron_request_failed';
    }

    return $response->getStatusCode() === 200 ? 'processed' : 'cron_request_failed';
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require '/var/www/html/vendor/autoload.php';

$http     = new Client();
$messages = [
    'processed'             => 'Scheduler tick completed.',
    'cron_key_missing'      => 'Scheduler waiting for a cron key configured in Settings.',
    'cron_request_failed'   => 'Scheduler request failed; retrying next minute.',
    'scheduler_unavailable' => 'Scheduler could not read settings; retrying next minute.',
];

while (true) {
    $started = microtime(true);
    $db      = null;
    try {
        $db = mysqli_init();
        $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
        $db->real_connect((string) getenv('DB_HOSTNAME'), (string) getenv('DB_USERNAME'), (string) getenv('DB_PASSWORD'), (string) getenv('DB_DATABASE'), (int) (getenv('DB_PORT') ?: 3306));
        $setting = $db->query("SELECT setting_value FROM ip_settings WHERE setting_key = 'cron_key' LIMIT 1")->fetch_assoc();
        $result  = run_docker_scheduler_tick($http, (string) ($setting['setting_value'] ?? ''));
    } catch (Throwable $e) {
        // Exceptions may contain the cron key, database credentials, or request URL.
        $result = 'scheduler_unavailable';
    } finally {
        if ($db !== null) {
            $db->close();
        }
    }
    echo gmdate('Y-m-d H:i:s') . ' UTC ' . $messages[$result] . PHP_EOL;
    sleep(max(1, 60 - (int) (microtime(true) - $started)));
}
