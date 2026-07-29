# aiplacement_dimensions — AI competency suggestions on the activity form

**Date:** 2026-07-29 · **Scope:** a new companion plugin `ai/placement/dimensions`, hard-depending on
`local_dimensions`. No changes to `local_dimensions` itself in this pass.

## Goal

Let a teacher editing an activity ask an AI provider which competencies from a chosen framework the
activity covers, review the suggestions, and have them pre-filled into the form's own competencies
field — then save normally.

The plugin owns exactly three things: **building the prompt, calling the model, and resolving the
model's answer to competency IDs**. Linking, searching and tree navigation are delegated to web
services `local_dimensions` already ships.

## Origin — what this replaces

This design is the outcome of a full audit of `cmu-sei/moodle-aiplacement_competency` (66 verified
findings). That plugin implements the same idea in ~1,900 non-boilerplate lines; roughly 1,650 of
them would have to be rewritten under any adoption path. The audit's conclusion was *take the idea,
rewrite the rest*. The good ideas we keep are named in "Inherited ideas" below; the concrete traps we
must not repeat are recorded in "Anti-patterns" — each one is a defect that was verified in that
codebase.

## Decisions (locked)

| Question | Decision |
|---|---|
| Primary audience | Teacher, on the activity form, **first**; Competency hub (bulk) later, same engine |
| Relationship to `local_dimensions` | **Hard declared dependency** (`$plugin->dependencies`) |
| Suggestion persistence | **Ephemeral** — apply or discard. No new tables, no provenance customfield |
| Model → competency resolution | **Numbered candidate list; the model returns indices** |
| Plugin type | `aiplacement` — the only type that inherits core_ai's action toggles, per-context opt-out and acceptable-use policy for free |
| Name | `aiplacement_dimensions` (`aiplacement_competency` is burned by the CMU frankenstyle) |

## Out of scope (future slices)

- The Competency hub tab for bulk mapping (slice 4). Same engine, different surface and target.
- Two-pass triage for frameworks that do not fit one call (slice 5, "approach C"). Shares the
  resolver, so it is not rework.
- Server-side content enrichment (pulling quiz question text into the prompt).
- Any provenance record of what the AI suggested. Explicitly rejected in this pass.

## Language

Everything committed (docs, code, commit messages) is **English**. Only the chat is bilingual.

---

## Architecture

### Division of labour

| Concern | Owner |
|---|---|
| Suggest (prompt + model + resolution) | **this plugin** — one read-only external function |
| Link competency → **course** | `local_dimensions_link_competency_course` (exists) |
| Link competency → **module** | **nobody writes it** — see "The load-bearing mechanism" |
| Search / browse competencies | `local_dimensions_search_competencies`, `_browse_structure`, `_get_structure_node` (exist) |

### The load-bearing mechanism

`tool_lp_coursemodule_edit_post_actions` (`admin/tool/lp/lib.php:178-207`) loads the module's existing
competencies (`:190`), diffs them against the submitted `$data->competencies` (`:197-200`), and
**removes everything present in the DB but absent from the form value** (`:203`).

Therefore writing the module link by web service while the form is open is *always* wrong: the user's
next Save undoes it. (This is a verified defect in the CMU plugin, which additionally fired
`window.location.reload()` right after applying.)

The mechanism we use instead:

1. The competencies field is a `MoodleQuickForm_autocomplete` with `multiple`
   (`admin/tool/lp/classes/course_competencies_form_element.php:41,86`) whose valid options are the
   **course** competencies (`:73`, `api::list_course_competencies($courseid)`).
2. So a chosen competency must first exist on the course → one **individual** call to
   `local_dimensions_link_competency_course` per competency.
3. Then we append `<option value="ID" selected>` to the hidden `<select>`.
4. The form submits the hidden select — not the visible chips — so the native Save creates the
   module link through `tool_lp_coursemodule_edit_post_actions`.

**We never write the module link.** If the user does not save, nothing was linked to the activity.
That is the correct semantics: the AI pre-fills the field, the human reviews and saves.

### Known open point (spike, slice 1)

`lib/amd/src/form-autocomplete.js` exports only `enhance` and `enhanceField` (`:1326-1336`).
`updateSelectionList` (`:152`) rebuilds the visible chips from
`originalSelect.children('option:selected')` (`:157`), but it runs on init and on user interaction —
there is **no `change` listener on the original select**. So mutating the select externally does not
redraw the chips.

This is **cosmetic, not functional**: the save works regardless, because the form submits the select.

- **Spike:** re-run `enhanceField` on the mutated select; verify it does not duplicate DOM.
- **Fallback:** a notice under the field listing what the AI added. Arguably better UX — it visually
  separates the AI's additions from what was already selected.

### New-activity support

`tool_lp` adds the competencies section on `?add=` too, with `$cmid = null`
(`admin/tool/lp/lib.php:150-157`), and `post_actions` runs after creation with `$data->coursemodule`
populated. **This design therefore works on unsaved new activities** — the case the CMU plugin had to
disable outright.

### Files

```
version.php                                dependencies on local_dimensions; no invented properties
lib.php                                    aiplacement_dimensions_coursemodule_standard_elements()
classes/placement.php                      get_action_list() → [generate_text]   (~15 lines)
classes/external/suggest_competencies.php  the ONLY external function
classes/local/prompt.php                   builds the numbered candidate list   (pure, DB-free logic)
classes/local/resolver.php                 index → id + discard accounting      (pure, DB-free)
amd/src/suggest.js                         own drawer, ~40 lines, extends nothing
db/services.php  db/access.php  lang/en  lang/pt_br  classes/privacy/provider.php
```

No `db/install.xml`, no `db/upgrade.php`, no `settings.php` — core_ai builds the action-settings page
itself (`\core\plugininfo\aiplacement::load_settings()`), exactly as `aiplacement_courseassist` does.

Entry point is `lib.php`, not a footer hook: `get_plugins_with_function('coursemodule_standard_elements', 'lib.php')`
(`lib/moodlelib.php:7210`, called from `course/moodleform_mod.php:847-848`) scans every plugin type
with a `lib.php`, so an `aiplacement` subplugin receives the callback. The button is rendered **inside**
the competencies section `tool_lp` already created, next to the field it will fill.

`prompt.php` and `resolver.php` touch neither the DB nor `core_ai`. They hold the logic the CMU plugin
got wrong, and are unit-testable without a site.

---

## Data flow

### Page load — six gates

`lib.php` renders the button and calls `js_call_amd` only when all of these hold:

1. `get_config('core_competency', 'enabled')`
2. `has_capability('moodle/competency:coursecompetencymanage', $context)` — the same gate `tool_lp`
   uses for the section itself (`admin/tool/lp/lib.php:140`)
3. `has_capability('aiplacement/dimensions:suggest', $context)`
4. `\core_ai\manager::is_action_enabled('aiplacement_dimensions', generate_text::class)`
5. at least one provider for `generate_text`
6. **`is_action_enabled_in_context($context, generate_text::class)`** (`ai/classes/manager.php:349-367`)
   — the per-course and per-activity AI opt-out

### The call

Client sends `{contextid, frameworkid, rootids[], content}`, where `rootids[]` are the IDs of the
competency subtree roots the user selected in step 2 (empty = the whole framework). `content` is the
**unsaved** editor text, capped by character budget.

Server (`suggest_competencies::execute`):

1. `validate_parameters` → `validate_context` → both `require_capability` calls
2. `is_action_enabled_in_context()`, else reject
3. acceptable-use policy backstop (`\core_ai\manager::get_user_policy_status()`,
   `ai/classes/manager.php:242`)
4. `prompt::build($frameworkid, $rootids)` returns a `$candidates` array indexed `1..N` **and** the
   prompt text derived from it. The array is the source of truth.
5. `generate_text` → `resolver::resolve($json, $candidates)`
6. return `{success, errorcode?, errormessage?, suggestions:[{id, idnumber, shortname, confidence, why}], discarded, candidatecount, truncated}`

`discarded` and `truncated` are surfaced in the UI. "The model returned 2 invalid indices" and
"40 competencies did not fit the budget" become on-screen text, never silence.

### Apply

For each checked suggestion:

- **(a)** an **individual** call to `local_dimensions_link_competency_course(competencyid, courseid)`.
  Individual, not batched: `lib/ajax/service.php:86-104` aborts the remainder of a batch on the first
  exception and `lib/amd/src/ajax.js:85-95` rejects every remaining promise with that same exception.
  A batch loses competencies silently.
- **(b)** append `<option value="ID" selected>` to the hidden select.

No page reload. No form state lost. The user can still unselect before saving.

---

## Error handling

The external function **does not throw on AI failure — it returns state**, following the core sibling
`aiplacement_courseassist`.

| Situation | What the user sees |
|---|---|
| No provider / action disabled | The button never renders — the gate is at page load, not at click |
| Policy not accepted | Policy block, not an error |
| Provider failed | Distinct message per `errorcode`; **"Try again" suppressed** when not retryable |
| Model returned invalid JSON | Not an exception: `suggestions: []` + `discarded`, with an explicit message |
| Index out of range | Counted in `discarded` and shown — never a silent drop |
| Chosen branches hold no competencies | Its own empty state, with a **defined** lang string |
| One `link_competency_course` failed | Only that row turns red; the others proceed (individual calls) |

Every referenced string must exist. See the string-existence test below.

---

## Testing

### Pure unit (no DB)

- `prompt::build` — stable numbering, deterministic ordering, budget ceiling, what survives truncation
- `resolver::resolve` — valid index, out-of-range index, duplicate index, malformed JSON, JSON wrapped
  in a code fence, missing `confidence`, empty `picks`

### PHPUnit with a site

- `suggest_competencies` invoked through **`external_api::call_external_function()`**, not `execute()`
  directly, so `execute_returns()` and the `db/services.php` wiring are exercised
- `\core\di::set()` to inject a mocked `\core_ai\manager`, the pattern core uses in
  `ai/placement/courseassist/tests/external/summarise_text_test.php:41-59`. **Real injection** — not an
  orphan stub class that nothing references
- Gates: missing capability → exception; `is_action_enabled_in_context` false → reject; policy not
  accepted → reject

### Behat

The scenario that proves the whole design:

> open → choose framework and branches → suggest (stubbed provider) → check → apply → **save the form**
> → the competency is linked to the activity

plus availability gates: button absent when the placement is disabled, absent for a role with the
capability Prohibited, absent when AI tools are disabled at course level.

### String existence

A test that statically extracts every `{{#str}}`, `get_string()` and `Str.get_string()` key from
`templates/`, `classes/` and `amd/src/` and asserts `string_exists()` for each. This exact test would
have caught three shipped defects in the CMU plugin.

### CI — from the first commit, not later

`moodle-plugin-ci` v4. Matrix PHP 8.2/8.3/8.4 × `MOODLE_501_STABLE` / `MOODLE_502_STABLE` — the floor
is 5.1, so 5.0 is not in the matrix, and `MOODLE_503_STABLE` does not exist yet (see "Version
support"). Gating jobs: `phplint`, `phpmd`, `phpcs --max-warnings 0`, `validate`, `savepoints`,
`mustache`, `grunt`, `phpunit`, `behat`. Release job carries `needs: [test]`.

`grunt` is non-negotiable and counts twice: it runs core's ESLint over `amd/src` **and** fails when
`amd/build` differs from a fresh build.

---

## Inherited ideas (from the audited plugin)

- The **course-then-module ordering**, including the non-obvious detail that a competency whose
  course-add returned `false` ("already in course") must still reach the module step — core's
  precondition (`competency/classes/api.php:1469-1472`) is *existence* of the `course_competency` row,
  not novelty of it. In this design the "module step" is step (b), appending the `<option selected>`:
  an already-in-course competency must still be appended, or the user's Save will not link it.
- **Two-stage narrowing** before prompting: choose a framework, then choose which branches are in
  scope, and send only that subtree. The right answer to the token-budget problem for large
  frameworks — implemented here on competency IDs and the full subtree, not on shortname substrings
  and not capped at one level of children.
- **Refusing to offer the feature when there is nothing to classify.**
- **Human-in-the-loop before any write**, with the correction that the modal must show the *resolved*
  competency (idnumber + shortname), not the model's raw string.
- **Classifying the unsaved editor content**, so a teacher can write a description and get suggestions
  without a save round trip. Kept — and here it is not destroyed by a reload.
- The **prompt wording**: "do not invent codes", "if you are not absolutely certain a competency
  exists, do not include it", "return an empty array if no clear matches exist". Clearly iterated
  against real models.

## Anti-patterns (each one a verified defect we must not reproduce)

| Anti-pattern | Why it fails |
|---|---|
| Round-tripping human-readable labels through rendered HTML | Prompt emits `shortname - description`, matcher looks up `idnumber` then `shortname` → on any framework where `idnumber != shortname` both exact paths are dead and everything falls to first-match-wins substring search |
| Fetching only direct children of the chosen roots | Exactly depth 2 of an arbitrarily deep tree. A three-level framework never exposes its leaves to the model, so the real competency codes can never be suggested. `prompt::build` must walk the whole subtree |
| Silently slicing the candidate list to a fixed cap | The model is told "use only competencies from the list above" while the list was truncated with no marker and no signal to the user. Hence `truncated` and `candidatecount` in the response |
| `extends` another plugin's private AMD module | `super()` dereferences markup that plugin owns and may not have rendered; nothing in core enables `aiplacement_courseassist` by default |
| Undeclared runtime dependency | No `$plugin->dependencies`, so the dependency is invisible to the admin and to uninstall |
| Reusing another plugin's global CSS selectors | `.course-assist-controls`, `.ai-drawer` are resolved document-wide by the other plugin → cross-bound handlers |
| Writing the module link while the form is open | Undone by `tool_lp_coursemodule_edit_post_actions` on the next save |
| `window.location.reload()` after applying | Discards the unsaved content that produced the classification |
| One `Ajax.call` batch for N writes | Aborts on first exception; remaining competencies silently lost and mislabelled |
| Throwing `moodle_exception` on provider failure | The real error (rate limit vs bad key vs content refusal) is discarded into `$debuginfo` |
| Skipping `is_action_enabled_in_context()` | Per-course and per-activity AI opt-outs silently ignored |
| Skipping the acceptable-use policy | Core does **not** enforce it in `manager::process_action()` — it is the placement's job |
| Declaring a capability in `db/services.php` that nothing checks | It is advisory metadata, not enforcement |
| A test stub that is never injected | `fake_placement` was dead code; the tests hit the real manager and could never pass |
| Data provider referenced by the wrong name | `#[DataProvider('templateProvider')]` vs `template_provider()` → the whole file errors and runs zero tests |
| CI that only versions and packages | Two test files were provably broken and nobody noticed, because CI never ran them |

## Roadmap

| Slice | Delivers | Done when |
|---|---|---|
| **0** | Scaffold + green empty CI + `prompt`/`resolver` with pure tests | CI green on the first PR |
| **1** | Chip re-render spike; `suggest_competencies` + mocked manager | PHPUnit covers the three gates |
| **2** | `lib.php` + drawer + apply (course link + select append) | Behat happy path passes |
| **3** | Error states, `discarded`/`truncated` in the UI, `pt_br` | String-existence test green |
| **4** | Competency hub tab (bulk), same engine | — |
| **5** | Approach C (two-pass triage) once a framework does not fit | — |

## Version support

```php
$plugin->requires     = 2025100600;                         // Moodle 5.1
$plugin->supported    = [501, 503];                         // 5.1 → 5.3
$plugin->dependencies = ['local_dimensions' => 2026072801]; // v2.0
```

`$plugin->supported` is read at `lib/classes/plugininfo/base.php:311-317` and must be an array of
exactly two **ints**, ascending — verified, not assumed.

**Why the 5.1 floor.** `is_action_enabled_in_context()` landed in MDL-85738 ("Add base support for AI
access controls", 2025-09-03), first released in `v5.1.0-beta`. It is gate 6 and is not optional:
without it the per-course and per-activity AI opt-outs are silently ignored, which is one of the
audited anti-patterns. Probing for it with `method_exists()` to widen the floor would be the same
cargo-cult defensive coding the audit flagged in the CMU plugin — so we require 5.1 honestly instead.

This is narrower than `local_dimensions` itself (`$plugin->requires = 2024100700`, Moodle 4.5). It is a
deliberate trade: correct AI governance over reach.

**Why the dependency pin.** `2026072801` is the anchor `local_dimensions` re-based on at a phase
boundary for its 2.0. Pinning at that boundary rather than `ANY_VERSION` is what makes the dependency
*honest* — the whole design consumes web services (`link_competency_course`, `search_competencies`,
`browse_structure`) that only exist from that version on, and the CMU plugin's headline defect was
exactly an undeclared dependency on another plugin's internals.

**Declared support outruns tested support.** As of 2026-07-29 only `MOODLE_500_STABLE`,
`MOODLE_501_STABLE` and `MOODLE_502_STABLE` exist upstream — there is no `MOODLE_503_STABLE` branch
yet. So CI can test 5.1 and 5.2 but not 5.3. `supported = [501, 503]` is a forward-looking claim;
the matrix must gain `MOODLE_503_STABLE` the moment that branch appears, and until then the top of the
declared range is asserted, not verified.
