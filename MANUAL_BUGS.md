# Part 1 — Manual Bug Report

Target: `test-qa.capslock.global`
Reporter: Denis Miano
Environment: assumed staging — confirm browser/version on triage
Format: ClickUp task template (Title / Description / Steps / Expected / Actual / Severity / Environment / Attachments)

> Severity scale: **Critical** (blocks core business flow), **High** (visible damage to trust or conversion), **Medium** (quality / hygiene), **Low** (cosmetic).

---

## 1. Geo-targeting mismatch: page advertises "Nairobi County" but CTAs ask for Michigan ZIP

**Severity:** Critical
**Where:** Top banner + every CTA block.
**Description:** The page header announces "Available in Nairobi County" while every cost-estimation CTA reads "How much does it cost to install a walk-in bath in **Michigan**?" and the ZIP input expects a 5-digit US ZIP. A Kenyan visitor cannot submit; a US visitor sees a Nairobi banner.
**Steps:**
1. Open the page.
2. Observe banner: "Available in Nairobi County".
3. Scroll to either CTA: "…install a walk-in bath in Michigan?".
4. Attempt to enter a Kenyan postal code (e.g. `00100`) and submit.
**Expected:** A single coherent market (region copy, ZIP rules, currency, fulfilment area all aligned).
**Actual:** Region copy and ZIP validation contradict each other; conversion is broken for both audiences.
**Attachments:** screenshot of banner + CTA + ZIP error.

---

## 2. Bath wall options duplicated — only "White" and "Biscuit" rendered six times each

**Severity:** High
**Where:** "Bath Walls" section.
**Description:** Copy promises "a variety of colors and patterns" but the grid renders only two options (White, Biscuit) repeated six times. Catalog/CMS bug.
**Steps:**
1. Scroll to "Bath walls come in a variety of colors and patterns…".
2. Inspect the swatch grid.
**Expected:** Distinct colour/pattern options as marketed.
**Actual:** Two options repeated; no variety.
**Attachments:** screenshot of swatch grid.

---

## 3. ZIP code field accepts inputs that violate the stated 5-digit rule  *(verify on live)*

**Severity:** Critical
**Where:** All ZIP inputs.
**Description:** Form requirement states ZIP must contain exactly 5 digits.
**Steps:**
1. Enter `1234` → submit.
2. Enter `123456` → submit.
3. Enter `abcde` → submit.
4. Enter `12 34` → submit.
**Expected:** Inline validation error on each; submission blocked.
**Actual to verify:** Any accepted value is a defect; record which.
**Attachments:** screen recording of each attempt.

---

## 4. Email field accepts malformed addresses  *(verify on live)*

**Severity:** Critical
**Where:** Email input on lead form.
**Description:** Form requirement states email must match a valid pattern.
**Steps:** Submit each in turn: `user`, `user@`, `user@site`, `@site.com`, `user@@site.com`, `user @site.com`.
**Expected:** Validation error on each; submission blocked.
**Actual to verify:** Record all accepted invalid values.
**Attachments:** screen recording.

---

## 5. Phone field does not enforce exactly 10 digits  *(verify on live)*

**Severity:** Critical
**Where:** Phone input on lead form.
**Description:** Form requirement states phone must contain exactly 10 digits.
**Steps:** Submit each in turn: `123456789` (9), `12345678901` (11), `abcdefghij`, `555-123-4567` (separators), `+15551234567` (E.164).
**Expected:** Validation error on each non-10-digit input; submission blocked. Document whether separators are stripped or rejected — both are valid product decisions, but it must be one or the other.
**Actual to verify:** Record behaviour.
**Attachments:** screen recording.

---

## 6. Required-field validation skipped on empty submit  *(verify on live)*

**Severity:** Critical
**Where:** Lead-capture form.
**Description:** Form requirement: all fields are required.
**Steps:**
1. Open the form.
2. Leave every field blank.
3. Click Submit.
**Expected:** Inline "required" error on every field; submission blocked.
**Actual to verify:** Note any field that submits empty.
**Attachments:** screenshot of post-submit state.

---

## 7. Successful submission does not redirect to a Thank-you page  *(verify on live)*

**Severity:** High
**Where:** Form submit flow.
**Description:** Spec requires redirect to a "Thank you" page on success.
**Steps:**
1. Fill every field with a valid value.
2. Submit.
**Expected:** Redirect to a dedicated Thank-you URL with a success message.
**Actual to verify:** Record landing URL and any post-submit messaging.
**Attachments:** screen recording from submit to landing page.

---

## 8. Outdated marketing copy ("Added This Walk-In Bath In 2020") on a 2026 page

**Severity:** Medium (trust, SEO freshness)
**Where:** H1-area heading.
**Description:** Heading reads "…So Many Seniors Have Added This Walk-In Bath In 2020". Footer reads "© Caps Lock, 2026". The page is six years stale.
**Steps:** Read the heading; compare to footer year.
**Expected:** Current year, or evergreen copy without a year reference.
**Actual:** "2020" on a 2026 site.
**Attachments:** screenshot.

---

## 9. Medical claims with unresolved footnote markers and missing citations

**Severity:** High (regulatory risk for health advertising)
**Where:** "Stats Show Bathroom Slips…" block.
**Description:**
- Body text contains `**` after "25 percent chance of dying within six months to a year if they fall and break a hip." but no matching footnote exists.
- Footer contains `* - According to CDC research` but no `*` marker appears in the body it refers to.
- Multiple medical claims (arthritis relief, diabetes benefits, sleep-apnea improvement) are presented without citation.
**Steps:** Read the stats block; search the page for matching footnote markers.
**Expected:** Every claim cited; every footnote marker resolves to a footnote.
**Actual:** Orphan `**`, orphan `*`, unsourced medical claims.
**Attachments:** annotated screenshot.

---

## 10. Testimonial contradicts the product's stated target audience

**Severity:** Medium (credibility, segmentation)
**Where:** Reviews block, "Review by Beverley W."
**Description:** The page targets seniors aged 65+ ("more than a third of seniors over the age of 65 slip and fall…"). The first testimonial begins "I am 38 years with diabetes…", undermining the senior pitch directly above it.
**Steps:** Read the senior-fall statistics; read the first testimonial.
**Expected:** Testimonials align with stated audience, or the audience copy is broadened to match.
**Actual:** 38-year-old testimonial on a 65+ landing page.
**Attachments:** screenshot of both blocks.

---

## Lower-priority issues (logged for completeness, not in the top 10)

- "Proudly American" badge displayed to a Nairobi audience.
- All benefit icons have alt text `icon` — screen readers cannot describe them.
- Run-on feature list with no separators: "Hydrotherapy & air jets Targeted back, leg, wrist…".
- Duplicate CTA block ("How much does it cost…") rendered twice with identical fields.
- Footer brand "Caps Lock" likely placeholder copy, not a real brand string.

---

## Notes for the triage call

- Items 3–7 are marked *(verify on live)* because I prepared this report from
  the static page copy provided in the brief. Each has explicit reproduction
  steps so verification is mechanical; if the live site enforces a rule
  correctly the item is closed as Not Reproducible without losing the audit
  trail.
- Where the same root cause likely explains multiple symptoms (form
  validation: items 3–6), I would consolidate into one parent ticket with
  sub-tasks per field rather than five separate bugs once the dev team
  confirms architecture.
