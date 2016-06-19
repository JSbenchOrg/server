<?php
namespace JSBTests;

/**
 * Route: GET {BASE_URL}/test/{slug}/revisions.json
 */
class GettingTheRevisionsTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Get the revision list
     */
    public function testGetTheRevisionList()
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

        $response = Helper::get(BASE_URL . '/test/' . $slug . '/revisions.json');
        $response = json_decode($response);

        static::assertTrue(is_array($response), print_r(Helper::getHeaders(), true));
        static::assertEquals(4, count($response));

        $revisionCount = array_map(function ($item) {
            return $item->revisionNumber;
        }, $response);

        sort($revisionCount);
        static::assertEquals([1,2,3,4], $revisionCount);

        $response = Helper::get(BASE_URL . '/test/' . $slug . '/2.json');
        $response = json_decode($response);
        static::assertEquals(2, $response->revisionNumber);
    }
}
