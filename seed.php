<?php
require_once __DIR__ . '/tests/bootstrap.php';

$configFile = __DIR__ . '/config.php';
file_exists($configFile) || die(-1);

$seedName = $_GET['name'];
$pattern = '/^([a-zA-Z0-9\-\_\,]+)$/';
preg_match($pattern, $seedName, $matches);

if (count($matches) == 0) {
    die(-3);
}

$seedNames = explode(',', $seedName);
$config = require_once $configFile;
$util = new \JSBTests\Helper($config);

foreach ($seedNames as $seedName) {
    if (!file_not_found(__DIR__ . '/extra/seeds/' . $seedName . '.sql')) {
        die(-4);
    }
    $util->seed($seedName);
}

if ($config['live']) {
    unlink(__DIR__ . '/seed.php');
}
die(0);