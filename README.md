# Capslock QA — Media Buyers API Automation

PHP + Codeception suite for the Media Buyers REST contract.
The deliverable is the **test code itself**, not a runnable harness — there is
no live server. The repo is shaped the way I would ship it on day one of a
real engagement, then iterate as the API surface grows.

---

## Suite layout

| Module | Why it's enabled |
|---|---|
| `REST` (`part: Json`) | The endpoints are JSON-only; loading the `Json` part keeps the actor surface small and the failure messages relevant. |
| `PhpBrowser` | HTTP transport for `REST`. Cheaper than launching a real browser and the only thing needed for a JSON API. |
| `Asserts` | First-class PHPUnit assertion verbs inside Cest classes (`assertSame`, `assertGreaterThan`) without reaching for `\PHPUnit\…`. |
| `SchemaValidator` (custom helper) | Wraps `justinrainbow/json-schema` so every response check is one line and the schema lives in version control next to the tests. |
| `ApiClient` (custom helper) | The single seam between tests and the HTTP layer — headers, base URL, and resource verbs all flow through here. Tests never call `sendPost` directly. |

Reporting: `allure-codeception` writes results to `tests/_output/allure-results`;
`qase/codeception-reporter` syncs each run to a Qase project keyed by
`QASE_PROJECT_CODE`. Both are extensions (see `codeception.yml`) and turn
themselves off when their env vars are absent, so local runs stay quiet.

---

## Repository organization

```
.
├── codeception.yml             # global config + Allure/Qase extensions
├── composer.json               # codeception, modules, schema validator, faker, reporters
├── docker-compose.yml          # optional Prism mock against mock/openapi.yaml
├── mock/openapi.yaml           # OpenAPI 3 contract — feeds Prism + future contract tests
├── .github/workflows/api-tests.yml
├── tests/
│   ├── Api.suite.yml           # actor + module wiring
│   ├── Api/MediaBuyers/
│   │   ├── GetMediaBuyersCest.php       # G1–G7
│   │   └── CreateMediaBuyerCest.php     # P1–P11 (DataProvider-driven negatives)
│   ├── _support/
│   │   ├── ApiTester.php
│   │   ├── Helper/
│   │   │   ├── ApiClient.php            # headers, base URL, resource verbs
│   │   │   └── SchemaValidator.php      # JSON Schema draft-07 validator
│   │   └── Factory/
│   │       ├── MediaBuyerFactory.php    # fluent builder: valid()/without()/with()
│   │       └── FieldGenerators.php      # mbId, initials, name, email, slackUserId
│   └── schemas/
│       ├── get-media-buyers-schema.json
│       ├── post-media-buyer-schema.json
│       └── error-schema.json            # validates 400 body shape
├── ASSUMPTIONS.md              # every place the contract is silent
└── PART2_EVALUATION.md         # written answers to Part 2
```

---

## Scenario coverage

15 test methods exercise all 18 acceptance criteria. Parameterized methods
expand into ~30 test cases in the report.

| AC | Test |
|---|---|
| G1 | `GetMediaBuyersCest::returnsHttp200WithJsonContentType` |
| G2, G4, G5, G6 | `GetMediaBuyersCest::responseMatchesSchema` (schema enforces required fields, email format, `active` enum) |
| G3 | `GetMediaBuyersCest::dataIsArrayEvenWhenEmpty` |
| G7 | `GetMediaBuyersCest::idsAreUniqueAcrossResponse` |
| P1 | `CreateMediaBuyerCest::validRequestReturns200AndMatchesSchema` |
| P2, P3 | `responseEchoesRequestFieldsAndServerAssignsId` |
| P4 | `activeBooleanCoercedToInteger` (DataProvider × 2) |
| P5 | `missingRequiredFieldReturns400` (DataProvider × 4) |
| P6 | `invalidEmailReturns400` |
| P7 | `initialsLongerThan2Returns400` |
| P8 | `nameLengthBoundaries` (DataProvider × 5 — both boundaries on both sides) |
| P9 | `mbIdMustBePositiveIntegerString` (DataProvider × 5) |
| P10 | `activeNonBooleanReturns400` (DataProvider × 4) |
| P11 | `duplicateMbIdRejected` |

### Why these scenarios

- **Schema validation carries the bulk of the GET coverage.** Once a JSON Schema
  is in place, asserting individual field presence in test methods is duplication
  that rots when fields are added. Schema = one place to evolve.
- **Boundaries on both sides of every range.** Length 2 and 30 for `name`, not
  just "too short" — a regression that off-by-one allows length 31 is exactly
  what a senior reviewer would expect the suite to catch.
- **`active` coercion is its own test.** Asymmetric boolean→integer mapping is
  a classic source of cross-service bugs; making it its own row in the report
  makes the contract behaviour explicit, not a footnote on a happy-path test.
- **Duplicate `mbId` asserts the *class* of error**, not the exact code.
  Locking in 409 vs 400 couples the suite to a backend decision the contract
  leaves open.

### What I intentionally left out

- Performance and load — wrong tool (k6 / Locust), wrong suite.
- Auth flows — the contract specifies none; speculative tests would rot.
- Pagination, filtering, sorting — the endpoint takes no query parameters.
- Concurrency on duplicate `mbId` — needs real infra to be meaningful; would
  belong in an integration suite once a staging DB exists.

---

## Abstractions and what they buy at 80 tests

| Abstraction | What it buys when the suite grows |
|---|---|
| `MediaBuyerFactory::valid()->with()/without()` | When the schema adds a field, you change one file. Negative tests express *what's wrong*, not *what JSON looks like*. |
| `FieldGenerators` | Realistic data without coupling tests to specific literals. Swap to deterministic generators (seeded Faker) when you need reproducible failures. |
| `ApiClient` helper | Single seam for headers, base URL, auth, retry/backoff, correlation IDs, anything cross-cutting. Tests stay readable: `$I->createMediaBuyer($payload)`. |
| `SchemaValidator` helper | One-line schema check. Encourages schemas as the source of shape truth so test methods focus on behaviour. |
| `error-schema.json` | The 400 body shape becomes part of the contract surface — not just the status code. |
| DataProviders for boundaries | New boundary = new array row, not a new method. Each row gets its own line in Allure/Qase. |
| `.env` + `%BASE_URL%` placeholder | Per-environment config without code change. CI sets `BASE_URL`; nothing else moves. |
| Qase `@qaseId` annotations | Two-way trace between test methods and the cases the QA team manages in Qase. Coverage gaps surface as cases without test ids. |
| `BACKEND` env-gated skips | Same suite runs against contract mock and real backend. Skips are named, reasoned, and disappear when the env flips — not permanent `@skip` tags that rot. |

---

## Reporting & test management

**Allure** — Each Cest writes structured results into `tests/_output/allure-results`.
`allure serve tests/_output/allure-results` opens a local report. CI publishes
the same report as an artifact (`.github/workflows/api-tests.yml`).

**Qase** — `qase/codeception-reporter` is wired in `codeception.yml` and
controlled by `QASE_REPORT`, `QASE_PROJECT_CODE`, and `QASE_API_TOKEN`. Each
test method is mapped to a Qase case via `@qaseId N` in the docblock; the
Qase project mirrors the file structure:

```
Project: MB (Media Buyers)
  Suite: API
    Section: GET /api/mediabuyers       → GetMediaBuyersCest
    Section: POST /api/mediabuyers      → CreateMediaBuyerCest
```

**ClickUp** — bug reports for the manual portion (`MANUAL_BUGS.md`) follow the
ClickUp task template the QA team uses: Title / Description / Steps /
Expected / Actual / Severity / Environment / Attachments. Files are written
in this repo so a reviewer sees the format alongside the automation work.

---

## Running it

The repo ships with a self-contained Docker harness — no PHP needed on the
host, no real backend needed:

```bash
docker compose up -d                              # prism mock + php runner
docker compose exec runner sh -c '
  cp -n .env.example .env &&
  sed -i "s|^BASE_URL=.*|BASE_URL=http://mock-api:4010|" .env &&
  composer install &&
  vendor/bin/codecept build &&
  BACKEND=mock vendor/bin/codecept run Api
'
```

Expected output: **29 tests, 25 passing, 4 skipped.** The 4 skips are tests
whose assertions a stateless contract mock cannot satisfy (request echoing,
boolean coercion, uniqueness across calls) — see the `BACKEND` switch
below.

### The `BACKEND` env switch

The same suite runs against a contract mock or a real environment. Tests
that need behaviour only a real backend produces are gated with
`$I->skipIfBackendIsMock('reason')` (or an `if` block where only one
assertion needs to be conditional).

```bash
# Contract mock (default) — green gate, 25 pass / 4 skipped
BACKEND=mock      vendor/bin/codecept run Api

# Real environment — all 29 run
BACKEND=real BASE_URL=https://staging.example.com vendor/bin/codecept run Api
```

This is the pattern I use to keep CI honest: a clean green run on every PR
without dishonest assertions, and zero coverage loss the moment the suite
points at staging. Skips are explicit, named, and gated by env — not
permanent `@skip` tags that rot.

### If a real PHP environment exists locally

```bash
composer install
cp .env.example .env       # set BASE_URL
vendor/bin/codecept run Api --steps
```

---

## Improvements I would land before this suite reaches 80 tests

1. **Contract testing.** `mock/openapi.yaml` is already the source of truth.
   Wire **Dredd** (or Pact for consumer-driven flows) into CI so a backend PR
   that drifts from the spec fails before it merges, not when this suite
   breaks downstream.
2. **Schema versioning.** Move `tests/schemas/` to `tests/schemas/v1/`. When a
   breaking change ships, the v1 folder is the regression suite for older
   clients and the v2 folder grows in parallel.
3. **Test data lifecycle.** A `DbSeeder` helper that hits a privileged
   `/test-support/reset` endpoint (or runs `psql TRUNCATE` against a Postgres
   service in CI) before each suite. Deterministic state beats clever cleanup.
4. **Parallelisation.** `codecept run --shard 1/N` once the suite passes ~3
   minutes wall time. Requires shard-safe seeding — addressed by (3).
5. **Flake quarantine.** A `@group flaky` tag plus a CI gate that reports
   quarantined tests but doesn't fail the build. A flake left in the main run
   trains the team to ignore red.
6. **Golden-file regression.** For complex response bodies, snapshot a known
   good response and diff. Cheap signal on accidental field renames.
7. **Observability hooks.** Inject a correlation header in `ApiClient` and log
   it on failure so backend logs can be pulled with one grep.
8. **Where AI helps in practice.** Two narrow places earn their keep:
   generating DataProvider rows from a freshly updated OpenAPI diff, and
   triaging clusters of failed runs from Allure history into a one-line
   summary. Anything past that — generating whole tests, autonomous flake
   "fixes" — has burned me enough times that I keep it out of the loop until
   a human-reviewed PR.

See `ASSUMPTIONS.md` for every place the contract was silent and how the
suite resolved it.
