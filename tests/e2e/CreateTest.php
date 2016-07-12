<?php
namespace JSBTests;
use JSB\Exception;

/**
 * Route: POST {BASE_URL}/testsa.json
 */
class CreateTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Will throw an exception when no payload is sent.
     * @group initial
     * @group database
     */
    public function testWillThrowAnExceptionWhenNoPayloadIsSent()
    {
        $responseBody = Helper::post(BASE_URL . '/tests.json', null);
        Helper::isErrorResponse($responseBody, 'Invalid input.', [
            (object) [
                'reason' => 'Invalid structure.',
                'code' => Exception::INVALID_STRUCTURE
            ]
        ]);
    }

    /**
     * Will throw an exception when no object payload is sent.
     * @group initial
     * @group database
     */
    public function testWillThrowAnExceptionWhenNoObjectPayloadIsSent()
    {
        $responseBody = Helper::post(BASE_URL . '/tests.json', 'abc');
        Helper::isErrorResponse($responseBody, 'Invalid input.', [
            (object) [
                'reason' => 'Invalid structure.',
                'code' => Exception::INVALID_STRUCTURE
            ]
        ]);
    }

    /**
     * Will throw an exception when no slug is sent in the payload body.
     * @group initial
     * @group database
     */
    public function testWillThrowAnExceptionWhenNoSlugIsSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../../extra/testcases/request.three-tests-with-setUp.json'));
        unset($contents->slug);

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', [
            (object) [
                'reason' => 'The slug is mandatory and should not be empty.',
                'code' => Exception::NO_SLUG
            ]
        ]);
    }

    /**
     * Will throw an exception when no entries are sent in the payload body.
     * @group initial
     * @group database
     */
    public function testWillThrowAnExceptionWhenNoEntriesAreSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../../extra/testcases/request.three-tests-with-setUp.json'));
        unset($contents->entries);

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', [
            (object) [
                'reason' => 'At least two entries should be sent.',
                'code' => Exception::ENTRY_COUNT,
            ]
        ]);
    }

    /**
     * Will throw an exception when less than two entries are sent in the payload body.
     * @group initial
     * @group database
     */
    public function testWillThrowAnExceptionWhenLessThanTwoEntriesAreSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../../extra/testcases/request.three-tests-with-setUp.json'));
        $contents->entries = [end($contents->entries)];

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', [
            (object) [
                'reason' => 'At least two entries should be sent.',
                'code' => Exception::ENTRY_COUNT,
            ]
        ]);
    }

    /**
     * Will throw exception the slug if longer than 255 chars.
     * @group initial
     * @group database
     */
    public function testWillTruncateTheDescriptionIfLongerThan255Chars()
    {
        $sampleData = Helper::generateRandomString(270);
        $modifier = function ($contents) use ($sampleData) {
            $contents->slug = $sampleData;
        };
        $item = Helper::createTestCase($modifier);
        Helper::isErrorResponse(json_encode($item), 'Invalid input.', [
            (object) [
                'reason' => 'The slug shouldn\'t be longer than 255 chars.',
                'code' => Exception::SLUG_LENGTH_EXCEEDED,
            ]
        ]);
    }

    /**
     * When more than one error are found then return them all in the error data property.
     * @group initial
     * @group database
     */
    public function testWhenMoreThanOneErrorAreFoundThenReturnThemAllInTheErrorDataProperty()
    {
        $sampleData = Helper::generateRandomString(270);
        $modifier = function ($contents) use ($sampleData) {
            $contents->slug = $sampleData;
            $contents->entries = [];
        };
        $item = Helper::createTestCase($modifier);

        $expectedData = [
            (object) [
                'reason' => 'The slug shouldn\'t be longer than 255 chars.',
                'code' => Exception::SLUG_LENGTH_EXCEEDED
            ],
            (object) [
                'reason' => 'At least two entries should be sent.',
                'code' => Exception::ENTRY_COUNT
            ],

        ];
        Helper::isErrorResponse(json_encode($item), 'Invalid input.', $expectedData);
    }

    /**
     * Will default the title with the slug value if the title is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheTitleWithTheSlugValueIfTheTitleIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->title);
        };
        $item = Helper::createTestCase($modifier);

        static::assertTrue(is_object($item));
        static::assertTrue(isset($item->title));
        static::assertTrue(isset($item->slug));
        static::assertTrue(!empty($item->slug));
        static::assertEquals($item->slug, $item->title);
    }

    /**
     * Will set the title with the value sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillSetTheTitleWithTheValueSentInThePayload()
    {
        $sampleData = uniqid('Hakuna matata');
        $modifier = function ($contents) use ($sampleData) {
            $contents->title = $sampleData;
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals($sampleData, $item->title);
    }

    /**
     * Will truncate the title if longer than 255 chars.
     * @group initial
     * @group database
     */
    public function testWillTruncateTheTitleIfLongerThan255Chars()
    {
        $sampleData = Helper::generateRandomString(270);
        $modifier = function ($contents) use ($sampleData) {
            $contents->title = $sampleData;
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals(substr($sampleData, 0, 255), $item->title);
    }

    /**
     * Will default the description as empty if the description is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheDescriptionAsEmptyIfTheDescriptionIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->description);
        };
        $item = Helper::createTestCase($modifier);

        static::assertTrue(is_object($item));
        static::assertTrue(isset($item->description));
        static::assertEquals('', $item->description);
    }

    /**
     * Will truncate the description if longer than 1000 chars.
     * @group initial
     * @group database
     */
    public function testWillTruncateTheDescriptionIfLongerThan1000Chars()
    {
        $sampleData = Helper::generateRandomString(1005);
        $modifier = function ($contents) use ($sampleData) {
            $contents->description = $sampleData;
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals(substr($sampleData, 0, 1000), $item->description);
    }

    /**
     * Will default the harness html as empty if it is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheHarnessHtmlAsEmptyIfItIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->harness->html);
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('', $item->harness->html);
    }

    /**
     * Will default the harness setUp as empty if it is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheHarnessSetUpAsEmptyIfItIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->harness->setUp);
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('', $item->harness->setUp);
    }

    /**
     * Will default the harness tearDown as empty if it is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheHarnessTearDownAsEmptyIfItIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->harness->tearDown);
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('', $item->harness->tearDown);
    }

    /**
     * Will default the status to private if it is not sent in the payload.
     * @group initial
     * @group database
     */
    public function testWillDefaultTheStatusToPrivateIfItIsNotSentInThePayload()
    {
        $modifier = function ($contents) {
            unset($contents->status);
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('private', $item->status);
    }

    /**
     * Will set the status to public if it was sent as public.
     * @group initial
     * @group database
     */
    public function testWillSetTheStatusToPublicIfItWasSentAsPublic()
    {
        $modifier = function ($contents) {
            $contents->status = 'public';
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('public', $item->status);
    }

    /**
     * Will set the status to private if it was sent as private.
     * @group initial
     * @group database
     */
    public function testWillSetTheStatusToPrivateIfItWasSentAsPrivate()
    {
        $modifier = function ($contents) {
            $contents->status = 'private';
        };
        $item = Helper::createTestCase($modifier);
        static::assertEquals('private', $item->status);
    }

    /**
     * When duplicate code is sent in the entries for at least two entries then throw exception.
     * @group initial
     * @group database
     */
    public function testWhenDuplicateCodeIsSentInTheEntriesForAtLeastTwoEntriesThenThrowException()
    {
        $modifier = function ($contents) {
            $contents->entries->{1}->code = 'samplecode1';
            $contents->entries->{2}->code = 'samplecode1';
        };
        $shaHash = sha1('samplecode1');
        $item = Helper::createTestCase($modifier);

        Helper::isErrorResponse(json_encode($item), 'Invalid input.', [
            (object) [
                'reason' => 'Duplicate entry code found [sha1: ' . $shaHash . ']. Only send unique values.',
                'code' => Exception::DUPLICATE_CODE_ENTRY,
            ]
        ]);
    }

    /**
     * When a different slug is sent, insert a new testcase.
     * @group initial
     * @group database
     */
    public function testWhenADifferentSlugIsSentInsertANewTestcase()
    {
        $modifier1 = function ($contents) {
            $contents->slug = 'slug-1';
        };
        $modifier2 = function ($contents) {
            $contents->slug = 'slug-2';
        };

        Helper::createTestCase($modifier1);
        Helper::createTestCase($modifier2, false);

        $database = Helper::getConnection();
        $rawEntries = $database->from('testcases')->select()->execute()->fetchAll();
        static::assertEquals(2, count($rawEntries));
    }
}
