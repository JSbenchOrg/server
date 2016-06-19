<?php
namespace JSBTests;

/**
 * Route: GET {BASE_URL}/tests.json
 */
class ListingTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Returns empty list when no test cases are in the database.
     * @return \Sparrow
     */
    public function testReturnsEmptyListWhenNoTestCasesAreInTheDatabase()
    {
        Helper::clearDatabase();

        // get listing. check if route is available and later on, if headers are valid
        $data = Helper::get(BASE_URL . '/tests.json');
        $entry = json_decode($data);

        // expecting an empty array
        static::assertTrue(is_array($entry), var_export($entry, true));
    }

    /**
     * Returns an array with one test case when a test case is stored in the database.
     * @depends testReturnsEmptyListWhenNoTestCasesAreInTheDatabase
     */
    public function testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase()
    {
        Helper::seed('three-tests-with-setUp');

        $data = Helper::get(BASE_URL . '/tests.json');
        $listing = json_decode($data);

        static::assertTrue(is_array($listing));
        static::assertEquals(1, count($listing));
        static::assertArrayHasKey(0, $listing);
        static::assertTrue(is_object($listing[0]));

        return $listing[0];
    }

    /**
     * The item has a title.
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasATitle($entry)
    {
        static::assertTrue(isset($entry->title));
        static::assertEquals('My test case', $entry->title);
    }

    /**
     * The item has a slug.
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasASlug($entry)
    {
        static::assertTrue(isset($entry->slug));
        static::assertEquals('auto-generated-slug', $entry->slug);
    }

    /**
     * The item has a status.
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasAStatus($entry)
    {
        static::assertTrue(isset($entry->status));
        static::assertEquals('public', $entry->status);
    }

    /**
     * The item has a description.
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasADescription($entry)
    {
        static::assertTrue(isset($entry->description));
        static::assertEquals('This is a description', $entry->description);
    }

    /**
     * The item has a revision number (starting at 1).
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasARevisionNumberStartingAt1($entry)
    {
        static::assertTrue(isset($entry->revisionNumber));
        static::assertEquals(1, $entry->revisionNumber);
    }

    /**
     * The item has a harness
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheItemHasAHarness($entry)
    {
        static::assertTrue(isset($entry->harness));
        static::assertTrue(is_object($entry->harness));

        static::assertTrue(isset($entry->harness->html));
        static::assertEquals('', $entry->harness->html);

        $expectedSetUp = "var str = 'Hello, world.',\n        q = 'Hell',\n        l = q.length,\n        re = /Hell/i;";
        static::assertTrue(isset($entry->harness->setUp));
        static::assertEquals($expectedSetUp, $entry->harness->setUp);

        static::assertTrue(isset($entry->harness->tearDown));
        static::assertEquals('', $entry->harness->tearDown);
    }

    /**
     * The sample item will have valid entries.
     * @depends testReturnsAnArrayWithOneTestCaseWhenATestCaseIsStoredInTheDatabase
     */
    public function testTheSampleItemWillHaveValidEntries($entry)
    {
        static::assertTrue(isset($entry->entries));
        static::assertTrue(is_array($entry->entries));
        static::assertEquals(3, count($entry->entries));

        for ($i = 1; $i < 3; $i++) {
            static::assertTrue(isset($entry->entries[$i]->id));
            static::assertTrue(isset($entry->entries[$i]->title));
            static::assertTrue(isset($entry->entries[$i]->code));
            static::assertTrue(isset($entry->entries[$i]->totals));
            static::assertTrue(is_array($entry->entries[$i]->totals));
            static::assertEquals(1, count($entry->entries[$i]->totals));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->metricType));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->metricValue));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->runCount));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->browserName));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->browserVersion));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->osArchitecture));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->osFamily));
            static::assertTrue(isset($entry->entries[$i]->totals[0]->osVersion));

            static::assertEquals('opsPerSec', $entry->entries[$i]->totals[0]->metricType);
            static::assertEquals('1', $entry->entries[$i]->totals[0]->runCount);
            static::assertEquals('Chrome', $entry->entries[$i]->totals[0]->browserName);
            static::assertEquals('50.0.2661.94', $entry->entries[$i]->totals[0]->browserVersion);
            static::assertEquals('32', $entry->entries[$i]->totals[0]->osArchitecture);
            static::assertEquals('Windows', $entry->entries[$i]->totals[0]->osFamily);
            static::assertEquals('10.0', $entry->entries[$i]->totals[0]->osVersion);
        }

        static::assertEquals(1, $entry->entries[0]->id);
        static::assertEquals('test', $entry->entries[0]->title);
        static::assertEquals('/Hell/i.test(str);', $entry->entries[0]->code);
        static::assertEquals('50', $entry->entries[0]->totals[0]->metricValue);

        static::assertEquals(2, $entry->entries[1]->id);
        static::assertEquals('search', $entry->entries[1]->title);
        static::assertEquals('str.search(q) > -1;', $entry->entries[1]->code);
        static::assertEquals('111', $entry->entries[1]->totals[0]->metricValue);

        static::assertEquals(3, $entry->entries[2]->id);
        static::assertEquals('match', $entry->entries[2]->title);
        static::assertEquals('str.match(q).length > 0;', $entry->entries[2]->code);
        static::assertEquals('100', $entry->entries[2]->totals[0]->metricValue);
    }

    /**
     * Returns a list of items but ignore older revisions - get only latest revisions for each test case.
     */
    public function testReturnsAListOfItemsButIgnoreOlderRevisionsGetOnlyLatestRevisionsForEachTestCase()
    {
        $createFirst = function ($contents) {
            $contents->slug = 'test-first';
        };
        $createSecond = function ($contents) {
            $contents->slug = 'test-second';
        };
        $updateFirst = function ($contents) {
            $contents->slug = 'test-first-updated';
            $contents->harness->html = '<!-- second revision -->';
        };
        Helper::createTestCase($createFirst, true, '/tests.json');
        Helper::createTestCase($updateFirst, false, '/test/test-first.json');
        Helper::createTestCase($createSecond, false, '/tests.json');

        $response = Helper::get(BASE_URL . '/tests.json');
        $response = json_decode($response);

        static::assertEquals(2, count($response));

        foreach ($response as $item) {
            static::assertTrue(in_array($item->slug, ['test-first-updated', 'test-second']), print_r($response, true));
        }
    }
}
