# Enrolment methods tab — stronger method differentiation

Design for improving how the **Enrolment methods** tab of the *Manage participants*
modal (Learning plans tab, Competency hub) tells the two methods apart —
**Cohort sync** vs **Self enrolment**.

Date: 2026-07-18 · Status: approved (design), pending implementation.

## Problem

The tab is **mode-based**: the method segment (Cohort sync | Self enrolment) picks
one method, and the whole surface — status pill, assigned role, enable/disable
toggle, Apply/Remove buttons — reflects only that one. Each row carries both
methods' state in `data-*`; only the selected one is painted, and switching the
segment repaints client-side.

The weakness the user reported: the **status pills say a generic "Configured" /
"Not configured"** and the **action buttons say a generic "Apply method" /
"Remove method"**, so nothing at the point of reading (the pill) or the point of
acting (the button) restates *which* method you are looking at or about to change.

Layout fact that shapes the fix: `.local-dimensions-enrol-tree` scrolls **internally**
(`max-height: 50vh; overflow-y: auto`, `styles.css:5961`). The config bar (segment
+ role select + the prose hint that already describes the active method) and the
action footer **never scroll away** — only the course list does. So the active
method is always on screen via the segment; a separate sticky "context anchor"
would only duplicate it.

## Decisions

- **Drop the sticky context anchor.** Redundant with the always-visible config bar,
  and avoids `position: sticky` fragility inside a modal.
- **Compact action-button label:** `Apply · <method>` (not `Apply method: <method>`) —
  fits the footer, the icon reinforces.
- **Keep the status pill *text* as the status word** ("Configured" / "Not configured" /
  "Processing"); differentiate the method with an **icon**, not longer text. This keeps
  the status column narrow **and** keeps the Behat assertion green (see CI notes).

## Design — a shared per-method icon language

`fa-users` = Cohort sync · `fa-user-plus` = Self enrolment. The same icon appears on
the segment, the status pill, and the action buttons, so the eye ties
status ↔ method ↔ action. Method names in words appear where there is room
(buttons, remove confirmation, details modal).

| Surface | Change |
| --- | --- |
| Method segment | Static method icon on each segment button (fixed per button). |
| Status pill | Method icon before the status word; icon flips when the segment switches. Pill text unchanged. |
| Footer buttons | `Apply · <method>` / `Remove · <method>` with the method icon; set by JS on init and on method switch. |
| Remove confirmation | Body names the method: "Remove **Cohort sync** from N course(s)? …". Title stays static (Behat matches the dialogue by title). |
| Details modal | Method icon on the two method-row labels (both always shown), for consistency. |

## String changes (`lang/en` + `lang/pt_br`, alphabetical, kept in sync)

New:

- `central_enrol_apply_method` = `Apply · {$a}` / `Aplicar · {$a}`
  (inserted between `central_enrol_apply` and `central_enrol_apply_queued`)
- `central_enrol_remove_method` = `Remove · {$a}` / `Remover · {$a}`
  (inserted between `central_enrol_remove` and `central_enrol_role`)

`{$a}` is the method display name, reusing `central_enrol_method_cohort` /
`central_enrol_method_self`. The mustache keeps the existing generic
`central_enrol_apply` / `central_enrol_remove` as the pre-hydration fallback label.

Changed (scalar → object placeholder):

- `central_enrol_confirm_remove`
  - EN: `Remove {$a->method} from {$a->count} course(s)? Users enrolled through it are unenrolled according to the method's settings.`
  - PT: `Remover {$a->method} de {$a->count} curso(s)? Os usuários inscritos por ele são desmatriculados conforme a configuração do método.`

## File-by-file

- `templates/central/enrol_methods.mustache` — static `fa-users` / `fa-user-plus` on the
  two segment buttons; the Apply/Remove buttons get an icon `<i>` + a text `<span>`
  (`data-region`) so JS can update icon class + label without rebuilding innerHTML.
- `templates/central/enrol_detail.mustache` — icon before the cohort/self method labels.
- `amd/src/central/enrol_methods.js`
  - `methodIcon(method)` helper → `fa-users` | `fa-user-plus`.
  - `makeRow` — the pill wraps an icon `<i data-region="row-status-icon">` plus a
    `<span data-region="row-status-text">` (instead of setting `pill.textContent` directly).
  - `paintRow` — set the pill icon class (from the current method) alongside the existing
    colour class, and write the status word into the text span.
  - `setActionLabels(state)` — `getString` the two `_method` strings with the current
    method name, set the Apply/Remove button icon + text; call from `init` (after main is
    revealed) and from `applyMethodChange`.
  - `queueAction('remove')` — pass `{method: <name>, count: courseids.length}` to
    `central_enrol_confirm_remove`.
- `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php` — the strings above.
- `styles.css` — expected none (icons use the `me-1` utility); run stylelint regardless.

## CI / build notes

- **Behat: no `.feature` change.** `tests/behat/manage_enrol_methods.feature:55` asserts the
  pill text `"Not configured"`, which is unchanged. The Apply/Remove labels and the remove
  confirmation are not asserted. Keeping the pill *text* as the status word is what keeps
  this green.
- **No `version.php` bump.** Version is frozen until 2.0; this adds no web service and no
  structural change — only strings, JS, templates. New strings load after the test site's
  cache purge on zip install.
- **Build:** `npx grunt amd --root=public/local/dimensions` from the Moodle root, then
  `npx eslint --max-warnings 0 public/local/dimensions/amd/src` and
  `npx stylelint --config .stylelintrc public/local/dimensions/styles.css`. Ship the rebuilt
  `.min.js` + `.map` in the same commit.

## Out of scope (mentioned, not doing unless asked)

- Sticky `thead` inside the tree (keeps column labels visible on long lists).
- Column header naming the method (adds width + Behat churn for little gain over the pill icon).
- Showing both methods' status simultaneously (contradicts the locked single-method design).

## Risk / verification

Runtime behaviour (icon flip on segment switch, button relabel, remove-confirm wording) is
verified on the user's test site via the traceable zip — this checkout has no Moodle install.
