# Part 2, Written Evaluation

## 1. Which scenarios I picked, and what I left out

Three priorities, in this order.

Every acceptance criterion gets at least one test. If the contract
says "empty list returns `{data: []}`", there has to be a test that
breaks when that breaks.

Both ends of every range. Name length 2 and 30, not just "too short".
Off-by-one bugs usually show up at the top end first.

The boolean-to-integer thing (`active: true` in, `active: 1` out)
gets its own test. It's the kind of detail that quietly breaks when
someone tidies up a serializer.

What I left out:

* Performance and load. Wrong tool for this suite. k6 or Locust
  against a perf environment.
* Auth flows. Nothing about auth in the contract.
* Pagination, filtering, sorting. The endpoint takes no query params.
* Race conditions on duplicate `mbId`. Needs a real database.
* Exact error message wording beyond P7. Marketing changes those, and
  the suite shouldn't go red because of a copy edit.

## 2. The abstractions, and what they buy as the suite grows

Five of them, in the order I built them.

`MediaBuyerFactory` so test bodies don't restate the whole payload.
Add a `phone` field later and you change one factory, not thirty
tests. Negative tests say what's wrong:
`MediaBuyerFactory::valid()->without('email')`.

`FieldGenerators` for realistic per-field data via Faker. Tests
aren't tied to specific strings, and seeded mode reproduces a flake
when one happens.

`ApiClient` is the one place that knows about HTTP. Headers, base URL,
resource methods. Tests don't call `sendPost` directly. The day auth
or retries show up, one file changes.

`SchemaValidator` is a one-liner over `justinrainbow/json-schema`.
Once it's there, test bodies stop checking field presence and start
checking behaviour.

`error-schema.json` checks the 400 envelope. The error body is part
of the contract too; a test that only checks the status code misses
a silent rename of `detail`.

DataProviders turn each boundary into one array row, with its own
line in the report. The `BACKEND` env switch lets the same suite run
against a contract mock and a real backend. Assertions a mock can't
satisfy are gated by `skipIfBackendIsMock('reason')` and come back on
when you point at staging.

## 3. Contract-drift detection

`mock/openapi.yaml` is the contract. Drift detection means a backend
change that violates it fails before anyone runs this suite.

Tools I'd use:

* OpenAPI lives next to the controllers in the backend repo and gets
  published as a build artifact on every merge.
* A nightly cron in CI runs `openapi-diff` between the published
  spec and the one in this repo and opens an auto-PR with the diff
  if they don't match.
* Dredd in the backend CI on every PR. A controller change that
  doesn't match its own OpenAPI can't merge.
* Pact for any consumer in production. Catches the kind of harmless
  rename that quietly breaks a dashboard.

For the process: the drift PR lands with a small checklist generated
from the diff (added field, removed field, changed validation).
`CODEOWNERS` on `tests/schemas/` assigns a QA reviewer. Schema and
test changes go in the same PR.

Breaking changes go into a `schemas/v1/` and `schemas/v2/` split so
the old contract still has tests covering it.

## 4. Tools for generation, maintenance, flakiness, reporting

Most of this question is about AI, so I'll be straight on that. I use
it for boilerplate, not for judgment.

**Test generation.** The biggest win isn't AI, it's walking the
OpenAPI spec and emitting DataProvider rows. AI helps when I want to
turn a bug-ticket repro into a Cest skeleton, or an OpenAPI diff into
candidate rows. I read the output line by line before it lands.

**Maintenance.** The factories and schemas from Q2 do most of it. The
biggest win on any suite I've owned is fewer hard-coded values in
tests.

**Flakiness.** A `@group flaky` quarantine that runs out of band so
flakes don't block the gate. A flake-rate chart on the Allure trends
page, anything over 1% gets looked at that week. Deterministic data
(seeded Faker, fixed time, idempotent fixtures) removes a whole class
of flakes. AI helps cluster failures by signature so triage starts
with "these three have the same root cause".

**Reporting.** Allure for engineers (history, trends, attachments),
Qase for the QA team (case management, sign-off), a thin Slack note
for run summaries. Three audiences, three tools. One tool serving all
three has never worked in the teams I've been on.

## 5. The hardest E2E problem I've owned

At Sulake, a release-blocking suite of around 600 E2E tests had
drifted to a 12% rate flake . Engineers retried red runs until they
went green. QA had stopped triaging because the signal was lost.

I spent two weeks tagging every failure with a signature before
touching the tests. The real failures were a small minority. The rest
clustered into four causes: shared test data races between parallel
workers, an auth token that expired mid-suite, a CDN warm-up issue on
first-deploy environments, and one non-deterministic feature flag.

The fixes were infrastructure, not test rewrites. Per-worker test
data namespaces stopped the races. A token-refresh middleware fixed
the auth flakes. A CDN warm-up step fixed the third. The feature flag
got pinned in test environments.

Anything still flaky after that went into a `@group flaky` lane:
nightly, non-blocking. A red run on `main` meant something again.

Flake rate dropped under 1% within a month.

The lesson I took away: a flake is almost never a test problem. It's
a test-environment problem dressed up as one.
