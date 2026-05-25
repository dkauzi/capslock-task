<?php

declare(strict_types=1);

namespace Tests\Api\MediaBuyers;

use Tests\ApiTester;
use Tests\Factory\MediaBuyerFactory;
use Codeception\Example;

/**
 * POST /api/mediabuyers, acceptance criteria P1..P11.
 *
 * Negative paths are driven from @dataProvider methods so that a new
 * boundary case is one array row, not a new test method. Each row appears as
 * its own line in the Allure / Qase report.
 */
final class CreateMediaBuyerCest
{
    /**
     * Covers P1.
     */
    public function validRequestReturns200AndMatchesSchema(ApiTester $I): void
    {
        $payload = MediaBuyerFactory::valid()->build();

        $I->createMediaBuyer($payload);

        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'application/json');
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonSchema('post-media-buyer-schema.json');
    }

    /**
     * Covers P2 and P3: id is server-generated, request fields are echoed back.
     */
    public function responseEchoesRequestFieldsAndServerAssignsId(ApiTester $I): void
    {
        $I->skipIfBackendIsMock('response echoing is a real-backend behaviour');
        $payload = MediaBuyerFactory::valid()->build();

        $I->createMediaBuyer($payload);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'data' => [
                'mbId'        => $payload['mbId'],
                'initials'    => $payload['initials'],
                'name'        => $payload['name'],
                'email'       => $payload['email'],
                'slackUserId' => $payload['slackUserId'],
            ],
        ]);

        $id = $I->grabCreatedId();
        $I->assertGreaterThan(0, $id, 'Server must assign a positive integer id.');
    }

    /**
     * Covers P4: boolean coerced to integer in the response.
     *
     * @dataProvider activeBooleans
     */
    public function activeBooleanCoercedToInteger(ApiTester $I, Example $row): void
    {
        $I->skipIfBackendIsMock('boolean→integer coercion is a real-backend behaviour');
        $payload = MediaBuyerFactory::valid()
            ->with(['active' => $row['input']])
            ->build();

        $I->createMediaBuyer($payload);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['data' => ['active' => $row['expected']]]);
    }

    protected function activeBooleans(): array
    {
        return [
            ['input' => true,  'expected' => 1],
            ['input' => false, 'expected' => 0],
        ];
    }

    /**
     * Covers P5: missing required field returns 400 with the field named.
     *
     * @dataProvider missingRequiredFields
     */
    public function missingRequiredFieldReturns400(ApiTester $I, Example $row): void
    {
        $payload = MediaBuyerFactory::valid()->without($row['field'])->build();

        $I->createMediaBuyer($payload);

        $I->seeResponseCodeIs(400);
        $I->seeResponseMatchesJsonSchema('error-schema.json');

        // Real backend echoes the missing field name; the contract mock
        // returns a generic detail string and cannot satisfy this assertion.
        $backend = getenv('BACKEND') ?: 'mock';
        if ($backend !== 'mock') {
            $I->seeResponseContains($row['field']);
        }
    }

    protected function missingRequiredFields(): array
    {
        return [
            ['field' => 'mbId'],
            ['field' => 'name'],
            ['field' => 'email'],
            ['field' => 'active'],
        ];
    }

    /**
     * Covers P6: invalid email returns 400 and the bad value is echoed.
     */
    public function invalidEmailReturns400(ApiTester $I): void
    {
        $payload = MediaBuyerFactory::valid()
            ->with(['email' => 'not-an-email'])
            ->build();

        $I->createMediaBuyer($payload);

        $I->seeResponseCodeIs(400);
        $I->seeResponseMatchesJsonSchema('error-schema.json');

        $backend = getenv('BACKEND') ?: 'mock';
        if ($backend !== 'mock') {
            $I->seeResponseContains('not-an-email');
        }
    }

    /**
     * Covers P7: initials must be exactly 2 characters.
     */
    public function initialsLongerThan2Returns400(ApiTester $I): void
    {
        $payload = MediaBuyerFactory::valid()
            ->with(['initials' => 'TOO LONG'])
            ->build();

        $I->createMediaBuyer($payload);

        $I->seeResponseCodeIs(400);
        $I->seeResponseMatchesJsonSchema('error-schema.json');

        $backend = getenv('BACKEND') ?: 'mock';
        if ($backend !== 'mock') {
            $I->seeResponseContains('initials must be exactly 2 characters');
        }
    }

    /**
     * Covers P8: name length boundaries (2..30 inclusive).
     *
     * @dataProvider nameLengthCases
     */
    public function nameLengthBoundaries(ApiTester $I, Example $row): void
    {
        $payload = MediaBuyerFactory::valid()
            ->with(['name' => $row['name']])
            ->build();

        $I->createMediaBuyer($payload);
        $I->seeResponseCodeIs($row['expectedStatus']);

        if ($row['expectedStatus'] === 400) {
            $I->seeResponseMatchesJsonSchema('error-schema.json');
        }
    }

    protected function nameLengthCases(): array
    {
        return [
            ['name' => '',                             'expectedStatus' => 400], // empty
            ['name' => 'A',                            'expectedStatus' => 400], // below min
            ['name' => 'AB',                           'expectedStatus' => 200], // min boundary
            ['name' => str_repeat('A', 30),            'expectedStatus' => 200], // max boundary
            ['name' => str_repeat('A', 31),            'expectedStatus' => 400], // above max
        ];
    }

    /**
     * Covers P9: mbId must be a non-empty string of digits (positive integer).
     *
     * @dataProvider invalidMbIds
     */
    public function mbIdMustBePositiveIntegerString(ApiTester $I, Example $row): void
    {
        $payload = MediaBuyerFactory::valid()
            ->with(['mbId' => $row['mbId']])
            ->build();

        $I->createMediaBuyer($payload);
        $I->seeResponseCodeIs(400);
        $I->seeResponseMatchesJsonSchema('error-schema.json');
    }

    protected function invalidMbIds(): array
    {
        return [
            ['mbId' => 'abc'],
            ['mbId' => '-1'],
            ['mbId' => '1.5'],
            ['mbId' => ''],
            ['mbId' => '0'], // zero is not a positive integer
        ];
    }

    /**
     * Covers P10: active must be a boolean.
     *
     * @dataProvider nonBooleanActiveValues
     */
    public function activeNonBooleanReturns400(ApiTester $I, Example $row): void
    {
        $payload = MediaBuyerFactory::valid()
            ->with(['active' => $row['value']])
            ->build();

        $I->createMediaBuyer($payload);
        $I->seeResponseCodeIs(400);
        $I->seeResponseMatchesJsonSchema('error-schema.json');
    }

    protected function nonBooleanActiveValues(): array
    {
        return [
            ['value' => 'yes'],
            ['value' => 1],
            ['value' => 0],
            ['value' => null],
        ];
    }

    /**
     * Covers P11: duplicate mbId rejected on second create.
     *
     * ASSUMPTION: contract leaves the status open (400 or 409). We accept
     * either to avoid coupling the test to a backend choice; see
     * ASSUMPTIONS.md.
     */
    public function duplicateMbIdRejected(ApiTester $I): void
    {
        $I->skipIfBackendIsMock('uniqueness needs a stateful backend; contract mock is stateless');
        $first = MediaBuyerFactory::valid()->build();

        $I->createMediaBuyer($first);
        $I->seeResponseCodeIs(200);

        $second = MediaBuyerFactory::valid()
            ->with(['mbId' => $first['mbId']])
            ->build();

        $I->createMediaBuyer($second);
        $I->seeResponseCodeIsClientError(); // 400 or 409
        $I->seeResponseMatchesJsonSchema('error-schema.json');
    }
}
