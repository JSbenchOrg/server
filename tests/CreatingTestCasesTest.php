<?php
namespace JSBTests;

/**
 * Route: POST {BASE_URL}/testsa.json
 */
class CreatingTestCasesTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Will throw an exception when no payload is sent.
     */
    public function testWillThrowAnExceptionWhenNoPayloadIsSent()
    {
        $responseBody = Helper::post(BASE_URL . '/tests.json', null);
        Helper::isErrorResponse($responseBody, 'Invalid input.', ['Invalid structure.']);
    }

    /**
     * Will throw an exception when no object payload is sent.
     */
    public function testWillThrowAnExceptionWhenNoObjectPayloadIsSent()
    {
        $responseBody = Helper::post(BASE_URL . '/tests.json', 'abc');
        Helper::isErrorResponse($responseBody, 'Invalid input.', ['Invalid structure.']);
    }

    /**
     * Will throw an exception when no slug is sent in the payload body.
     */
    public function testWillThrowAnExceptionWhenNoSlugIsSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../extra/testcases/request.three-tests-with-setUp.json'));
        unset($contents->slug);

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', ['The slug is mandatory and should not be empty.']);
    }

    /**
     * Will throw an exception when no entries are sent in the payload body.
     */
    public function testWillThrowAnExceptionWhenNoEntriesAreSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../extra/testcases/request.three-tests-with-setUp.json'));
        unset($contents->entries);

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', ['At least two entries should be sent.']);
    }

    /**
     * Will throw an exception when less than two entries are sent in the payload body.
     */
    public function testWillThrowAnExceptionWhenLessThanTwoEntriesAreSentInThePayloadBody()
    {
        $contents = json_decode(file_get_contents(__DIR__ . '/../extra/testcases/request.three-tests-with-setUp.json'));
        $contents->entries = [end($contents->entries)];

        $responseBody = Helper::post(BASE_URL . '/tests.json', $contents);
        Helper::isErrorResponse($responseBody, 'Invalid input.', ['At least two entries should be sent.']);
    }

    /**
     * Will throw exception the slug if longer than 255 chars.
     */
    public function testWillTruncateTheDescriptionIfLongerThan255Chars()
    {
        $sampleData = Helper::generateRandomString(270);
        $modifier = function ($contents) use ($sampleData) {
            $contents->slug = $sampleData;
        };
        $item = Helper::createTestCase($modifier);
        Helper::isErrorResponse(json_encode($item), 'Invalid input.', ['The slug shouldn\'t be longer than 255 chars.']);
    }

    /**
     * When more than one error are found then return them all in the error data property.
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
            'The slug shouldn\'t be longer than 255 chars.',
            'At least two entries should be sent.'
        ];
        Helper::isErrorResponse(json_encode($item), 'Invalid input.', $expectedData);
    }

    /**
     * Will default the title with the slug value if the title is not sent in the payload.
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
     */
    public function testWhenDuplicateCodeIsSentInTheEntriesForAtLeastTwoEntriesThenThrowException()
    {
        $modifier = function ($contents) {
            $contents->entries->{1}->code = 'samplecode1';
            $contents->entries->{2}->code = 'samplecode1';
        };
        $shaHash = sha1('samplecode1');
        $item = Helper::createTestCase($modifier);
        Helper::isErrorResponse(json_encode($item), 'Invalid input.', ['Duplicate entry code found [sha1: ' . $shaHash . ']. Only send unique values.']);
    }

    /**
     * When a different slug is sent, insert a new testcase.
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
