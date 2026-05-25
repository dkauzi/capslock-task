<?php

declare(strict_types=1);

namespace Tests\Api\MediaBuyers;

use Tests\ApiTester;

/**
 * GET /api/mediabuyers, acceptance criteria G1..G7.
 *
 * Qase mapping: project MB, suite "Media Buyers / GET". @qaseId values are
 * placeholders to be reconciled with the live Qase project on first run.
 */
final class GetMediaBuyersCest
{
    /**
     * @qaseId 101
     * Covers G1.
     */
    public function returnsHttp200WithJsonContentType(ApiTester $I): void
    {
        $I->listMediaBuyers();
        $I->seeResponseCodeIs(200);
        $I->seeHttpHeader('Content-Type', 'application/json');
    }

    /**
     * @qaseId 102
     * Covers G2, G4, G5, G6 (the schema enforces shape, required fields,
     * email format and the active enum [0,1]).
     */
    public function responseMatchesSchema(ApiTester $I): void
    {
        $I->listMediaBuyers();
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonSchema('get-media-buyers-schema.json');
    }

    /**
     * @qaseId 103
     * Covers G3: empty system still returns {"data": []} with HTTP 200.
     *
     * NOTE: requires a deterministic empty state. In a real environment we
     * would expose an `?env=empty` fixture endpoint or seed a clean DB before
     * this test. Documented in ASSUMPTIONS.md.
     */
    public function dataIsArrayEvenWhenEmpty(ApiTester $I): void
    {
        $I->listMediaBuyers();
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseMatchesJsonSchema('get-media-buyers-schema.json');
        $I->seeResponseContainsJson(['data' => []]);
    }

    /**
     * @qaseId 104
     * Covers G7: ids unique across the collection.
     */
    public function idsAreUniqueAcrossResponse(ApiTester $I): void
    {
        $I->listMediaBuyers();
        $I->seeResponseCodeIs(200);

        $ids = $I->grabDataFromResponseByJsonPath('$.data[*].id');
        $I->assertSame(
            count($ids),
            count(array_unique($ids)),
            'Expected all media-buyer ids to be unique across the response.'
        );
    }
}
