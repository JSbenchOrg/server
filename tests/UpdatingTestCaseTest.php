<?php
namespace JSBTests;

/**
 * Route: POST {BASE_URL}/test/{slug}.json
 */
class UpdatingTestCaseTest extends \PHPUnit_Framework_TestCase
{
    /**
     * When a different slug is sent for an existing slug address, insert a new revision.
     */
    public function testWhenADifferentSlugIsSentForAnExistingSlugAddressInsertANewRevision()
    {
        $modifier1 = function ($contents) {
            $contents->slug = 'slug-1';
        };
        $modifier2 = function ($contents) {
            $contents->slug = 'slug-2';
        };

        Helper::createTestCase($modifier1);
        Helper::createTestCase($modifier2, false, '/test/slug-1.json');

        $database = Helper::getConnection();

        $rawEntries = $database->from('testcases')->select()->execute()->fetchAll();
        static::assertEquals(1, count($rawEntries));

        $rawEntries = $database->from('revisions')->select()->execute()->fetchAll();
        static::assertEquals(2, count($rawEntries));
    }

    /**
     * When a different slug is sent for an existing slug address but the updated slug collides with an existing test case, then throw exception.
     */
    public function testWhenADifferentSlugIsSentForAnExistingSlugAddressButTheUpdatedSlugCollidesWithAnExistingTestCaseThenThrowException()
    {
        $modifier1 = function ($contents) {
            $contents->slug = 'slug-first';
        };
        $modifier2 = function ($contents) {
            $contents->slug = 'slug-second';
        };
        Helper::createTestCase($modifier1, true, '/tests.json'); // create first
        Helper::createTestCase($modifier2, false, '/tests.json'); // create second
        $response = Helper::createTestCase($modifier1, false, '/test/slug-second.json'); // try to update the second with the first's slug (=> collision)

        $reasonData = [
            (object) [
                'reason' => 'There already is a test case with this slug [slug-first].',
                'code' => 'EXISTING_SLUG'
            ]
        ];
        Helper::isErrorResponse(json_encode($response), 'Could not update the revision.', $reasonData);

        $database = Helper::getConnection();
        $rawEntries = $database->from('testcases')->select()->execute()->fetchAll();
        static::assertEquals(2, count($rawEntries));
        $rawEntries = $database->from('revisions')->select()->execute()->fetchAll();
        static::assertEquals(2, count($rawEntries));
    }

    /**
     * When a test case had 3 entries and the second one will not be sent, then a revision is created, totals for 1 and 3 are incremented and 2 will not be inherited to the revision. entry 3 will have the id 2 now.
     */
    public function testWhenATestCaseHad3EntriesAndTheSecondOneWillNotBeSentThenARevisionIsCreatedTotalsFor1And3AreIncrementedAnd2WillNotBeInheritedToTheRevisionEntry3WillHaveTheId2Now()
    {
        $modifier1 = function ($contents) {
            $contents->slug = 'test-remove-entry';
        };
        $modifier2 = function ($contents) {
            $contents->slug = 'test-remove-entry';
            unset($contents->entries->{2});
        };
        Helper::createTestCase($modifier1, true, '/tests.json');
        Helper::createTestCase($modifier2, false, '/tests.json');
        $response = Helper::get(BASE_URL . '/test/test-remove-entry.json');
        $response = json_decode($response);

        static::assertEquals(2, $response->revisionNumber);
        static::assertEquals(2, count($response->entries));
        static::assertEquals(1, count($response->entries[0]->totals));
        static::assertEquals(1, count($response->entries[1]->totals));
        static::assertEquals(2, $response->entries[0]->totals[0]->runCount);
        static::assertEquals(2, $response->entries[0]->totals[0]->runCount);
    }

    /**
     * When the harness is changed then create a new revision.
     */
    public function testWhenTheHarnessIsChangedThenCreateANewRevision()
    {
        $slug = 'check-revisions-increment-on-harness-change';

        $defaultSlug = function ($contents) use ($slug) {
            $contents->slug = $slug;
        };

        $modifierTitle = function ($contents) use ($defaultSlug) {
            $defaultSlug($contents);
            $contents->title = 'title' . uniqid();
        };

        $modifierEnvironment = function ($contents) use ($defaultSlug) {
            $defaultSlug($contents);
            $contents->env->browserVersion = '55';
        };

        $modifierHarness = function ($modifierTarget) use ($defaultSlug) {
            return function ($contents) use ($modifierTarget, $defaultSlug) {
                $defaultSlug($contents);
                foreach (explode(',', $modifierTarget) as $key) {
                    $contents->harness->{$key} = 'var harnessChanged = "' . $key . '";';
                }
            };
        };

        Helper::createTestCase($modifierTitle, true, '/tests.json'); // revision = 1, was just created
        Helper::createTestCase($modifierEnvironment, true, '/tests.json'); // revision = 1, only the totals were changed
        Helper::createTestCase($modifierHarness('html'), false, '/tests.json'); // revision should be = 2
        Helper::createTestCase($modifierHarness('html,setUp'), false, '/tests.json'); // revision should be = 3
        Helper::createTestCase($modifierHarness('html,setUp,tearDown'), false, '/tests.json'); // revision should be = 4

        $response = Helper::get(BASE_URL . '/test/' . $slug . '.json');
        $response = json_decode($response);

        static::assertEquals(4, $response->revisionNumber);
    }

    /**
     * When the harness is not changed then don't create a new revision.
     */
    public function testWhenTheHarnessIsNotChangedThenDonTCreateANewRevision()
    {
        $modifier = function ($contents) {
            $contents->slug = 'test-revision-harness-not-changed';
        };
        Helper::createTestCase($modifier, true, '/tests.json');
        Helper::createTestCase($modifier, false, '/tests.json');
        $response = Helper::get(BASE_URL . '/test/test-revision-harness-not-changed.json');
        $response = json_decode($response);

        static::assertEquals(1, $response->revisionNumber);
    }

    /**
     * When the environment is changed, will not increment the revision, just merge the values.
     */
    public function testWhenTheEnvironmentIsChangedWillNotIncrementTheRevisionJustMergeTheValues()
    {
        $modifierBrowser = function ($browserName, $browserVersion, $scores) {
            return function ($contents) use ($browserName, $browserVersion, $scores) {
                $contents->slug = 'test-env-change-aggregation';
                $contents->env->browserName = $browserName;
                $contents->env->browserVersion = $browserVersion;

                foreach ($scores as $key => $value) {
                    $contents->entries->{$key}->results->opsPerSec = $value;
                }
            };
        };

        $stage0 = Helper::createTestCase($modifierBrowser('Chrome', '55', [1 => 100, 2 => 200, 3 => 300]), true, '/tests.json');
        $stage1 = Helper::createTestCase($modifierBrowser('Chrome', '55', [1 => 250, 2 => 450, 3 => 650]), false, '/tests.json');
        $stage2 = Helper::createTestCase($modifierBrowser('Chrome', '53', [1 => 110, 2 => 210, 3 => 310]), false, '/tests.json');

        $response = Helper::get(BASE_URL . '/test/test-env-change-aggregation.json');
        $response = json_decode($response);

        $debug = [
            'stage0' => $stage0,
            'stage1' => $stage1,
            'stage2' => $stage2,
            'final' => $response,
        ];

        static::assertEquals(175, $response->entries[0]->totals[0]->metricValue, print_r($debug, true));
        static::assertEquals(2, $response->entries[0]->totals[0]->runCount);
        static::assertEquals(55, $response->entries[0]->totals[0]->browserVersion);

        static::assertEquals(110, $response->entries[0]->totals[1]->metricValue);
        static::assertEquals(1, $response->entries[0]->totals[1]->runCount);
        static::assertEquals(53, $response->entries[0]->totals[1]->browserVersion);

        return $response;
    }

    /**
     * sample
     * @depends testWhenTheEnvironmentIsChangedWillNotIncrementTheRevisionJustMergeTheValues
     */
    public function testSample($item)
    {
        $response = Helper::get(BASE_URL . '/test/' . $item->slug . '/totals/by-browser.json');
        $response = json_decode($response);

        static::assertEquals('Chrome (55)', $response[0]->totals[0]->browserName);
        static::assertEquals(175, $response[0]->totals[0]->metricValue);
        static::assertEquals(2, $response[0]->totals[0]->runCount);

        static::assertEquals('Chrome (53)', $response[0]->totals[1]->browserName);
        static::assertEquals(110, $response[0]->totals[1]->metricValue);
        static::assertEquals(1, $response[0]->totals[1]->runCount);
    }
}
