# Capslock QA Task

[![API tests](https://github.com/dkauzi/capslock-task/actions/workflows/api-tests.yml/badge.svg)](https://github.com/dkauzi/capslock-task/actions/workflows/api-tests.yml)



[![API tests](https://img.shields.io/github/actions/workflow/status/dkauzi/capslock-task/api-tests.yml?style=for-the-badge&label=API%20tests)](https://github.com/dkauzi/capslock-task/actions/workflows/api-tests.yml)



By Denis Miano.

* **Repo:** https://github.com/dkauzi/capslock-task
* **CI runs:** https://github.com/dkauzi/capslock-task/actions
* **Latest results:** click the badge above

PHP + Codeception tests for the Media Buyers API contract. The brief says
the test code is what's being judged, not whether it runs, so that's
where I put the effort. A Docker setup is included so you can actually
see it execute if you want to.

## Layout

```
.
├── codeception.yml
├── composer.json
├── docker-compose.yml          prism mock + composer runner
├── mock/openapi.yaml           the contract, in OpenAPI 3
├── .github/workflows/api-tests.yml
├── tests/
│   ├── Api.suite.yml
│   ├── Api/MediaBuyers/
│   │   ├── GetMediaBuyersCest.php       G1..G7
│   │   └── CreateMediaBuyerCest.php     P1..P11
│   ├── _support/
│   │   ├── ApiTester.php
│   │   ├── Helper/
│   │   │   ├── ApiClient.php
│   │   │   └── SchemaValidator.php
│   │   └── Factory/
│   │       ├── MediaBuyerFactory.php
│   │       └── FieldGenerators.php
│   └── schemas/
│       ├── get-media-buyers-schema.json
│       ├── post-media-buyer-schema.json
│       └── error-schema.json
├── ASSUMPTIONS.md
└── PART2_EVALUATION.md
```

## The setup

Codeception with REST (Json part), PhpBrowser, and Asserts. That's the
minimum for a JSON API and it keeps the actor's autocomplete useful.

Two custom helpers do the heavy lifting:

* `ApiClient` is the one place that sets `Content-Type`/`Accept` and wraps
  `sendGet`/`sendPost` behind resource verbs like `listMediaBuyers()` and
  `createMediaBuyer($payload)`. Tests don't touch raw HTTP. When auth, or
  retry, or correlation IDs show up, it's one file to change.
* `SchemaValidator` wraps `justinrainbow/json-schema` so a response check
  is one line: `$I->seeResponseMatchesJsonSchema('post-media-buyer-schema.json')`.

Payloads come from a fluent factory, not hard-coded JSON. Tests look
like `MediaBuyerFactory::valid()->without('email')->build()` or
`MediaBuyerFactory::valid()->with(['initials' => 'TOO LONG'])->build()`.
Negative tests describe what's wrong, not the whole payload.

Parameterised cases (P5, P8, P9, P10) use DataProviders. A new boundary
is one array row and shows up as its own line in the report.

## What's covered

15 test methods cover all 18 acceptance criteria. With DataProviders they
expand into 29 cases.

GET:

* G1: `returnsHttp200WithJsonContentType`
* G2, G4, G5, G6: `responseMatchesSchema` (the schema enforces required
  fields, email format, and the `active` enum, so one test covers four ACs)
* G3: `dataIsArrayEvenWhenEmpty`
* G7: `idsAreUniqueAcrossResponse`

POST:

* P1: `validRequestReturns200AndMatchesSchema`
* P2, P3: `responseEchoesRequestFieldsAndServerAssignsId`
* P4: `activeBooleanCoercedToInteger` (2 rows)
* P5: `missingRequiredFieldReturns400` (4 rows: mbId, name, email, active)
* P6: `invalidEmailReturns400`
* P7: `initialsLongerThan2Returns400`
* P8: `nameLengthBoundaries` (5 rows, both ends)
* P9: `mbIdMustBePositiveIntegerString` (5 rows: "abc", "-1", "1.5", "", "0")
* P10: `activeNonBooleanReturns400` (4 rows)
* P11: `duplicateMbIdRejected`

A few choices worth flagging:

* On the GET endpoint, the schema does the heavy lifting. Asserting each
  field in test methods would duplicate what the schema already states,
  and that duplication fails the moment the field is changed.
* `name` is tested at length 2 and 30, not just "too short". Off-by-one
  bugs show up at the upper bound first.
* `active` boolean-to-integer coercion gets its own test. Asymmetric
  coercions are a classic source of cross-service bugs and deserve a
  labelled line in the report.
* P11 asserts a client-error class, not 400 vs 409. The contract leaves
  the status open; pinning it would couple this suite to a backend
  decision.

## Reporting

Allure and Qase are both in `composer.json`
(`allure-framework/allure-codeception ^2.4` and
`qase/codeception-reporter ^2.0`). They're commented out in
`codeception.yml`'s `extensions.enabled` so the docker run stays
self-contained and offline. Uncomment to wire them up in CI.

The Qase project I'd mirror against the file tree:

```
Project: MB (Media Buyers)
  Suite: API
    Section: GET /api/mediabuyers    → GetMediaBuyersCest
    Section: POST /api/mediabuyers   → CreateMediaBuyerCest
```

## Running it

The brief says I don't have to make this runnable. I did anyway, because
"shipped but never ran" isn't something I'd put my name on.

Docker, two services: a Prism mock built from `mock/openapi.yaml` and a
composer container that runs the suite against it.

```bash
docker compose up -d
docker compose exec runner sh -c '
  cp -n .env.example .env &&
  sed -i "s|^BASE_URL=.*|BASE_URL=http://mock-api:4010|" .env &&
  composer install &&
  vendor/bin/codecept build &&
  BACKEND=mock vendor/bin/codecept run Api
'
```

29 tests, 25 pass, 4 skipped. The skips are real assertions that need a
real backend (request echo, boolean coercion, mbId uniqueness), gated by
the env switch below.

### The `BACKEND` switch

Same suite, two environments. Tests that need behaviour only a real
backend can produce call `$I->skipIfBackendIsMock('reason')`. The skip
disappears the moment you point at staging:

```bash
BACKEND=mock vendor/bin/codecept run Api                                 # default
BACKEND=real BASE_URL=https://staging.example.com vendor/bin/codecept run Api
```

The alternative is permanent `@skip` tags nobody removes, or two
divergent suites that drift apart. I've inherited both. Neither survives
contact with a team.

## Assumptions

Every contract silence is in `ASSUMPTIONS.md`. The ones worth flagging
here:

* Duplicate `mbId` returns 400 or 409. Test accepts either.
* Empty list returns 200 with `{"data": []}`. Contract guarantees the
  array shape, not the status, but 200 is the only consistent reading.
* `active: true` produces `active: 1` in the response. Contract states
  this. I flagged it because asymmetric coercions cause bugs.
* `initials` is optional. The "if provided" wording is read literally.

## What I'd add next

In rough order:

1. **Contract testing.** `mock/openapi.yaml` is the source of truth. Wire
   Dredd (or Pact for consumer flows) into backend CI so a controller
   change that drifts from the spec fails there, not here.
2. **Schema versioning.** Move `tests/schemas/` to `tests/schemas/v1/`.
   When a breaking change ships, v1 keeps the regression net for older
   clients while v2 grows alongside.
3. **Parallel runs.** `codecept run --shard 1/N` once wall time crosses
   three minutes. Needs shard-safe seeding (DB reset endpoint or
   `psql TRUNCATE` between shards).
4. **Correlation IDs in `ApiClient`.** An `X-Test-Run` header on every
   request, logged by the backend. One grep then pulls every request a
   failing test made.

## On AI

I used it in a governed way: limited, tactical, never for the bits that
need judgment. It helped with boilerplate. I treat its
output the way I treat a junior's first draft: useful starting point,
every line read.

