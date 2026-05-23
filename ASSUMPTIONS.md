# Assumptions

The contract is the source of truth. Where it is silent, this document records
the choice and the reasoning so a future reader can challenge it instead of
guessing what the test meant.

| # | Topic | Assumption | Reason |
|---|---|---|---|
| 1 | Duplicate `mbId` (P11) | Accept either 400 or 409 on the second create | Contract leaves status open. Asserting one couples the suite to a backend choice; asserting *client error class* is the contractual intent. |
| 2 | Empty list (G3) | Empty system returns 200 with `{"data": []}` | Contract guarantees `data` is always an array; status code for the empty case isn't called out but 200 is the only consistent reading. |
| 3 | `active: true` → `1` | Asymmetric coercion is intentional | Contract states this explicitly (request boolean, response integer). Flagged because asymmetric coercion is a common source of integration bugs. |
| 4 | `initials` optional | Sent when present, omitted when absent | "If provided" wording in the contract — treated as optional. Positive path includes a request without `initials`. |
| 5 | `mbId: "0"` rejected | `"0"` is not a positive integer | Contract: "positive integer". Filed under P9 negatives. |
| 6 | Error-message wording (P7) | Substring match, not exact | Wording is product copy; tests should not break when marketing edits a string. |
| 7 | Auth | None | Contract specifies no auth headers. If added later, `ApiClient::_before()` is the single place to wire it. |
| 8 | `slackUserId` length | 1..32 enforced in schemas | Contract states the range; encoded in `get-/post-media-buyer-schema.json`. |
| 9 | Empty-state fixture | Achieved via dedicated DB seed | In a real environment the suite would either point at a `?fixture=empty` URL or seed the DB; documented here to avoid hidden coupling. |
| 10 | Content negotiation | Tests always send both headers via `ApiClient::_before()` | Contract requires `Content-Type` and `Accept`. Sending them per-test would invite drift. |
