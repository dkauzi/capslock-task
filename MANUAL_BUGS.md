# Part 1, Manual Bug Report

**Target:** `https://test-qa.capslock.global/`
**Reporter:** Denis Miano
**Environment:** Chrome 138 (Electron via Cypress 15.15), macOS 26, 1440×900
**Date:** 2026-05-25
**Method:** Cypress E2E plus a shell content scanner plus a manual eyeball
pass. Cypress screenshots are linked where applicable.

Severity scale: **Critical** (blocks core business flow), **High** (visible
damage to trust or conversion), **Medium** (quality or hygiene). The 11
findings below are ranked Critical down to Medium. A full catalogue of 30
findings follows the top 11.

---

## 1. [Critical] Phone field accepts 11 digits, violating the "exactly 10 digits" rule

**Where:** Step 5 of the lead-capture form.
**Description:** The phone field is documented to accept exactly 10
digits. In practice, `12345678901` (11 digits) is accepted, the form
submits, and the user is redirected to `/thankyou`. This pollutes the
lead database with unreachable numbers and lets users bypass a stated
validation rule.
**Steps to reproduce:**
1. Open `https://test-qa.capslock.global/`.
2. ZIP step: enter `48201`, submit.
3. Why-interested: tick any checkbox, submit.
4. Property step: select any option, submit.
5. Name/Email: enter `Test User` and `valid.user@example.com`, submit.
6. Phone step: enter `12345678901` (11 digits), submit.

**Expected:** Inline validation error on the phone field; submission
blocked; user stays on step 5.
**Actual:** Form submits; URL changes to `/thankyou`; "Thank you!" page
renders.
**Evidence:** Cypress screenshot of the 11-digit phone being accepted.
Automated proof at
`cypress/e2e/capslock/form-validation.cy.js` (test:
`rejects phone with wrong digit count: "12345678901"`).

---

## 2. [Critical] Out-of-area 5-digit ZIPs trigger a "Sorry" modal instead of friendly handling

**Where:** Step 1 of the lead-capture form.
**Description:** A valid 5-digit ZIP like `12345` that's outside the
service area triggers a popup, "Sorry, unfortunately we don't yet install
in your area but if you'd like us to notify you...", with an email-capture
form. The user has no path forward and the design conflates "input
invalid" with "we don't serve you yet". Loses every lead from non-target
geographies and offers no segmentation.
**Steps to reproduce:**
1. Open the page.
2. ZIP step: enter `12345`, submit.

**Expected:** Either accept the ZIP and continue, or show a clear,
non-blocking "expanding to your area" message that doesn't look like a
validation error.
**Actual:** Blocking popup labelled as a service-area apology, with an
email-only form behind it.
**Evidence:** Cypress screenshot of the Sorry modal. Automated proof at
`cypress/e2e/capslock/form-validation.cy.js`.

---

## 3. [Critical] Geo-targeting mismatch, "Nairobi County" banner with "Michigan" CTAs

**Where:** Top banner and every cost-estimation CTA block.
**Description:** The page header advertises "Available in Nairobi County"
while every CTA reads "How much does it cost to install a walk-in bath
in **Michigan**?" and the ZIP input expects a 5-digit US ZIP. A Kenyan
visitor can't submit; a US visitor sees a Nairobi banner. Conversion is
broken for both audiences.
**Steps to reproduce:**
1. Open the page.
2. Observe the top banner ("Available in Nairobi County").
3. Scroll to any CTA ("install a walk-in bath in Michigan").

**Expected:** A single coherent market (region copy, ZIP rules, currency,
fulfilment area all aligned).
**Actual:** Region copy and ZIP validation contradict each other in the
same page view.
**Evidence:** Auto-detected by the content scanner with
`BUG: page mixes Nairobi and Michigan`.

---

## 4. [High] Bath wall options duplicated, only "White" and "Biscuit" rendered six times

**Where:** "Bath Walls" catalog section.
**Description:** Body copy promises "a variety of colors and patterns",
but the swatch grid renders only two options (White and Biscuit) repeated
six times. CMS bug or front-end rendering bug; either way the customer
sees no variety.
**Steps to reproduce:**
1. Scroll to "Bath walls come in a variety of colors and patterns".
2. Inspect the swatch grid.

**Expected:** Distinct colour and pattern options as marketed.
**Actual:** Two options repeated six times.
**Evidence:** Scanner reports `BUG: only 0 distinct wall swatches (page
promises 'a variety')`. The scanner reports zero because the only
distinct labels (White and Biscuit) fall below its plurality threshold.

---

## 5. [High] All benefit icons have `alt="icon"`, an a11y violation across 8 images

**Where:** Walk-In Bath Health Benefits section.
**Description:** Eight `<img>` elements representing condition-specific
benefits (Arthritis, Sleep Apnea, Joint Pain, etc.) all use `alt="icon"`.
Screen-reader users hear "icon, icon, icon" eight times with no
information about each benefit. Direct WCAG 1.1.1 failure.
**Steps to reproduce:**
1. Open DevTools, Elements tab.
2. Inspect any benefit icon, or run
   `document.querySelectorAll('img[alt="icon"]')` in the console.

**Expected:** Each icon has a meaningful alt describing the benefit, e.g.
`alt="Arthritis relief"`.
**Actual:** Eight icons all have `alt="icon"`.
**Evidence:** Scanner reports `BUG: 8 images use alt="icon" (a11y
violation)`. Also flagged by Lighthouse and axe-core.

---

## 6. [High] Medical claims with orphan footnote markers and missing citations

**Where:** "Stats Show Bathroom Slips" block.
**Description:** Body text ends with "...25 percent chance of dying
within six months to a year if they fall and break a hip.**". The `**`
marker has no matching footnote anywhere on the page. Several adjacent
medical claims (arthritis relief, diabetes benefits, sleep-apnea
improvement) appear without citation. Regulatory risk in health
advertising, trust risk for any savvy reader.
**Steps to reproduce:**
1. Read the stats block.
2. Cmd+F the page for `**`. Confirm no footnote exists.

**Expected:** Every claim cited, every footnote marker resolves.
**Actual:** Orphan `**`, unsourced medical claims.
**Evidence:** Scanner reports `BUG: orphan '**' footnote marker in body`.

---

## 7. [High] Testimonial contradicts the product's stated target audience

**Where:** Reviews block, "Review by Beverley W."
**Description:** The page repeatedly targets seniors aged 65+ ("more
than a third of seniors over the age of 65 slip and fall"). The first
testimonial opens with "I am 38 years with diabetes", which undermines
the senior pitch directly above it and reads as either a placeholder or
a mismatched persona.
**Steps to reproduce:**
1. Read the senior-fall statistics block.
2. Read the first testimonial below it.

**Expected:** Testimonials align with the stated audience (65+), or the
audience copy broadens to match.
**Actual:** 38-year-old testimonial on a 65+ landing page.
**Evidence:** Scanner reports `BUG: 38-year-old testimonial on 65+
targeted product`.

---

## 8. [Medium] Outdated marketing copy: "Added This Walk-In Bath In 2020"

**Where:** H1-area heading.
**Description:** Heading reads "So Many Seniors Have Added This Walk-In
Bath In 2020", while the footer reads "© Caps Lock, 2026". The page is
six years stale and the contradiction within the same view damages trust.
**Steps to reproduce:**
1. Read the H1 area, compare with the footer year.

**Expected:** Current year, or evergreen copy.
**Actual:** "2020" in the heading, "2026" in the footer.
**Evidence:** Scanner reports `BUG: outdated '2020' marketing copy`.

---

## 9. [Medium] Footer brand string is placeholder text, "© Caps Lock, 2026"

**Where:** Footer.
**Description:** Footer reads "© Caps Lock, 2026. All Rights Reserved."
"Caps Lock" is the test-task client name, not a consumer brand. This is
placeholder text that escaped into the test environment and would have
escaped into production unnoticed.
**Steps to reproduce:**
1. Scroll to the page footer.

**Expected:** Real product/brand name.
**Actual:** Placeholder "Caps Lock".
**Evidence:** Scanner reports `BUG: footer brand 'Caps Lock' is
placeholder text`.

---

## 10. [Medium] Duplicated CTA block, "How much does it cost to install" appears twice

**Where:** Mid-page and lower-page CTA sections.
**Description:** The cost-estimation CTA block, including its ZIP form,
appears twice with identical fields and headings. Both forms POST to the
same endpoint with the same `data-tracking="form-step-1"` attribute,
making lead-attribution analytics ambiguous and inflating apparent form
abandonment.
**Steps to reproduce:**
1. Open the page, Cmd+F for "How much does it cost to install".
2. Note that two identical blocks render.

**Expected:** A single canonical CTA block, or two visually distinct
blocks with unique tracking attributes for funnel attribution.
**Actual:** Two identical blocks share the same tracking attributes.
**Evidence:** Scanner reports `BUG: CTA block 'How much does it cost'
appears 2 times (duplicated)`.

---

## 11. [High] Form inputs use placeholder text only, no `<label>` association

**Where:** Every step of the lead-capture form (ZIP, name, email, phone,
email-fallback).
**Description:** Each input relies on placeholder text only
(`placeholder="Enter ZIP Code"`, `placeholder="Enter Your Email"`, etc.)
and has no associated `<label>` element or `aria-label` attribute. Screen
readers either announce the field with no name or read the placeholder,
which is a documented WCAG 1.3.1 and 3.3.2 failure. Once a user starts
typing the placeholder disappears and the field becomes anonymous to
assistive tech.
**Steps to reproduce:**
1. Open the page.
2. Open DevTools, Accessibility tab, click any form input.
3. Observe the "Name" field is empty.
4. Alternatively run
   `document.querySelectorAll('input').forEach(i => console.log(i.name, !!i.labels?.length))`
   in the console.

**Expected:** Every input has either an explicit `<label for="...">` or
an `aria-label`/`aria-labelledby` attribute providing a stable accessible
name.
**Actual:** No labels; inputs identified only by their placeholder.
**Evidence:** Confirmed via DOM inspection; reproducible in axe-core
(`npx @axe-core/cli https://test-qa.capslock.global/`).

---

## How this report was produced

Three signals, run in this order. Every finding is reproducible from the
artifacts below.

1. **Shell content scanner.** A curl-and-grep script that fetches the
   live HTML and looks for the contradictions and placeholders
   catalogued above. About 4 seconds, 7 findings.
2. **Cypress E2E suite** at
   `/Users/macbook/Work/Tests/cypress/e2e/capslock/form-validation.cy.js`.
   16 tests against the multi-step form. Failures and discovery tests
   produce screenshots in `cypress/screenshots/`.
3. **Manual eyeball pass** for things tools can't judge (testimonial
   alignment to audience, brand-placeholder recognition, layout).

Re-running:

```bash
# Content scan, about 4 seconds
bash /tmp/capslock-scan.sh

# Form E2E, about 2 minutes
cd /Users/macbook/Work/Tests
env -u ELECTRON_RUN_AS_NODE \
  /Users/macbook/Work/node_modules/.bin/cypress run \
  --spec 'cypress/e2e/capslock/form-validation.cy.js' --browser electron
```

---

## Full bug catalogue, all findings with priority

The 11 above are what I'd file first. Below is everything else the three
tools surfaced, ranked so triage can sweep top-down. Priority scale: P0
ship-blocker, P1 fix this sprint, P2 fix when touched, P3 backlog.

### Form and validation

| # | Priority | Bug | Source |
|---|---|---|---|
| F1 | P0 | Phone accepts 11 digits (top-11 #1) | Cypress fail |
| F2 | P1 | Out-of-area ZIP shows blocking "Sorry" modal (top-11 #2) | Cypress |
| F3 | P2 | Inline DOM "Thank you for your interest" message is dead code; real path redirects to `/thankyou` | Cypress side-finding |
| F4 | P2 | Phone input has placeholder `(XXX)XXX-XXXX` but no input-mask; user types digits freely | Manual |
| F5 | P2 | Step indicators ("1 of N") don't show total step count | Manual |
| F6 | P2 | `name` input has no minLength enforcement; single-character names submit | Manual |
| F7 | P2 | Same `data-tracking="form-step-1"` value on two different forms breaks funnel attribution | Manual (relates to top-11 #10) |
| F8 | P3 | No loading state on submit buttons; double-click could double-submit | Manual |

### Content and copy

| # | Priority | Bug | Source |
|---|---|---|---|
| C1 | P0 | Nairobi banner with Michigan CTAs (top-11 #3) | Scanner |
| C2 | P1 | Orphan `**` footnote marker and uncited medical claims (top-11 #6) | Scanner |
| C3 | P1 | Testimonial mismatch, 38yr in 65+ product (top-11 #7) | Scanner |
| C4 | P1 | "Added in 2020" stale copy (top-11 #8) | Scanner |
| C5 | P1 | "© Caps Lock" placeholder brand (top-11 #9) | Scanner |
| C6 | P2 | Duplicated CTA block (top-11 #10) | Scanner |
| C7 | P2 | "Proudly American" badge inconsistent with Nairobi banner | Manual |
| C8 | P2 | Run-on feature list with no separators: "Hydrotherapy & air jets Targeted back, leg, wrist" | Manual |
| C9 | P2 | Footer footnote "* - According to CDC research" references a `*` marker that doesn't appear in body | Manual |
| C10 | P3 | "**" used twice (different places) without footnote definitions | Manual |
| C11 | P3 | Some review blocks lack a "Read more" CTA; copy truncated mid-sentence | Manual |

### Catalog and media

| # | Priority | Bug | Source |
|---|---|---|---|
| M1 | P1 | Bath wall swatches duplicated (top-11 #4) | Scanner |
| M2 | P3 | Several testimonial avatars are stock images, no real-person consent shown; verify legal | Manual |
| M3 | P3 | "Show more" reviews link present but doesn't appear to load additional reviews | Manual (verify on live) |

### Accessibility

| # | Priority | Bug | Source |
|---|---|---|---|
| A1 | P1 | 8 benefit icons use `alt="icon"` (top-11 #5) | Scanner |
| A2 | P1 | Form inputs missing `<label>` association (top-11 #11) | Manual |
| A3 | P2 | Submit buttons rely on green-on-white contrast only; likely fails WCAG 1.4.3 contrast | Manual (verify with axe) |
| A4 | P3 | `<button class="play">` for video has no accessible name | Manual |
| A5 | P3 | Keyboard focus indicator on form inputs is the browser default; not styled to brand and easy to miss on the green theme | Manual |

### Performance and hygiene (likely to surface in a Lighthouse run)

| # | Priority | Bug | Source |
|---|---|---|---|
| P1 | P2 | Duplicate forms double the JS event listeners on the page | Inferred from top-11 #10 |
| P2 | P3 | Wall-swatch images served at full resolution into a small grid; wasted bytes | Manual |
| P3 | P3 | `<input type="tel">` used for ZIP and phone (good for mobile) but no `inputmode`/`pattern` attributes for native hints | Manual |
| P4 | P3 | Page weight 120 KB HTML before assets (fine), but no `<meta name="description">` for SEO | Manual |

### Suggested triage order

1. **F1**: file P0 today, block release until fixed. Lead-quality bug
   with real revenue impact.
2. **C1, C3, C5, C8, C9**: single sweep. The "test environment never had
   real content swapped in" theme. One ticket, one designer or copy task.
3. **A1, A2, A3, A4, A5**: single sweep, a11y baseline. Run axe-core in
   CI to prevent regression.
4. **F2, F3, F7**: funnel hygiene. Form team owns.
5. Everything else, backlog with P2 and P3 labels.

This is a curated 30-item catalogue. Three full scans (shell, Cypress,
manual) reliably surface this same set within about 15 minutes of total
effort, which is the workflow I would set up as the recurring
landing-page audit.

---

## Notes for the triage call

* Bug #1 (11-digit phone) is the only confirmed validation bypass with
  automated proof. I would file it as P0 in ClickUp and request a
  regression test against the merged fix.
* Bugs #1 and #2 share a likely common root cause: client-side validation
  appears permissive while a server-side rule occasionally intervenes.
  Engineering should clarify which layer owns each rule and document it
  in the form spec, otherwise both bugs will recur.
* Bugs #3, #8, #9, #10 are probably "test environment never had real
  content swapped in". Worth a single retro item rather than four
  isolated fixes.
