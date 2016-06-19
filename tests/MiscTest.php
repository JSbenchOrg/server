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
        Helper::get(BASE_URL);
        $headers = explode("\n", Helper::getHeaders());
        $headers = array_map(
            function ($value) {
                return trim($value);
            },
            $headers
        );

        static::assertContains('HTTP/1.1 303 See Other', $headers);
        static::assertContains('Location: ' . BASE_URL . '/tests.json', $headers);
    }
}