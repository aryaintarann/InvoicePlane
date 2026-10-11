<?php

require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/IntegrationEnvironment.php';
require APPPATH . 'modules/notifications/models/Mdl_notifications.php';

$GLOBALS['test_application'] = new IntegrationApplication(integration_database());
$delay                       = max(0, (float) $argv[2] - microtime(true));
usleep((int) ($delay * 1000000));
echo (int) (new Mdl_notifications())->claim((int) $argv[1], new DateTimeImmutable('+1 minute'));
