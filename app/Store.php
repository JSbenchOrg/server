<?php
namespace JSB;

use JSB\Models\Entry;
use JSB\Models\Metric;
use JSB\Models\Revision;
use JSB\Models\Harness;

class Store
{
    protected $storage;

    public function __construct(\Sparrow $storage)
    {
        $this->storage = $storage;
    }

    public function search($options = [])
    {
        $entries = $this->storage
            ->from('testcases as t');

        $joinOptions = ['r.testcase_id' => 't.id', '(r.id=t.current_revision_id)' => '1'];

        // limit by slug. tests with it as the current slug have priority
        if (array_key_exists('slug', $options)) {
            if (array_key_exists('revisions', $options) && $options['revisions']) {
                unset($joinOptions['(r.id=t.current_revision_id)']);
                $entries->where(['t.slug' => $options['slug']]);

            } else {
                unset($joinOptions['(r.id=t.current_revision_id)']); // when searching for a slug, get latest first but allow older revisions

                $whereOptions = [
                    'r.slug' => $options['slug'],
//                    '|t.slug' => $options['slug'],
                ];

                if (array_key_exists('revision', $options) && (int) $options['revision'] > 0) {
                    $whereOptions['r.revision_number'] = (int) $options['revision'];
                }

                $entries
                    ->where($whereOptions)
                    ->limit(1);
            }

        } else if (array_key_exists('id', $options)) {
            $entries->where('t.id', $options['id']);
        } else {
            // get all, only the latest revisions for each test
            $entries->groupBy('t.id');
        }

        $entries
            ->join('revisions as r', $joinOptions)
            ->sortDesc('is_latest')
            ->sortDesc('r.revision_number')
            ->select([
                't.id',
                'r.slug',
                't.status',
                'r.revision_number',
                'r.id as revision_id',
                'r.parent_revision_id',
                'r.title',
                'r.description',
                'r.harness_html',
                'r.harness_set_up',
                'r.harness_tear_down',
                '(r.id = t.current_revision_id) as is_latest'
            ]);

        $rawRevisionItems = $entries->many();

        $revisionIds = array_map(function($item) {return $item['revision_id'];}, $rawRevisionItems);
        $entriesByRevision = $this->getTestEntriesByRevisionIds($revisionIds);

        $listing = array_map(function($item) use ($entriesByRevision) {
            $revisionEntries = array_key_exists($item['revision_id'], $entriesByRevision)
                ? $entriesByRevision[$item['revision_id']]
                : [];
            return (new Revision(
                (int) $item['id'],
                (int) $item['revision_id'],
                (int) $item['revision_number'],
                $item['title'],
                $item['slug'],
                $item['status'],
                $item['description'],
                new Harness(
                    $item['harness_html'],
                    $item['harness_set_up'],
                    $item['harness_tear_down']
                ),
                array_map(function($entry) {
                    return new Entry(
                        (int) $entry->id,
                        $entry->title,
                        $entry->code,
                        array_map(function($result) {
                            return new Metric(
                                (int) $result->id,
                                $result->metric_type,
                                $result->metric_value,
                                $result->run_count,
                                $result->browser->name,
                                $result->browser->version,
                                $result->os->architecture,
                                $result->os->family,
                                $result->os->version
                            );
                        }, $entry->results)
                    );
                }, $revisionEntries)
            ))->setIsDraft($item['is_latest'] !== '1');
        }, $rawRevisionItems);

        return $listing;
    }

    public function updateRevision(Revision $model)
    {
        $sql = $this->storage
            ->from('revisions')
            ->where('id', $model->getRevisionId())
            ->update([
                'title' => $model->title,
                'slug' => $model->slug,
                'description' => $model->description,
                'revision_number' => $model->revisionNumber,
                'harness_html' => $model->harness->html,
                'harness_set_up' => $model->harness->setUp,
                'harness_tear_down' => $model->harness->tearDown,
            ])
            ->sql();
        $this->storage->execute($sql);

        $removeIds = array_filter(array_map(function(Entry $entry) {
            return $entry->isObsolete() ? $entry->id : null;
        }, $model->entries));

        // now, update totals
        $this->setRevisionEntries($model->getRevisionId(), $model->entries, $removeIds);
    }

    public function createRevision($testCaseId, Revision $model)
    {
        $revisionId = $this->insertRevision(
            $testCaseId,
            $model->getRevisionId(),
            $model->title,
            $model->slug,
            $model->description,
            $model->harness->html,
            $model->harness->setUp,
            $model->harness->tearDown,
            $model->revisionNumber
        );

        $sql = $this->storage
            ->from('testcases')
            ->where('id', $testCaseId)
            ->update([
                'current_revision_id' => $revisionId,
                'slug' => $model->slug,
                'status' => $model->status,
            ])
            ->sql();

        $this->storage->execute($sql);

        foreach ($model->entries as $entry) {
            $entryId = $this->insertEntry(
                $revisionId,
                $entry->title,
                $entry->code
            );

            foreach ($entry->totals as $total) {
                $this->insertTotalItem(
                    $entryId,
                    $total->metricType,
                    $total->metricValue,
                    $total->browserName,
                    $total->browserVersion,
                    $total->runCount,
                    $total->osArchitecture,
                    $total->osFamily,
                    $total->osVersion
                );
            }
        }
    }

    public function createTestCase(Revision $model)
    {
        $testCaseId = $this->insertTestCase($model->slug, $model->status);
        $this->createRevision($testCaseId, $model);
        return $testCaseId;
    }

    public function insertTestCase($slug, $status)
    {
        $sql = $this->storage
            ->from('testcases')
            ->insert([
                'slug' => $slug,
                'status' => $status
            ])->sql();

        $this->storage->execute($sql);
        return (int) $this->storage->insert_id;
    }

    public function insertRevision(
        $testCaseId,
        $parentRevisionId,
        $title,
        $slug,
        $description,
        $harnessHtml,
        $harnessSetUp,
        $harnessTearDown,
        $revisionNumber = 1
    ) {
        $insertStatement = $this->storage
            ->from('revisions')
            ->insert([
                'testcase_id' => $testCaseId,
                'parent_revision_id' => $parentRevisionId,
                'revision_number' => $revisionNumber,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'harness_html' => $harnessHtml,
                'harness_set_up' => $harnessSetUp,
                'harness_tear_down' => $harnessTearDown
            ])
            ->sql();
        $this->storage->execute($insertStatement);
        return (int) $this->storage->insert_id;
    }

    public function insertEntry($revisionId, $title, $code)
    {
        $insertStatement = $this->storage
            ->from('entries')
            ->insert([
                'revision_id' => $revisionId,
                'title' => $title,
                'code' => $code,
            ])
            ->sql();
        $this->storage->execute($insertStatement);
        return (int) $this->storage->insert_id;
    }

    public function insertTotalItem(
        $entryId,
        $type,
        $value,
        $browserName = '',
        $browserVersion = '',
        $runCount = 1,
        $osArchitecture = '',
        $osFamily = '',
        $osVersion = ''
    ) {
        $type = ($type != 'opsPerSec') ? 'custom' : $type;
        $browserEntryId = $this->getBrowserEntryId($browserName, $browserVersion);
        $osEntryId = $this->getOsEntryId($osArchitecture, $osFamily, $osVersion);

        $statement = $this->storage
            ->from('totals')
            ->insert([
                'entry_id' => $entryId,
                'browser_entry_id' => $browserEntryId,
                'os_entry_id' => $osEntryId,
                'metric_type' => $type,
                'metric_value' => $value,
                'run_count' => $runCount
            ])
            ->sql();
        $this->storage->execute($statement);
    }

    /**
     * @param $browserName
     * @param $browserVersion
     *
     * @return int
     */
    protected function getBrowserEntryId($browserName, $browserVersion)
    {
        $existingBrowserEntry = $this->storage
            ->from('browsers')
            ->where('name', $browserName)
            ->where('version', $browserVersion)
            ->select(['id'])
            ->sql();

        $existingBrowserEntry = $this->storage
            ->sql($existingBrowserEntry)
            ->execute();

        $existingBrowserEntry = $existingBrowserEntry->fetch();
        if ($existingBrowserEntry) {
            $browserEntryId = $existingBrowserEntry['id'];
            return $browserEntryId;
        } else {
            $sql = $this->storage
                ->from('browsers')
                ->insert([
                    'name' => $browserName,
                    'version' => $browserVersion,
                ])
                ->sql();
            $this->storage->sql($sql)->execute();

            $lastId = (int) $this->storage->insert_id;
            return $lastId;
        }
    }

    protected function getOsEntryId($osArchitecture, $osFamily, $osVersion)
    {
        $existingOsEntry = $this->storage
            ->from('os')
            ->where('architecture', $osArchitecture)
            ->where('family', $osFamily)
            ->where('version', $osVersion)
            ->select(['id'])
            ->one();

        if ($existingOsEntry) {
            $osEntryId = $existingOsEntry['id'];
            return $osEntryId;
        } else {
            $sql = $this->storage
                ->from('os')
                ->insert([
                    'architecture' => $osArchitecture,
                    'family' => $osFamily,
                    'version' => $osVersion
                ])
                ->sql();

            $this->storage->sql($sql)->execute();
            return (int) $this->storage->insert_id;
        }
    }

    public function getTestEntriesByRevisionIds($ids)
    {
        if (empty($ids)) {
            return [];
        }

        $sql = '
            SELECT DISTINCT
                e.id,
                r.testcase_id,
                e.revision_id,
                e.title,
                e.code,
                totals.id as totals_id,
                totals.metric_type,
                totals.metric_value,
                totals.run_count,
                browsers.name as browser_name,
                browsers.version as browser_version,
                os.architecture as os_architecture,
                os.family as os_family,
                os.version as os_version
            FROM entries AS e
            INNER JOIN revisions as r on r.id = e.revision_id
            INNER JOIN totals ON totals.entry_id = e.id
            inner join browsers on browsers.id = totals.browser_entry_id
            inner join os on os.id = totals.os_entry_id
            WHERE e.revision_id in (' . implode(',', $ids) . ');
        ';

        $statement = $this->storage->sql($sql)->execute();
        $entries = $statement->fetchAll();

        $response = [];
        foreach ($entries as $entry) {
            $revisionId = $entry['revision_id'];
            $entryId = $entry['id'];
            if (!array_key_exists($revisionId, $response)) {
                $response[$revisionId] = [];
            }
            if (!array_key_exists($entryId, $response[$revisionId])) {
                $response[$revisionId][$entryId] = (object) [
                    'id' => $entry['id'],
                    'title' => $entry['title'],
                    'code' => $entry['code'],
                    'results' => [],
                ];
            }
            $response[$revisionId][$entryId]->results[] = (object) [
                'id' => $entry['totals_id'],
                'metric_type' => $entry['metric_type'],
                'metric_value' => $entry['metric_value'],
                'run_count' => $entry['run_count'],
                'browser' => (object) [
                    'name' => $entry['browser_name'],
                    'version' => $entry['browser_version'],
                ],
                'os' => (object) [
                    'architecture' => $entry['os_architecture'],
                    'family' => $entry['os_family'],
                    'version' => $entry['os_version'],
                ]
            ];
        }

        return $response;
    }

    public function getEntryTotals($entryId)
    {
        $selectStatement = $this->storage
            ->from('totals')
            ->join('browsers', ['browsers.id' => 'totals.browser_entry_id'])
            ->join('os', ['os.id' => 'totals.os_entry_id'])
            ->where('entry_id', $entryId)
            ->select([
                'totals.id as id',
                'metric_type',
                'metric_value',
                'run_count',
                'browsers.name as browser_name',
                'browsers.version as browser_version',
                'os.architecture as os_architecture',
                'os.family as os_family',
                'os.version as os_version',
            ]);

        $stmt = $selectStatement->execute();
        $data = $stmt->fetchAll();
        $response = [];

        foreach ($data as $result) {
            $response[] = new Metric(
                (int) $result['id'],
                $result['metric_type'],
                $result['metric_value'],
                $result['run_count'],
                $result['browser_name'],
                $result['browser_version'],
                $result['os_architecture'],
                $result['os_family'],
                $result['os_version']
            );
        }

        return $response;
    }

    /**
     * @param int $revisionId
     * @param Entry[] $entries
     * @param array $obsoleteEntryIds
     */
    public function setRevisionEntries($revisionId, $entries, $obsoleteEntryIds = [])
    {
        $toInsert = [];

        foreach ($entries as $entry) {
            $entryId = $entry->id;
            if ($entryId > 0) {
                $this->storage
                    ->from('entries')
                    ->where('id', $entryId)
                    ->update(['title' => $entry->title])
                    ->execute();
                $this->setEntryTotals($entry);

            } else if (!is_null($entryId)) { // insert if not marked for deletion
                $toInsert[] = $entry;
            }
        }

        if (count($toInsert) > 0) {
            $this->insertEntries($revisionId, $toInsert);
        }
        if (count($obsoleteEntryIds) > 0) {
            $this->deleteEntriesByIds($obsoleteEntryIds);
        }
    }

    public function deleteEntriesByIds($ids = [])
    {
        if (count($ids) > 0) {
            $this->storage->from('entries')->where('id @', $ids)->delete()->execute();
            $this->storage->from('totals')->where('entry_id @', $ids)->delete()->execute();
        }
    }

    public function insertEntries($revisionId, $entries)
    {
        $insertedEntries = [];
        /** @var Entry $entry */
        foreach ($entries as $entry) {
            $entryId = $this->insertEntry($revisionId, $entry->title, $entry->code);
            /** @var Metric $total */
            foreach ($entry->totals as $total) {
                $this->insertTotalItem(
                    $entryId,
                    $total->metricType,
                    $total->metricValue,
                    $total->browserName,
                    $total->browserVersion,
                    $total->runCount,
                    $total->osArchitecture,
                    $total->osFamily,
                    $total->osVersion
                );
            }
            $insertedEntries[] = $entryId;
        }
    }

    /**
     * @param Entry $entry
     */
    protected function setEntryTotals($entry)
    {
        /** @var Entry $currentEntry */
        $entryId = $entry->id;
        $totals = $entry->totals; // new values

        $currentTotalsIds = [];
        $currentTotals = $this->getEntryTotals($entryId);
        $currentEntry = new Entry($entryId, $entry->title, $entry->code, $currentTotals);

        foreach ($currentEntry->totals as $currentTotal) {
            $currentTotalsIds[$currentTotal->id] = $currentTotal->getChecksum();
        }

        $matchingIds = [];
        foreach ($totals as $metric) {
            $existingIdentifierId = array_search($metric->getChecksum(), $currentTotalsIds);
            unset($currentTotalsIds[$existingIdentifierId]); // allow multiple samples

            if ($existingIdentifierId > 0) {
                $matchingIds[] = $existingIdentifierId;
                $this->updateTotalResults(
                    $existingIdentifierId,
                    $metric->metricValue,
                    $metric->runCount
                );

            } else { // insert new total
                $this->insertTotalItem(
                    $entryId,
                    $metric->metricType,
                    $metric->metricValue,
                    $metric->browserName,
                    $metric->browserVersion,
                    $metric->runCount,
                    $metric->osArchitecture,
                    $metric->osFamily,
                    $metric->osVersion
                );
            }
        }

        $toDeleteIds = array_diff(array_keys($currentTotalsIds), $matchingIds);

        if (count($toDeleteIds) > 0) {
            $this->deleteTotalsByIds($toDeleteIds);
        }
    }

    public function updateTotalResults($id, $inputMetricValue, $inputRunCount)
    {
        $sql = $this->storage
            ->from('totals')
            ->where('id', $id)
            ->update([
                'metric_value' => $inputMetricValue,
                'run_count' => $inputRunCount
            ])
            ->sql();
        $this->storage->sql($sql)->execute();
    }

    public function deleteTotalsByIds($toDeleteIds = [])
    {
        if (count($toDeleteIds) == 0) {
            return false;
        }
        $this->storage->from('totals')->where('id @', $toDeleteIds)->delete()->execute();
    }

    public function logJavaScriptErrorEntry($message, $url, $lineNumber, $columnNumber, $trace)
    {
        $this->storage
            ->from('errors')
            ->insert([
                'msg' => $message,
                'url' => $url,
                'line_number' => $lineNumber,
                'column_number' => $columnNumber,
                'trace' => $trace,
            ])
            ->sql();
    }

    public function getJavaScriptErrorEntries()
    {
        return $statement = $this->storage
            ->from('errors')
            ->select(['msg', 'url', 'line_number', 'column_number', 'trace', 'created_at'])
            ->many();
    }
}
