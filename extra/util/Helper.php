<?php
namespace JSBTests;

require_once __DIR__ . '/../../deps/sparrow/sparrow.php';

class Helper
{
    /** @var \PDO */
    protected $storage;

    /** @var  unique prefix, used to track a test run */
    protected static $key;

    protected $config;

    protected static $lastCallHeaders;

    protected static $flagIsRunningRemote;

    public function __construct($config)
    {
        $this->config = $config;
        $this->storage = new \PDO(
            'mysql:host=' . $config['mysql-host'] . ';dbname=' . $config['mysql-database'] . ';charset=utf8',
            $config['mysql-username'],
            $config['mysql-password']
        );
    }

    protected static function isRunningOnRemote()
    {
        if (!is_bool(static::$flagIsRunningRemote)) {
            static::$flagIsRunningRemote = file_exists(__DIR__ . '/../../.run-remote-flag');
        }
        return static::$flagIsRunningRemote;
    }

    public function resetDatabase()
    {
        if (static::isRunningOnRemote()) {
            static::get(BASE_URL . '/migrate.php');
            return null;
        }

        $config = $this->config;
        $statement = $this->storage->prepare('show tables');
        $statement->execute();

        $response = array_map(function($row) use ($config) {return $row['Tables_in_' . $config['mysql-database']];}, $statement->fetchAll());
        if (!empty($response)) {
            foreach ($response as $table) {
                $statement = $this->storage->prepare('drop table ' . $table);
                $statement->execute();
            }
        }

        $this->storage->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        $sqlFolder = __DIR__ . '/../migrations/';
        $files = scandir($sqlFolder);

        foreach ($files as $snapshotFile) {
            $match = strpos($snapshotFile, 'migration-');
            if ($match === 0) {
                echo "Snapshot file: " . $snapshotFile . "\n";
                $loadedFile = $sqlFolder . $snapshotFile;
                $contents = file_get_contents($loadedFile);
                $temp = explode(";\n", $contents);
                foreach ($temp as $statementString) {
                    $statementString = trim($statementString);
                    if (!empty($statementString)) {
                        $statement = $this->storage->prepare($statementString);
                        $statement->execute();
                    }
                }
                echo "----------------------------------------------------------------------------------------------\n";
            }
        }
        echo "\n\nDone\n\n";
    }

    public static function post($url, $contents)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($contents));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        $responseRaw = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        static::$lastCallHeaders = substr($responseRaw, 0, $header_size);
        $body = substr($responseRaw, $header_size);
        return $body;
    }

    public static function get($url)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        $responseRaw = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        static::$lastCallHeaders = substr($responseRaw, 0, $header_size);
        $body = substr($responseRaw, $header_size);
        return $body;
    }

    public static function getHeaders()
    {
        return static::$lastCallHeaders;
    }

    public static function addSnapshot($contents, $delta = 0) {
        $entries = [];
        $contents->entries = (array) $contents->entries;
        foreach ($contents->entries as $k => $entry) {
            $entries[$k] = $entry;
            $entries[$k]->results->opsPerSec = $entry->results->opsPerSec + $delta;
        }
        $contents->entries = $entries;
        $responseRaw = self::post(BASE_URL . '/tests.json', $contents);
        return json_decode($responseRaw);
    }

    /**
     * @return \Sparrow
     * @throws \Exception
     */
    public static function getConnection()
    {
        static $connection;

        if (!($connection instanceof \Sparrow)) {
            $config = require_once __DIR__ . '/../../config.php';
            $dsn = 'mysql:host=' . $config['mysql-host'] . ';dbname=' . $config['mysql-database'] . ';charset=utf8';
            $connection = new \Sparrow();
            $connection->setDb(new \PDO($dsn, $config['mysql-username'], $config['mysql-password']));
        }
        return $connection;
    }

    public static function seed($seedName)
    {
        if (static::isRunningOnRemote()) {
            static::get(BASE_URL . '/seed.php?name=' . $seedName);
            return null;
        }
        $database = static::getConnection();
        $seed = file_get_contents(__DIR__ . '/../../extra/seeds/' . $seedName . '.sql');
        $instructions = explode(";\n", $seed);
        foreach ($instructions as $instruction) {
            $database->sql($instruction)->execute();
        }
    }

    public static function clearDatabase()
    {
        if (static::isRunningOnRemote()) {
            static::get(BASE_URL . '/migrate.php');
            return null;
        }

        // empty all known tables
        $database = static::getConnection();
        array_map(
            function ($tableName) use ($database) {
                $database->from($tableName)->delete()->execute();
            },
            ['browsers', 'entries', 'errors', 'os', 'revisions', 'testcases', 'totals']
        );
    }

    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function createTestCase($callback, $clearDatabase = true, $url = '/tests.json')
    {
        if ($clearDatabase) {
            static::clearDatabase();
        }
        $contents = json_decode(file_get_contents(__DIR__ . '/../../extra/testcases/request.three-tests-with-setUp.json'));
        $callback($contents);

        $responseBody = static::post(BASE_URL . $url, $contents);
        return json_decode($responseBody);
    }

    public static function isErrorResponse($responseBody, $expectedMessage, $expectedData = null)
    {
        $response = json_decode($responseBody);
        \PHPUnit_Framework_TestCase::assertTrue(is_object($response));
        \PHPUnit_Framework_TestCase::assertTrue(isset($response->error));
        \PHPUnit_Framework_TestCase::assertEquals($expectedMessage, $response->error->message);
        \PHPUnit_Framework_TestCase::assertEquals($expectedData, $response->error->data);
    }
}
