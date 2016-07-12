<?php
// v1 - clean install, single shot of migration
require_once __DIR__ . '/tests/bootstrap.php';

$configFile = __DIR__ . '/config.php';
file_exists($configFile) || die(-1);

$config = require_once $configFile;
$util = new \JSBTests\Helper($config);
$util->resetDatabase();

if ($config['live']) {
    unlink(__DIR__ . '/migrate.php');
}
die(0);