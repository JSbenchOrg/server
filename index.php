<?php
// loader.php
require_once __DIR__ . '/deps/flight/flight/autoload.php';
require_once __DIR__ . '/deps/sparrow/sparrow.php';
require_once __DIR__ . '/app/Controllers/TestCase.php';
require_once __DIR__ . '/app/Models/Entry.php';
require_once __DIR__ . '/app/Models/Harness.php';
require_once __DIR__ . '/app/Models/Metric.php';
require_once __DIR__ . '/app/Models/Revision.php';
require_once __DIR__ . '/app/Exception.php';
require_once __DIR__ . '/app/Store.php';

$allowedHosts = [
    'http://jsbench.org',
    'http://www.jsbench.org',
];

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS' && in_array($_SERVER['HTTP_ORIGIN'], $allowedHosts)) {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']) && $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] == 'GET') {
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: X-Requested-With');
    }
    exit;
}

// setup.php
$config = require_once __DIR__ . '/config.php';

if ($config['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// dependencies.php
$engine = new \flight\Engine();
$request = $engine->request(); /** @var \flight\net\Request $request */
$dsn = 'mysql:host=' . $config['mysql-host'] . ';dbname=' . $config['mysql-database'] . ';charset=utf8';
$database = new \Sparrow();
$database->setDb(new \PDO($dsn, $config['mysql-username'], $config['mysql-password']));
$store = new \JSB\Store($database);
$controller = new \JSB\Controllers\TestCase($store);

// routes.php
$engine->_route('GET /', function () use ($engine, $config) {
    return $engine->_redirect($config['baseUrl'] . '/tests.json');
});

$engine->_route('GET /tests.json', function () use ($engine, $controller) {
    return $engine->_json($controller->listing());
});

$engine->_route('GET /test/@slug.json', function ($slug) use ($engine, $controller) {
    return $engine->_json($controller->find($slug));
});

$engine->_route('GET /test/@slug/revisions.json', function ($slug) use ($engine, $controller) {
    return $engine->_json($controller->getRevisionsForTestCase($slug));
});

$engine->_route('GET /test/@slug/@revisionNumber:[0-9]+.json', function ($slug, $revisionNumber) use ($engine, $controller) {
    return $engine->_json($controller->find($slug, $revisionNumber));
});

$engine->_route('GET /test/@slug/totals/by-browser.json', function ($slug) use ($engine, $controller) {
    return $engine->_json($controller->reportByBrowser($slug));
});

$engine->_route('GET /test/@slug/@revisionNumber:[0-9]+/totals/by-browser.json', function ($slug, $revisionNumber) use ($engine, $controller) {
    return $engine->_json($controller->reportByBrowser($slug, $revisionNumber));
});

$engine->_route('POST /tests.json', function () use ($engine, $controller, $request) {
    $model = \JSB\Models\Revision::fromData(json_decode($request->getBody()));
    if ($controller->store($model)) {
        $response = $controller->find($model->slug);
        return $engine->_json($response);
    }
});

$engine->_route('POST /test/@slug.json', function ($slug) use ($engine, $controller, $request) {
    $model = \JSB\Models\Revision::fromData(json_decode($request->getBody()));
    if ($controller->store($model, $slug)) {
        $response = $controller->find($model->slug);
        return $engine->_json($response);
    }
});

$engine->_route('GET /log.json', function () use ($engine, $controller) {
    return $engine->_json($controller->getJavaScriptErrorEntries());
});

$engine->_route('POST /log.json', function () use ($engine, $controller, $request) {
    $body = json_decode($request->getBody(), true);
    return $engine->_json($controller->addErrorLogEntry($body));
});


// start
try {
    $engine->_start();

} catch (\JSB\Exception $e) {
    echo $engine->_json([
        'error' => [
            'message' => $e->getMessage(),
            'data' => $e->getDetails(),
        ],
    ], 400);

} catch (\Exception $e) {
    echo $engine->_json([
        'error' => [
            'message' => $e->getMessage(),
            'code' => \JSB\Exception::APPLICATION_ERROR
        ],
    ], 400);
}
