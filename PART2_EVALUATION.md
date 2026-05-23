# Part 2 — Written Evaluation

## 1. Scenario selection — what's in, what's out, why

I picked scenarios on three axes: contract-binding (every acceptance
criterion gets at least one test), boundary-realism (both sides of every
range, both sides of every coercion), and report-readability (each
DataProvider row tells a reviewer *which* boundary failed without opening
the code).

**In:**
- Every `G*` and `P*` AC maps to at least one method (see README table).
- Both boundaries on every range (`name` length 2 and 30, not just "too
  short"); off-by-one regressions are the most common silent failures I see
  in payload validators.
- `active` boolean→integer coercion as its own test — asymmetric coercion is
  a recurring source of integration bugs and deserves its own line in the
  report.
- Schema-level validation of both the 200 *and* the 400 body. The error
  envelope is part of the contract surface, not an implementation detail.

**Out, with reasoning:**
- **Performance / load.** Wrong tool. A Codeception suite that occasionally
  measures latency teaches the team nothing useful and goes red on flaky
  network days. k6 or Locust against a perf environment.
- **Auth.** Contract specifies none. Speculative tests rot.
- **Pagination, filtering, sorting.** The endpoint accepts no query
  parameters. Adding "just in case" tests trains the team to maintain dead
  code.
- **Concurrency / race on duplicate `mbId`.** Meaningful only against a
  real DB with realistic contention. Belongs in an integration suite, not
  this one.
- **Exact wording of error messages beyond P7.** Product copy churns. I
  assert structural shape (via `error-schema.json`) and substring presence
  of the offending value where the contract is explicit, and nothing more.

---

## 2. Abstractions and what they buy at 8 → 80 tests

| Abstraction | At 8 tests | At 80 tests |
|---|---|---|
| `MediaBuyerFactory` | Removes hard-coded JSON from tests | When the schema adds `phone`, one line changes — not 80. Negative tests stay readable because they describe *the deviation*, not the full payload. |
| `FieldGenerators` | Realistic values without test coupling | Seeded mode for reproducible failures; per-field generators evolve independently as validation rules tighten. |
| `ApiClient` helper | Headers in one place | The seam where auth, retry, correlation IDs, and version pinning land. None of those changes touch tests. |
| `SchemaValidator` helper | One-line response check | Schemas absorb new fields without changing a single test method. The shape of "valid" lives in JSON, not in PHP. |
| `error-schema.json` | Validates 400 envelope | Catches a backend that silently drops the `errors` array or renames `detail`. |
| DataProviders | One method handles 5 boundaries | New boundary = one array row. Each row is its own line in Allure / Qase, so when row 4 fails you see *which*. |
| `.env` + `%BASE_URL%` | No hard-coded URL | Environment matrix in CI is one job per env, not one branch per env. |
| `@qaseId` annotations | Cosmetic | Two-way trace: cases without test ids are coverage gaps; tests without case ids are orphans. The QA lead can audit either direction. |
| OpenAPI in `mock/` | Lets the suite run against Prism locally | Same file becomes the contract-test source-of-truth. One spec, two consumers. |
| `BACKEND` env switch + `skipIfBackendIsMock` | Lets the same 29 tests run against the contract mock (clean green gate) and a real environment (full assertions). | Skips are named, reasoned, and disappear when the env flips. The alternative — permanent `@skip` annotations or two divergent suites — is how coverage rots in every codebase I've inherited. |

The header on this question is "at 80." The honest answer is: at 80, the
test methods read identically to how they read at 8. That is the test of a
good abstraction.

---

## 3. Contract-drift detection

The contract is `mock/openapi.yaml`. Drift detection means making a
backend change that violates it fail loudly before anyone runs this suite.

**Tooling:**
- **Backend repo owns the spec.** OpenAPI lives next to the controllers and
  is published as a build artifact on every merge to main.
- **`openapi-diff`** runs in the QA repo's CI on a nightly cron, comparing
  the latest published spec to `mock/openapi.yaml`. Any change opens an
  auto-PR titled *"Contract drift: <summary>"* with the diff in the body.
- **Dredd** runs in backend CI on every PR, exercising the spec against the
  service. Backend can't merge a controller change that contradicts its own
  OpenAPI.
- **Pact** for any consumer that talks to this API in production. The
  consumer publishes its expectations; the provider verifies them in CI.
  Catches the "we changed the field, nobody told the dashboard" class of
  break.

**Process:**
1. The contract-drift PR lands in the QA repo with the diff and an
   auto-generated checklist of likely test impacts (added fields = new
   schema entry; removed fields = test deletions; changed validation =
   DataProvider expansion).
2. A QA owner is assigned by `CODEOWNERS` on the `tests/schemas/` path.
3. Test updates merge in the same PR as the spec bump. Schemas and tests
   stay in lockstep with the version they describe.
4. Breaking changes trip a `schemas/v1/` → `schemas/v2/` split so the old
   contract still has a regression net for existing clients.

The pattern that's bitten me before: backend ships a "harmless" rename,
nobody updates the consumer test suite, the dashboards quietly stop
populating in production. The fix is to make the spec the single
mandatory artifact, with mechanical detection on both sides.

---

## 4. Tooling for generation, maintenance, flakiness, reporting

**Test generation.** OpenAPI → DataProvider rows for negative paths is the
highest-leverage automation. Tools like `openapi-test-generator` or a small
internal script that walks the spec and emits boundary cases pays for
itself within a sprint. I treat AI here as a *tactical* aid for DataProvider
boilerplate and converting a manual reproduction into a Cest skeleton — not
as the author of behaviour. Generated tests are reviewed like any other
code; the unreviewed ones I've seen ship are uniformly worse than
hand-written ones.

**Maintenance.** Lean on the factories and schemas described in §2. The
single biggest maintenance win on any suite I've owned is fewer literals in
test bodies — every literal is a future maintenance event.

**Flakiness.**
- A `@group flaky` quarantine that runs out of band and reports without
  blocking the gate. Flakes left in the main run train the team to ignore
  red, which is worse than the flake itself.
- A "flake rate" panel in the Allure trends page; anything above 1% gets
  triaged the same week.
- Deterministic data (seeded Faker, fixed time) eliminates a category of
  flakes before any tool is reached for.
- Where AI earns its keep is clustering the last week of failures into
  signatures so triage starts with "these three failures have the same root
  cause" instead of opening 30 reports by hand.

**Reporting.** Allure for the developer-facing report (history, trends,
attachments), Qase for the QA-team-facing case management and run
sign-off, and a thin Slack notifier that posts the run summary with a link
to the failing case. Three tools, three audiences. Trying to make one tool
serve all three has always ended in nobody using any of them.

---

## 5. Hardest API / E2E automation situation and how I resolved it

The recurring shape of the hardest problems I've owned: a suite that goes
red for reasons nobody trusts to be real. At Sulake, a release-blocking
suite of ~600 E2E tests had drifted into ~12% flake rate. Engineers retried
red runs until they went green; QA had stopped triaging individual
failures.

The wrong fix was to ban retries and shame engineers — that would have
ground releases to a halt. The right fix was infrastructural:

1. **Measure first.** Tagged every failure with its signature for two
   weeks. Real failures were a small minority; the rest clustered into four
   root causes: shared test data races, an auth token that expired
   mid-suite, a CDN warm-up problem, and one genuinely non-deterministic
   feature flag.
2. **Fix the substrate, not the tests.** Per-worker test data namespaces
   killed the races. A token-refresh middleware in the HTTP client killed
   the auth flakes. A CDN warm-up step in the CI job killed the third. The
   feature flag got pinned in test environments.
3. **Quarantine, don't delete.** Anything still flaky after the substrate
   fixes went into a `@group flaky` lane that ran nightly and reported
   without blocking — visibility without pain.
4. **Re-establish trust.** A red run on `main` was made a stop-the-line
   event again, *because* it now meant something.

Flake rate dropped under 1% within a month. The lesson I carry: a flake is
almost never a test problem. It's a test-environment problem dressed up as
one. Treat it that way and the suite gets quieter on its own.
