<?php
namespace JSBTests;

/**
 * Route: GET /
 */
class MiscTest extends \PHPUnit_Framework_TestCase
{
    /**
     * When the root path is accessed, redirect to the tests.json path.
     */
    public function testWhenTheRootPathIsAccessedRedirectToTheTestsJsonPath()
    {
        $contents = Helper::get(BASE_URL);
        $headerString = Helper::getHeaders();
        $headerLines = explode("\n", $headerString);
        $headers = array_map(
            function ($value) {
                return trim($value);
            },
            $headerLines
        );

        static::assertContains('HTTP/1.1 303 See Other', $headers, print_r($headers, true) . PHP_EOL . $contents);
        static::assertContains('Location: ' . BASE_URL . '/tests.json', $headers);
    }
}