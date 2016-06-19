<?php
namespace JSB\Controllers;

use JSB\Exception;
use JSB\Store;
use JSB\Models\Revision;

class TestCase
{
    protected $store;

    /**
     * This requires a store that will return models.
     * @param Store $store This is a specific repository / gateway. In this implementation, Sparrow is used as the storage adapter.
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Return listing response or empty array if no elements are found.
     * @limits max 50 results, ordered by created_at date value
     * @return array
     */
    public function listing()
    {
        $items = $this->store->search();
        $listing = array_map(
            function (Revision $item) {
                return $item->toArray();
            },
            $items
        );
        return $listing;
    }

    /**
     * Adds a test case. If an existing test case exists (searching by slug)
     * then (if the found revision is the latest one) increment the revision
     * or only combine / recalculate the totals for the revision.
     *
     * @param Revision $input The input details, converted into a draft Revision.
     * @param string   $slug If sent, will try to update the test case that has this slug as the current revision slug.
     *
     * @return mixed
     * @throws Exception
     */
    public function store(Revision $input, $slug = '')
    {
        $checkUpdateSlugCollision = ($slug != '');
        if ($slug == '') {
            $slug = $input->slug;
        }

        $testCaseRevision = null;
        $getRevisionBySlug = function ($slug) {
            $listing = $this->store->search(['slug' => $slug]);
            $revision = count($listing) > 0 ? $listing[0] : null;
            return $revision;
        };

        $testCaseRevision = $getRevisionBySlug($slug);

        if (!is_null($testCaseRevision)) { /*  && !$testCaseRevision->isDraft() */
            try {
                if ($checkUpdateSlugCollision && $slug != $input->slug) {
                    $testCaseExistingRevision = $getRevisionBySlug($input->slug);
                    if (!is_null($testCaseExistingRevision) && !$testCaseExistingRevision->isDraft()) {
                        throw new Exception('There already is a test case with this slug [' . $input->slug . '].');
                    }
                }

                $isNewRevision = $testCaseRevision->mergeRevisionData($input);
                $revisionId = $testCaseRevision->revisionId;
                if ($isNewRevision) {
                    $revisionId = 0;
                    $testCaseRevision->revisionNumber++;
                    $this->store->createRevision($testCaseRevision->testCaseId, $testCaseRevision);
                } else {
                    $this->store->updateRevision($testCaseRevision);
                }

            } catch (\Exception $e) {
                throw (new Exception('Could not update the revision.'))
                    ->withDetails([
                        'reason' => $e->getMessage(),
                    ]);
            }
            if ($revisionId > 0) { // was updated, grab new instance
                $testCaseRevision = $getRevisionBySlug($input->slug);
            } else {
                // only just refreshed the totals, return the existing instance
                // bumpRevision will update the totals / other details
            }
        } else {
            $input->testCaseId = 0; // make sure to insert a proper root parent revision id
            $this->store->createTestCase($input);
            $testCaseRevision = $getRevisionBySlug($input->slug);
        }
        $response = $testCaseRevision->toArray();
        return $response;
    }

    public function find($slug, $revisionNumber = null)
    {
        $listing = $this->store->search(['slug' => $slug, 'revision' => $revisionNumber]);
        if (count($listing) > 0) {
            return $listing[0]->toArray();
        }
        throw new Exception('Not found.');
    }

    public function reportByBrowser($slug, $revisionNumber = null)
    {
        $item = $this->find($slug, $revisionNumber);
        return array_values(
            array_map(
                function ($entry) {
                    $totals = [];
                    foreach ($entry['totals'] as $metric) {
                        $key = $metric['browserName'] . ' (' . $metric['browserVersion'] . ')';
                        if (array_key_exists($key, $totals)) {
                            $totals[$key]['metricValue'] = $metric['metricValue'] * $metric['runCount'] + $totals[$key]['runCount'] * $totals[$key]['metricValue'];
                            $totals[$key]['runCount'] += $metric['runCount'];
                            $totals[$key]['metricValue'] = $totals[$key]['metricValue'] / $totals[$key]['runCount'];

                        } else {
                            $totals[$key] = [
                                'browserName' => $key,
                                'metricType' => $metric['metricType'],
                                'metricValue' => $metric['metricValue'],
                                'runCount' => $metric['runCount']
                            ];
                        }
                    }
                    return [
                        'title' => $entry['title'],
                        'totals' => array_values($totals)
                    ];
                },
                $item['entries']
            )
        );
    }

    public function getRevisionsForTestCase($slug)
    {
        $listing = $this->store->search(['slug' => $slug, 'revisions' => true]);
        return array_map(function ($item) {
            return $item->toArray();
        }, $listing);
    }

    public function addErrorLogEntry($data)
    {
        if (!is_array($data) || empty($data['msg']) || empty($data['lineNo']) || empty($data['colNo']) || empty($data['trace'])) {
            throw new Exception('Invalid entry: ' . var_export($data, true));
        }
        $data['url'] = array_key_exists('url', $data) ? $data['url'] : '';
        $this->store->logJavaScriptErrorEntry(
            $data['msg'],
            $data['url'],
            $data['lineNo'],
            $data['colNo'],
            $data['trace']
        );
    }
    public function getJavaScriptErrorEntries()
    {
        return $this->store->getJavaScriptErrorEntries();
    }
}
