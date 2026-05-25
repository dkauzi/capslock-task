# Assumptions

The contract is the source of truth. Where it's silent, here's what I
decided and why, so a future reader can challenge it instead of guessing
what the test meant.

1. **Duplicate `mbId` (P11).** Accept either 400 or 409 on the second
   create. The contract leaves the status open. Asserting one couples the
   suite to a backend choice; asserting the client-error class is what
   the contract actually requires.

2. **Empty list (G3).** Empty system returns 200 with `{"data": []}`.
   The contract guarantees `data` is always an array. Status code for
   the empty case isn't called out, but 200 is the only consistent
   reading.

3. **`active: true` becomes `1` in the response.** Asymmetric coercion is
   intentional per the contract, which states it explicitly. I flagged
   it because asymmetric coercion is a common source of integration bugs
   and I wanted it on its own test method, not buried in a happy-path
   assertion.

4. **`initials` is optional.** Sent when present, omitted when absent.
   The "if provided" wording is treated literally. The positive path
   includes a request without `initials`.

5. **`mbId: "0"` is rejected.** "0" is not a positive integer. Filed
   under P9 negatives.

6. **Error-message wording (P7).** Substring match, not exact. Wording is
   product copy; tests shouldn't break when marketing edits a string.

7. **Auth.** None. The contract specifies no auth headers. If added
   later, `ApiClient::_before()` is the single place to wire it.

8. **`slackUserId` length.** 1..32 enforced in the JSON schemas. Contract
   states the range.

9. **Empty-state fixture.** Achieved via a dedicated DB seed. In a real
   environment the suite would either point at a `?fixture=empty` URL or
   seed a clean DB before the test. Documented here so the dependency
   isn't hidden.

10. **Content negotiation.** Tests always send both headers via
    `ApiClient::_before()`. The contract requires `Content-Type` and
    `Accept`. Sending them per test would invite drift.
