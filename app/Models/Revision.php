<?php
namespace JSB\Models;

use JSB\Exception;

class Revision
{
    const SETTING_SLUG_MAX_LENGTH = 255;

    public $testCaseId;
    public $revisionId;
    public $revisionNumber;
    public $title;
    public $slug;
    public $status;
    public $description;
    public $harness;
    public $entries;

    private $draftFlag;

    public function __construct($testCaseId, $revisionId, $revisionNumber, $title, $slug, $status, $description, $harness, $entries = [])
    {
        $this->testCaseId = $testCaseId;
        $this->revisionId = $revisionId;
        $this->revisionNumber = $revisionNumber;
        $this->title = $title;
        $this->slug = $slug;
        $this->status = $status;
        $this->description = $description;
        $this->harness = $harness;
        $this->entries = $entries;
    }

    public static function fromStorage($data)
    {

    }

    public static function fromData($data)
    {
        $data = static::validate($data);

        $instance = new static(
            $data->id,
            0, // new revision
            $data->revisionNumber,
            $data->title,
            $data->slug,
            $data->status,
            $data->description,
            new Harness(
                $data->harness->html,
                $data->harness->setUp,
                $data->harness->tearDown
            ),
            $entries = array_map(function($rawEntry) use ($data) {
                $totals = [];
                if (isset($rawEntry->results) && is_object($rawEntry->results)) {
                    $totals[] = new Metric(
                        0,
                        'opsPerSec',
                        $rawEntry->results->opsPerSec,
                        1,
                        $data->env->browserName,
                        $data->env->browserVersion,
                        $data->env->os->architecture,
                        $data->env->os->family,
                        $data->env->os->version
                    );
                }

                return new Entry(
                    0,
                    $rawEntry->title,
                    $rawEntry->code,
                    $totals
                );
            }, $data->entries)
        );

        return $instance;
    }

    public static function validate($data)
    {
        $errors = [];

        if (!is_object($data)) {
            $errors[] = 'Invalid structure.';
        } else {
            // metadata
            $data->id = !empty($data->id) ? (int) $data->id : 0;
            $data->revisionNumber = !empty($data->revisionNumber) ? (int) $data->revisionNumber : 1;
            $data->isDraft = !empty($data->isDraft) ? (bool) $data->isDraft : false;
            $data->title = !empty($data->title) ? trim($data->title) : '';
            $data->slug = !empty($data->slug) ? trim($data->slug) : '';
            $data->description = !empty($data->description) ? trim($data->description) : '';
            $data->status = empty($data->status) || !in_array($data->status, ['public', 'private']) ? 'private' : $data->status;

            if (empty($data->slug)) {
                $errors[] = 'The slug is mandatory and should not be empty.';
            }

            if (empty($data->title)) {
                $data->title = $data->slug;
            }

            if (strlen($data->slug) > self::SETTING_SLUG_MAX_LENGTH) {
                $errors[] = 'The slug shouldn\'t be longer than ' . self::SETTING_SLUG_MAX_LENGTH . ' chars.';
            }

            // entries
            $data->entries = !empty($data->entries) ? (array) $data->entries : [];
            if (count($data->entries) < 2) {
                $errors[] = 'At least two entries should be sent.';
            }

            // check entry code collision
            $entriesCodeHashes = [];
            foreach ($data->entries as $entry) {
                $hash = sha1($entry->code);
                if (in_array($hash, $entriesCodeHashes)) {
                    $errors[] = 'Duplicate entry code found [sha1: ' . $hash . ']. Only send unique values.';
                }
                $entriesCodeHashes[] = $hash;
            }

            // harness
            if (!isset($data->harness) || !is_object($data->harness) || empty($data->harness)) {
                $data->harness = (object) [
                    'html' => '',
                    'setUp' => '',
                    'tearDown' => '',
                ];
            } else {
                $data->harness->html = !empty($data->harness->html) ? $data->harness->html : '';
                $data->harness->setUp = !empty($data->harness->setUp) ? $data->harness->setUp : '';
                $data->harness->tearDown = !empty($data->harness->tearDown) ? $data->harness->tearDown : '';
            }

            $data->env = isset($data->env) && is_object($data->env) ? $data->env : (object) [];
            $data->env->os = isset($data->env->os) && is_object($data->env->os) ? $data->env->os : (object) [];
            $data->env->browserName = !empty($data->env->browserName) ? $data->env->browserName : '';
            $data->env->browserVersion = !empty($data->env->browserVersion) ? $data->env->browserVersion : '';
            $data->env->os->architecture = !empty($data->env->os->architecture) ? $data->env->os->architecture : '';
            $data->env->os->family = !empty($data->env->os->family) ? $data->env->os->family : '';
            $data->env->os->version = !empty($data->env->os->version) ? $data->env->os->version : '';
        }

        if (count($errors) == 0) {
            return $data;
        }
        throw (new Exception('Invalid input.', Exception::INVALID_REQUEST_BODY))->withDetails($errors);
    }

    public function isDraft() {
        return $this->draftFlag;
    }

    public function setIsDraft($flag)
    {
        $this->draftFlag = $flag;
        return $this;
    }

    public function toArray()
    {
        $incrementId = function() {
            static $count = 1;
            return $count++;
        };

        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'description' => $this->description,
            'revisionNumber' => $this->revisionNumber,
            'harness' => $this->harness,
            'entries' => array_values(array_map(function(Entry $entry) use ($incrementId) {
                return [
                    'id' => $incrementId(),
                    'title' => $entry->title,
                    'code' => $entry->code,
                    'totals' => array_map(function(Metric $item) {
                        return [
                            'metricType' => $item->metricType,
                            'metricValue' => $item->metricValue,
                            'runCount' => $item->runCount,
                            'browserName' => $item->browserName,
                            'browserVersion' => $item->browserVersion,
                            'osArchitecture' => $item->osArchitecture,
                            'osFamily' => $item->osFamily,
                            'osVersion' => $item->osVersion,
                        ];
                    }, $entry->totals),
                ];
            }, $this->entries)),
        ];
    }

    /**
     * @param Revision $input
     * @return array
     */
    public function mergeRevisionData(Revision $input)
    {
        $newRevision = false; // change revision only on slug change or on description change
        $newEntries = $entryCodes = [];

        if ($this->title != $input->title) {
            $this->title = $input->title;
        }
        if ($this->slug != $input->slug) {
            $this->slug = $input->slug;
            $newRevision = true;
        }
        if ($this->status != $input->status) {
            $this->status = $input->status;
        }
        if ($this->description != $input->description) {
            $this->description = $input->description;
            $newRevision = true;
        }
        if ($this->harness->html != $input->harness->html) {
            $this->harness->html = $input->harness->html;
            $newRevision = true;
        }
        if ($this->harness->setUp != $input->harness->setUp) {
            $this->harness->setUp = $input->harness->setUp;
            $newRevision = true;
        }
        if ($this->harness->tearDown != $input->harness->tearDown) {
            $this->harness->tearDown = $input->harness->tearDown;
            $newRevision = true;
        }

        foreach ($input->entries as $entry) {
            $entryCodes[] = sha1($entry->code);
        }

        // merge entries / totals
        /** @var Entry $entry */
        foreach ($this->entries as $entry) {
            $key = sha1($entry->code);
            if (in_array($key, $entryCodes)) {
                $inputEntry = array_reduce($input->entries, function($prev, $item) use ($key) {
                    if (sha1($item->code) === $key) {
                        $prev = $item;
                    }
                    return $prev;
                });
                $entry->inheritTotals($inputEntry->totals); // will use the entry id set above to update
                $newEntries[$key] = $entry;
            } else {
                $newRevision = true;
                $entry->setObsolete(); // marked for delete (or insert / inherit)
            }
        }
        foreach ($input->entries as $entry) {
            $key = sha1($entry->code);
            if (!array_key_exists($key, $newEntries)) {
                $newRevision = true;
                $entry->id = 0; // mark for an insert
                $newEntries[$key] = $entry; // append new entries
            }
        }

        $this->entries = array_values($newEntries);
        return $newRevision;
    }

    public function getRevisionId()
    {
        return $this->revisionId;
    }
}
