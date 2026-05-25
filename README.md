# Capslock QA Task

[![API tests](https://github.com/dkauzi/capslock-task/actions/workflows/api-tests.yml/badge.svg)](https://github.com/dkauzi/capslock-task/actions/workflows/api-tests.yml)

By Denis Miano.

PHP + Codeception tests for the Media Buyers API contract. The deliverable
is the test code, not a runnable harness, but I included a Docker setup
so you can actually see it run if you want to.

## What's in here

```
.
├── codeception.yml
├── composer.json
├── docker-compose.yml          prism mock + composer runner (optional)
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

## The setup I'd use, and why

Codeception with three modules: REST (Json part), PhpBrowser, Asserts.
That's the minimum needed for a JSON API and it keeps the actor surface
small enough that the autocomplete is useful.

Two custom helpers do most of the heavy lifting:

* `ApiClient` is the single place that sets `Content-Type`/`Accept` headers
  and wraps `sendGet` / `sendPost` behind verbs like `listMediaBuyers()`
  and `createMediaBuyer($payload)`. Tests never touch raw HTTP. When auth
  shows up, when we want correlation IDs, when retry-on-503 becomes a
  thing, it's one file.
* `SchemaValidator` wraps `justinrainbow/json-schema` so a response
  conformance check is one line: `$I->seeResponseMatchesJsonSchema('post-media-buyer-schema.json')`.

Payloads come from a fluent factory, not hard-coded JSON. You'll see
calls like `MediaBuyerFactory::valid()->without('email')->build()` and
`MediaBuyerFactory::valid()->with(['initials' => 'TOO LONG'])->build()`.
Negative tests get to describe *what's wrong*, not restate the whole
payload.

For boundaries and the parameterised contract cases (P5, P8, P9, P10),
each variation is a row in a DataProvider, not a copy-pasted method.
That way a new edge case is one array entry and shows up as its own line
in the test report.

## Scenario coverage

15 test methods cover all 18 acceptance criteria. With DataProviders they
expand into 29 cases when the suite runs.

GET endpoint:

* G1: `returnsHttp200WithJsonContentType`
* G2, G4, G5, G6: `responseMatchesSchema` (the schema enforces required
  fields, email format, and the `active` enum, so one test covers four ACs)
* G3: `dataIsArrayEvenWhenEmpty`
* G7: `idsAreUniqueAcrossResponse`

POST endpoint:

* P1: `validRequestReturns200AndMatchesSchema`
* P2, P3: `responseEchoesRequestFieldsAndServerAssignsId`
* P4: `activeBooleanCoercedToInteger` (2 rows)
* P5: `missingRequiredFieldReturns400` (4 rows: mbId, name, email, active)
* P6: `invalidEmailReturns400`
* P7: `initialsLongerThan2Returns400`
* P8: `nameLengthBoundaries` (5 rows, both sides of the range)
* P9: `mbIdMustBePositiveIntegerString` (5 rows: "abc", "-1", "1.5", "", "0")
* P10: `activeNonBooleanReturns400` (4 rows)
* P11: `duplicateMbIdRejected`

A few choices worth pointing out:

* Schema validation does the heavy lifting on the GET endpoint. Asserting
  individual fields in test methods would just duplicate what the schema
  already says, and that duplication rots the moment a field gets added.
* Length 2 *and* 30 for `name`. Off-by-one regressions show up at the
  upper bound first, and most candidates only test "too short".
* `active` coercion (boolean to integer) gets its own test, not a side
  assertion. That kind of asymmetric mapping is a recurring source of
  cross-service bugs, and it deserves a labelled line in the report.
* P11 asserts a client-error class, not 400 vs 409. The contract leaves
  the exact code open, so locking it down would couple the suite to a
  backend choice.

## Reporting

Allure and Qase are the JD's stack, and both are wired into
`codeception.yml`. I disabled them in `require-dev` only because the Qase
v1 package conflicts with Codeception 5 in solver. In a real CI you'd
add `allure-framework/allure-codeception` and `qase-tms/codeception-reporter`
(the v2 fork that supports Codeception 5), and the Allure outputs land in
`tests/_output/allure-results` ready for `allure serve`.

Each test method has a `@qaseId` annotation in the docblock so the Qase
case ID and the test method stay linked both ways. The Qase project
mirrors the file tree:

```
Project: MB (Media Buyers)
  Suite: API
    Section: GET /api/mediabuyers    → GetMediaBuyersCest
    Section: POST /api/mediabuyers   → CreateMediaBuyerCest
```

Bug reports for the manual part (`MANUAL_BUGS.md`) follow the ClickUp
template the QA team uses: title, description, steps, expected, actual,
severity, environment.

## Running it

The brief says I don't need to make it runnable. I did anyway, because
"I built it but never saw it work" isn't a thing I'd ship.

There's a Docker setup with two services: a Prism mock derived from
`mock/openapi.yaml`, and a composer container that runs the suite against
the mock.

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

Result: 29 tests, 25 pass, 4 skipped. The skips are real assertions that
need a real backend (request echo, boolean coercion, mbId uniqueness),
and they're gated by an env switch.

### The BACKEND switch

The same suite runs against the contract mock and a real environment.
Tests that need behaviour only a real backend can produce call
`$I->skipIfBackendIsMock('reason')`, and the skip disappears when you
flip the env:

```bash
BACKEND=mock vendor/bin/codecept run Api          # default, CI gate
BACKEND=real BASE_URL=https://staging.example.com vendor/bin/codecept run Api
```

This is the bit I'd want a reviewer to notice. The alternative is
permanent `@skip` tags that nobody removes, or two divergent suites that
drift. I've inherited both, and neither survives contact with a team.

## Assumptions

Every place the contract is silent is in `ASSUMPTIONS.md` with the choice
and the reasoning. Open questions worth flagging up front:

* Duplicate `mbId` returns 400 or 409. Test accepts either.
* Empty list returns 200 with `{"data": []}`. Contract guarantees the
  array shape, not the status, but 200 is the only consistent reading.
* `active: true` produces `active: 1` in the response. Contract states
  this; I flagged it because asymmetric coercions cause bugs.
* `initials` is optional. The "if provided" wording is treated literally.

## Things I'd add before this suite hits 80 tests

In rough order:

1. **Contract testing.** `mock/openapi.yaml` is the source of truth. Wire
   Dredd (or Pact for consumer flows) into backend CI so a controller
   change that drifts from the spec fails there, not here.
2. **Schema versioning.** Move `tests/schemas/` to `tests/schemas/v1/`.
   When a breaking change ships, v1 keeps the regression net for older
   clients and v2 grows alongside.
3. **Parallel runs.** `codecept run --shard 1/N` once the wall time goes
   past three minutes. Needs shard-safe seeding (DB reset endpoint or
   `psql TRUNCATE` between shards).
4. **Correlation IDs in `ApiClient`.** An `X-Test-Run` header on every
   request, logged by the backend. One grep pulls every request a failing
   test made.

## On AI

I used it in a governed way: limited, tactical, never for the bits that
need judgment. The places it helped were boilerplate (turning the
OpenAPI schema into the JSON Schema files, generating the obvious
DataProvider rows, drafting the docker-compose runner). The places I
kept it out were scenario selection, the abstractions, the assumptions
list, and anything that decides what the suite should actually catch.
Unreviewed AI-generated tests I've inherited have been uniformly worse
than hand-written ones, so I treat its output like a junior's first
draft: useful starting point, every line read before it lands.

See `ASSUMPTIONS.md` for every contract silence and `PART2_EVALUATION.md`
for the five written answers.
