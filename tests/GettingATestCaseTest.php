<?php
namespace JSBTests;

/**
 * Route: GET {BASE_URL}/test/{slug}.json
 */
class DetailsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Return a Not found error message when the slug is not found in the database.
     * @return \Sparrow
     */
    public function testReturnANotFoundErrorMessageWhenTheSlugIsNotFoundInTheDatabase()
    {
        $data = Helper::get(BASE_URL . '/test/some-slug-that-isnt-here.json');

        $fourOhFourMessageHTML = '<h1>404 Not Found</h1><h3>The page you have requested could not be found.</h3>';
        if (trim($data) == $fourOhFourMessageHTML) {
            static::fail('Was expecting a different error message.');
        }

        $errorPayload = json_decode($data);
        static::assertTrue(is_object($errorPayload), $data);
        static::assertTrue(isset($errorPayload->error), $data);
        static::assertTrue(isset($errorPayload->error->message), $data);
        static::assertEquals('Not found.', $errorPayload->error->message);
    }

    /**
     * Will return the item with the slug.
     */
    public function testWillReturnTheItemWithTheSlug()
    {
        Helper::clearDatabase();
        Helper::seed('three-tests-with-setUp');

        $data = Helper::get(BASE_URL . '/test/auto-generated-slug.json');
        $response = json_decode($data);

        static::assertTrue(is_object($response));
        static::assertTrue(isset($response->slug));
        static::assertEquals('auto-generated-slug', $response->slug);
    }

    /**
     * When the slug is only found in a previous revision, then mark revision as a historical entity.
     */
    public function testWhenTheSlugIsOnlyFoundInAPreviousRevisionThenMarkRevisionAsAHistoricalEntity()
    {
        $modifier1 = function ($contents) {
            $contents->slug = 'slug-original';
            $contents->title = 'Original title';
        };
        $modifier2 = function ($contents) {
            $contents->slug = 'slug-second';
            $contents->title = 'This is the second revision.';
        };
        $modifier3 = function ($contents) {
            $contents->slug = 'slug-third';
            $contents->title = 'This is the third revision.';
        };
        $modifier4 = function ($contents) {
            $contents->slug = 'slug-third';
            $contents->title = 'This is the third revision with a different title.';
            // Don't change the description, will trigger another revision to be created.
            // $contents->description = 'This is the third revision description still.';
        };
        $modifier5 = function ($contents) {
            $contents->slug = 'slug-fourth';
            $contents->title = 'This is the fourth revision\'s case title.';
            $contents->description = 'This is the fourth revision description.';
        };

        Helper::createTestCase($modifier1);
        $this->assertExpectedEntries('testcases', 1);
        $this->assertExpectedEntries('revisions', 1);

        // add the second revision
        Helper::createTestCase($modifier2, false, '/test/slug-original.json');
        $this->assertExpectedEntries('testcases', 1);
        $this->assertExpectedEntries('revisions', 2);

        // create, then update the third revision
        Helper::createTestCase($modifier3, false, '/test/slug-second.json');
        $this->assertExpectedEntries('revisions', 3);
        Helper::createTestCase($modifier4, false, '/test/slug-third.json');
        $this->assertExpectedEntries('revisions', 3);

        // add the fourth revision
        Helper::createTestCase($modifier5, false, '/test/slug-third.json');
        $this->assertExpectedEntries('revisions', 4);

        // now, get the older revision (the second one)
        $thirdRevision = Helper::get(BASE_URL . '/test/slug-third.json');
        $thirdRevision = json_decode($thirdRevision);
        if (isset($thirdRevision->error)) {
            static::fail('Could not get the revision. ' . $thirdRevision->error->message);
        }
        static::assertEquals('slug-third', $thirdRevision->slug);
        static::assertEquals('This is the third revision with a different title.', $thirdRevision->title);
        static::assertEquals(3, $thirdRevision->revisionNumber);

        // the latest revision is still available at the main route
        $all = Helper::get(BASE_URL . '/tests.json');
        $all = json_decode($all);

        static::assertEquals(1, count($all));

        $latest = $all[0];
        static::assertEquals('slug-fourth', $latest->slug);
        static::assertEquals('This is the fourth revision\'s case title.', $latest->title);
        static::assertEquals('This is the fourth revision description.', $latest->description);
        static::assertEquals(4, $latest->revisionNumber);

        $this->assertExpectedEntries('testcases', 1);
        $this->assertExpectedEntries('revisions', 4);
    }

    protected function assertExpectedEntries($table, $count)
    {
        $database = Helper::getConnection();
        $rawEntries = $database->from($table)->select()->execute()->fetchAll();
        static::assertEquals($count, count($rawEntries), print_r($rawEntries, true));
        return $rawEntries;
    }
}
