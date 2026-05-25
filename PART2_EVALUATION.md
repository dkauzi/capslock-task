# Part 2, Written Evaluation

Quick note on voice. This is written the way I'd write a design doc for
the team rather than a pitch. Where I'm guessing I'll flag it; where I'm
pulling from past projects I'll say so.

## 1. Which scenarios I picked, and what I left out

Three priorities, in this order.

Every acceptance criterion gets at least one test. That's non-negotiable.
If the contract says "G3: empty list returns `{data: []}`", there has to
be a test that breaks when that breaks. Anything that skips an AC to
chase a clever edge case has misread the brief.

Both sides of every boundary. Name length 2 and 30, not just "too short".
The most common silent regression I've seen (and inherited) is an
off-by-one in payload validators where the lower bound got attention and
the upper bound didn't. Two rows in a DataProvider catches the whole
class.

Asymmetric coercions get their own test. `active: true` in, `active: 1`
out. That's the contract, but it's also the kind of thing that quietly
breaks when someone refactors a serializer and "fixes" the asymmetry.
Putting it on its own test method, instead of folding it into a happy
path, means the report points to the right line when it breaks.

What I left out, with reasons:

* **Performance and load.** Wrong tool. A Codeception suite that
  occasionally measures latency teaches the team nothing useful and goes
  red on flaky network days. k6 or Locust against a perf environment is
  the right home.
* **Auth flows.** The contract specifies none. Tests written for "what
  auth probably looks like" break the day someone adds the real thing.
* **Pagination, filtering, sorting.** The endpoint accepts no query
  parameters. Adding tests "just in case" trains the team to maintain
  dead code.
* **Concurrent duplicate `mbId` race conditions.** Real test needs a real
  database under contention. Belongs in an integration suite, not this
  one.
* **Exact wording of error messages beyond P7.** Product copy churns. I
  assert structural shape via `error-schema.json` and substring presence
  where the contract is explicit, and nothing more.

## 2. The abstractions and what they buy as the suite grows

Five of them. In the order I built them:

`MediaBuyerFactory` is the most important one. Hard-coded JSON in test
methods is the single largest source of test rot I've seen. When the
schema adds a `phone` field, you either touch one factory or you touch
every test. Negative tests get to express the deviation
(`MediaBuyerFactory::valid()->without('email')`) instead of restating the
whole payload. At 80 tests this isn't optional. It's the difference
between a maintainable suite and one nobody wants to touch.

`FieldGenerators` sits underneath the factory. Realistic data via Faker,
generated per field. Two things this buys you: tests aren't coupled to
specific literals, so an "email format changes" backend update doesn't
break 30 tests; and you can swap to seeded mode when you need
reproducible failures. Both come up within the first month of a real
suite.

`ApiClient` helper is the seam. It owns the headers, the base URL, the
resource verbs. Tests never call `sendPost` directly. The day you add
auth, retry, correlation IDs, or response logging, you touch one file.
Without this seam every cross-cutting concern becomes a search-and-replace
across the test files.

`SchemaValidator` helper is the one-line wrapper around
`justinrainbow/json-schema`. Schema lives in version control next to the
tests. Once it's there, your test methods stop checking individual field
presence and start checking behaviour. Test bodies shrink. The shape of
"valid" lives in JSON, where it belongs.

`error-schema.json` is the abstraction most candidates miss. The 400
envelope is part of the contract surface. If the backend silently drops
the `errors` array or renames `detail`, a test that only checks the
status code passes. A schema check on the error body catches the
regression where it actually is.

Two smaller things worth calling out. DataProviders for boundaries mean
a new edge case is one array row, not a new method, and each row shows
up as its own line in Allure and Qase. When row 4 fails you see *which*.
And the `BACKEND` env switch with `skipIfBackendIsMock` lets the same
suite run against a contract mock (clean CI gate) and a real environment
(full assertions). Skips are named, reasoned, and disappear when the env
flips. The alternative is permanent `@skip` tags nobody removes, or two
divergent suites that drift apart. I've inherited both. Neither survives
contact with a team.

The point of a good abstraction is that the test methods read at 80 the
way they read at 8. If you find yourself explaining "yeah but you have
to copy the boilerplate from over here", the abstraction failed.

## 3. Contract-drift detection

`mock/openapi.yaml` is the contract. Drift detection means making a
backend change that violates it fail before anyone runs this suite.

The tooling I'd reach for:

* **Backend repo owns the spec.** OpenAPI lives next to the controllers
  and gets published as a build artifact on every merge to main.
* **`openapi-diff` on a nightly cron** in the QA repo's CI. It compares
  the latest published spec to the committed `mock/openapi.yaml` and
  opens an auto-PR titled *"Contract drift: \<summary\>"* with the diff
  in the body.
* **Dredd** in the backend CI on every PR. Exercises the spec against
  the service. Backend can't merge a controller change that contradicts
  its own OpenAPI document.
* **Pact** for any consumer that talks to this API in production. The
  consumer publishes its expectations; the provider verifies them in CI.
  That's the one that catches "we changed the field, nobody told the
  dashboard" breaks, which always sneak past code review because the
  change looks harmless.

The process side matters as much as the tools. The drift PR has to land
in the QA repo with a checklist auto-generated from the diff (added
fields = new schema entry; removed fields = test deletions; changed
validation = DataProvider expansion). A `CODEOWNERS` rule on
`tests/schemas/` assigns a QA owner automatically. Test updates merge in
the same PR as the spec bump. Schemas and tests stay in lockstep.

Breaking changes trip a `schemas/v1/` to `schemas/v2/` split so the old
contract still has a regression net for existing clients.

The pattern that's bitten me before: backend ships a "harmless" rename,
nobody updates the consumer, the dashboards quietly stop populating in
production. The fix is to make the spec the single mandatory artifact,
with mechanical detection on both sides.

## 4. Tools for generation, maintenance, flakiness, reporting

I'll be straight on the AI part because that's the one this question is
really asking about. I use AI in a governed way: limited, tactical,
never for the bits that need judgment. Specifics on where it earns its
keep and where I keep it out.

**Test generation.** The highest-leverage automation here isn't AI. It's
walking the OpenAPI spec and emitting DataProvider rows for boundary
cases. Either a small internal script or something like
`openapi-test-generator`. Predictable, reviewable, no hallucinations.

Where AI helps: turning a manual repro from a bug ticket into a Cest
skeleton, or converting an OpenAPI diff into a candidate set of new
DataProvider rows. Both are boilerplate-heavy and easy to review. I treat
the output like a junior engineer's first draft, reviewed line by line,
edited, then merged. The unreviewed AI-generated tests I've inherited
have been uniformly worse than hand-written ones (over-tested in obvious
places, missing in important ones), so I keep it on the boring side of
the work.

**Maintenance.** Lean on the factories and schemas described in Q2. The
biggest maintenance win on any suite I've owned is fewer literals in test
bodies. Every literal is a future maintenance event.

**Flakiness.** Three things matter, none of them AI:

1. A `@group flaky` quarantine that runs out of band and reports without
   blocking the gate. Flakes left in the main run train the team to
   ignore red, which is worse than the flake itself.
2. A "flake rate" panel on the Allure trends page. Anything above 1%
   gets triaged the same week.
3. Deterministic data (seeded Faker, fixed time, idempotent fixtures)
   eliminates a whole category of flakes before any tool gets reached
   for.

Where AI does help: clustering the last week of failures into signatures
so triage starts with *"these three failures have the same root cause"*
instead of opening 30 reports by hand. That's pattern-matching work, it's
auditable, and the worst case is a wrong grouping you re-open.

**Reporting.** Allure for the developer-facing report (history, trends,
attachments), Qase for the QA-team-facing case management and run
sign-off, and a thin Slack notifier that posts the run summary with a
link to the failing case. Three tools, three audiences. Trying to make
one tool serve all three has always ended in nobody using any of them.

So: AI on the edges (boilerplate, triage clustering), humans on the
load-bearing decisions (what to test, how to abstract, what counts as
flake vs real). That's the senior version of using it.

## 5. The hardest E2E automation problem I've owned

The recurring shape of the hardest problems I've owned: a suite that goes
red for reasons nobody trusts to be real. At Sulake, a release-blocking
suite of around 600 E2E tests had drifted into a 12% flake rate.
Engineers retried red runs until they went green. QA had stopped
triaging individual failures because the signal-to-noise had collapsed.

The wrong fix was to ban retries and shame engineers. That would have
ground releases to a halt. The right fix was infrastructural.

First I tagged every failure with its signature for two weeks before
touching anything. Real failures were a small minority. The rest
clustered into four root causes: shared test data races between parallel
workers, an auth token that expired mid-suite on long runs, a CDN
warm-up problem on first-deploy environments, and one genuinely
non-deterministic feature flag.

Then I fixed the substrate, not the tests. Per-worker test data
namespaces killed the races. A token-refresh middleware in the HTTP
client killed the auth flakes. A CDN warm-up step in the CI job killed
the third. The feature flag got pinned in test environments.

Anything still flaky after the substrate fixes went into a `@group flaky`
lane that ran nightly and reported without blocking. Visibility without
pain. And a red run on `main` was made a stop-the-line event again,
because it now meant something.

Flake rate dropped under 1% within a month.

The lesson I carry: a flake is almost never a test problem. It's a
test-environment problem dressed up as one. Treat it that way and the
suite gets quieter on its own.
