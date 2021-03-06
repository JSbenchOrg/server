<?php
namespace JSBTests;

/**
 * Route: GET /
 */
class MiscTest extends \PHPUnit_Framework_TestCase
{
    /**
     * When the root path is accessed, redirect to the tests.json path.
     * @group initial
     * @group anytime
     * @group remote
     */
    public function testWhenTheRootPathIsAccessedRedirectToTheTestsJsonPath()
    {
        $contents = Helper::get(BASE_URL . '/');
        $headerString = Helper::getHeaders();
        $headerLines = explode("\n", $headerString);
        $headers = array_map(
            function ($value) {
                return trim($value);
            },
            $headerLines
        );

        $debugResponse = print_r($headers, true) . PHP_EOL . $contents;
        $isMoved = 'HTTP/1.1 303 See Other' == $headers[0];
        static::assertTrue($isMoved, $debugResponse);

        $locationString = 'Location: ' . BASE_URL;
        $isLocationToTests = in_array($locationString . '/tests.json', $headers);
        static::assertTrue($isLocationToTests, $debugResponse);
    }
}