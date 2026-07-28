# Field map — `MOD.RULE` · Completion rule (as-is)

Editor for a competency's completion rule, opened from `EST-DETAIL-RULES` — the **Competency rule**
button in the **sticky footer** of the Competencies tab. Three cascading controls: **outcome** (what
happens on completion), **rule type** and, for the **points** rule only, a table of points per child
with a required total. The modal **does not talk to the server**: it receives the children and the
rule types ready-made from `structure.js`, and resolves a `{ruletype, ruleoutcome, ruleconfig}`
object that **the caller** persists.

- **Mustache:** [`rule_config.mustache`](../../../templates/central/rule_config.mustache) (98) ·
  trigger in [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache)
  (`:49-52`)
- **AMD:** [`rule_config.js`](../../../amd/src/central/rule_config.js) (186) — `show` at `:138-186`,
  `readPointsConfig` at `:115-128`, `buildContext` at `:82-107`. Imported by
  [`structure.js`](../../../amd/src/central/structure.js) (`:37`), which opens it (`:896`) and persists (`:897`)
- **WS: none.** This module does not call `Ajax` — a `grep -n "core/ajax" amd/src/central/rule_config.js`
  returns **nothing**. The two writes (`core_competency_read_competency` + `core_competency_update_competency`)
  belong to `structure.js`, in `persistRule` (`:848-880`), and are **core's**: the modal has no entry in
  `db/services.php`
- **Screen in the DS:** [`screens/mod-rule.html`](../screens/mod-rule.html) — single panel, with the
  driven error and the real title

**Abbreviations used in the tables:** `js:` = `amd/src/central/rule_config.js` · `mustache:` =
`templates/central/rule_config.mustache` · `structure.js:` = `amd/src/central/structure.js`.
Paths starting with `admin/` or `lang/` are **core's**, relative to `public/`.

## Two conventions this map fixes

- **The Mustache refs for this modal do not rot, and that is not the map's doing.**
  `rule_config.mustache` **has not been touched since `a78c3f6` (2026-06-27)**, the commit that created
  the modal — a `git log --oneline -- templates/central/rule_config.mustache` returns **one** line. A
  ref pointing at a frozen file stays correct on its own; what ages here is the `.js`
  (`rule_config.js` changed in `d343716`) and `structure.js`, which grows underneath.
- **The trigger gets no new ID here.** [`est-competencies.md`](est-competencies.md) already maps
  `EST-DETAIL-RULES` at `structure_footer_actions.mustache:49-52` and already says "opens `MOD.RULE`".
  This map **references** it instead of coining a `MOD.RULE-ACTION` — the convention `MOD.DELPLANS` ←
  `PLN-DELETE` established.

The kit's reference shell for this modal is [`modal-shell.html`](../modal-shell.html). It is **not**
[`paginated-picker.html`](../paginated-picker.html): a `grep -in 'rule' docs/design-kit/paginated-picker.html`
returns **zero** across 136 lines — that card is *"Search picker · Server-side search + AJAX results +
overflow warning"* and carries neither a rule nor an outcome.

## Trigger (on the Competencies tab, outside the modal)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `EST-DETAIL-RULES` | Competency rule | button (trigger) | `structure_footer_actions.mustache:49-52` — ID from [`est-competencies.md`](est-competencies.md) | `data-action="rules"` · `fa fa-list` | str `competencyrule, tool_lp` — **the same str as the modal title** (`js:140`), so the button and the dialogue it opens carry an identical label. Same pattern as `PLN-DELETE`/`MOD.DELPLANS-TITLE`, and here it is literally the same key on both sides. Lives in the tab's shared **sticky footer**, not in a row. `structure.js:888-899` (`showRuleConfig`) reads the four `data-*` of the active row (`:890-893`), fetches the children (`:895`) and calls `show(competency, children, rulesModules)` (`:896`) |
| `EST-JSON-RULES` | `[no label]` | JSON data | `structure.mustache:95` — ID from [`est-competencies.md`](est-competencies.md) | `data-region="rules-modules"` | **the rule types come from the server, not from the JS**: `readJson` (`structure.js:124-134`) reads them at init (`:1354`) and the array travels through `show` into `buildContext` (`js:97-101`). A new type in core shows up here without touching the AMD |

## Modal shell

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RULE-TITLE` | Competency rule | title | `js:140` (str), `:144` (`ModalSaveCancel.create`) | str `competencyrule, tool_lp` | `admin/tool/lp/lang/en/tool_lp.php:71` = `'Competency rule'`. **No competency name**: `create` receives `{title, body}` (`:144`) and `title` is the **raw** string — nothing concatenates the target, unlike `MOD.LINKS-TITLE`, which takes `$a`. `ModalSaveCancel` (import `:28`), so the footer already ships Cancel + Save changes and there is no `setSaveButtonText`. `setRemoveOnClose(true)` at `:145` |
| `MOD.RULE-ROOT` | `[no label]` | region/root | `mustache:51` | `data-region="rule-config"` · `.local-dimensions-rule-config` | body wrapper. **The module's five `querySelector` calls (`js:147-151`) start from the _modal_ root** (`modal.getRoot()[0]`, `:146`), not from this node — `data-region="rule-config"` is, today, a hook with no reader: a `grep -rn 'rule-config' amd/src/ styles.css` outside `build/` returns only docblock prose, no selector. The class has no rule either: `grep -n 'rule-config' styles.css` returns **zero**. Recorded, not removed |
| `MOD.RULE-SAVE` | Save changes | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="save"` | comes free with `ModalSaveCancel`. **It is the modal's only validation point** — see "The validation" |
| `MOD.RULE-CANCEL` | Cancel | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="cancel"` | closing via Cancel, via the X or via ESC all lands on `ModalEvents.hidden` (`js:183`) → `resolve(null)` → `structure.js:897` sees `null` and **does not persist** |

## Body — the cascade

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.RULE-OUTCOME-LABEL` | Outcome | label | `mustache:53` | str `outcome, tool_lp` · `for="local-dimensions-rule-outcome"` | a real `<label>`, with `for` |
| `MOD.RULE-OUTCOME` | Outcome (select) | select | `mustache:54` | `data-region="outcome"` · `.form-select` | **the top of the cascade**. 4 options, from `OUTCOMES` (`js:37-42`): `0` None · `1` Attach an evidence · `3` Recommend the competency · `2` Mark as complete — **the array order is not the numeric order** (`0,1,3,2`), and the array order is what the user sees. The labels are **core** strs (`competencyoutcome_*`, `tool_lp:66-69`), fetched in one batch (`getStrings`, `js:139`) and paired **by index** (`js:92-96`), not by key — reordering `OUTCOMES` and nothing else stays correct, but swapping `getStrings` for a batch in another order would silently scramble the labels. `.form-select` (never `custom-select`) |
| `MOD.RULE-TYPE-WRAP` | — | wrapper | `mustache:60` | `data-region="ruletype-wrap"` | born `hidden` under `{{^hasrule}}` — `hasrule` is `ruleoutcome !== 0` (`js:87`). After the render, `refresh` (`js:155`) is in charge |
| `MOD.RULE-TYPE-LABEL` | **Rule** | label | `mustache:61` | str `central_rule_type` · `for="local-dimensions-rule-type"` | `lang/en:287` = `'Rule'`; `lang/pt_br:287` = `'Regra'` — **that is the string, not "Rule type"**, which is the key paraphrased. The string is the **plugin's**, not `tool_lp`'s: core exposes no equivalent |
| `MOD.RULE-TYPE` | Rule (select) | select | `mustache:62` | `data-region="ruletype"` · `.form-select` | options from `EST-JSON-RULES`, via `rulesModules` (`js:97-101`); `value` = the core **class** (`core_competency\competency_rule_all`, `…_points`). Only `…_points` (`RULE_POINTS`, `js:34`) opens the table |
| `MOD.RULE-ERROR` | The total available points must be at least the required points. | **inline** alert | `mustache:68-70` (node), `js:178` (turns it on), `js:158` (clears it) | `data-region="error"` · `role="alert"` · `.alert-danger` | str `central_rule_invalidpoints` (`lang/en:286`, `lang/pt_br:286`). **Always** born `hidden` (`showerror` is pinned to `false`, `js:89`). Turned on only on an invalid save (`js:178`) and cleared by `refresh` (`js:158`) together with the points table it describes — see "The validation". It is a sibling **above** `MOD.RULE-POINTS` (`mustache:68-70` vs `:71`), not a child: that is why hiding the table never takes the message with it through markup. **Inline** idiom; its sibling `MOD.SCALE` uses a popup `Notification.alert` for the same role |
| `MOD.RULE-POINTS` | — | wrapper | `mustache:71` | `data-region="points"` | born `hidden` under `{{^ispoints}}` — and **`ispoints` (`js:88`) does not look at `hasrule`**, while `refresh` (`js:156`) looks at both. See "The first-render divergence" |
| `MOD.RULE-POINTS-TABLE` | points table | table | `mustache:73` | `.table.table-sm` | only under `{{#haschildren}}` (`:72`) = `children.length > 0` (`js:90`). **A leaf competency has no table** — the wrapper is there but empty, and a save with the points rule falls into `requiredpoints < 1` (there is no input) → error. See "The validation" |
| `MOD.RULE-POINTS-HEAD` | `[col 1 unlabelled]` · Points · Required | header | `mustache:74-79` | strs `points, tool_lp` (`:77`) / `required` (`:78`) | the 1st column (the child's name) is an **empty** `<th scope="col">` (`:76`). `required` is **core's** (`lang/en/moodle.php:1848`), with no component; `points` is `tool_lp`'s (`:183`) |
| `MOD.RULE-POINTS-ROW` | one row per child | row | `mustache:83-87` | `data-competency="{{id}}"` · `input[name=points]` · `input[name=required]` | name in a `<th scope="row">` (`:84`); `points` is a `number` with `min="0"` (`:85`); `required` is a checkbox (`:86`). **The whole contract with the AMD is the `data-competency`/`name` pair** (`js:118-121`) — no class and no id in between |
| `MOD.RULE-POINTS-TOTAL` | Total required to complete | row | `mustache:89-93` | str `totalrequiredtocomplete, tool_lp` · `input[name=requiredpoints]` | `tool_lp:272`. A `number` with `min="1"` (`:91`) — **and the HTML `min` protects nothing here**: there is no form submit for the browser to validate, the save is a modal button. What blocks `0` is `js:124`. It is the table row that is **not** a child: it sits inside the same `<tbody>`, with an empty 3rd cell (`:92`) |

The Mustache `showerror` is **never** `true`: `buildContext` (`js:89`) pins it to `false` and nothing
else writes it, so `{{^showerror}}hidden{{/showerror}}` (`mustache:68`) **always** renders `hidden`.
The variable is not server state — it exists only to seed the attribute, and what turns the alert on
is the JS at runtime (`js:178`).

## The validation — the only point where the modal says "no"

`readPointsConfig` (`js:115-128`) is called **only** on the points branch (`js:175`) and is the only
thing that can return `null`:

```
const total = competencies.reduce((sum, comp) => sum + Math.max(0, comp.points), 0);   // js:123
if (requiredpoints < 1 || total < requiredpoints) { return null; }                      // js:124
```

**Two branches, not one:**

1. **`requiredpoints < 1`** — the required total is `0` (or empty: `Number(input.value || 0)`, `js:117`).
   The markup's `min="1"` does not stop someone typing `0`. **It is also the leaf-competency branch**:
   without `{{#haschildren}}` there is no `[name="requiredpoints"]` in the DOM, `js:116-117` falls
   through the ternary and `requiredpoints` becomes `0` → error. The user sees an alert about points in
   a modal **with no points table**.
2. **`total < requiredpoints`** — the children's sum does not reach the required total. The
   `Math.max(0, …)` (`:123`) discards a negative point value before summing, so the `min="0"` on
   `MOD.RULE-POINTS-ROW` is redundant as a guard too.

The invalid path does **two** things and nothing else (`js:176-179`):

```
event.preventDefault();     // js:177 — holds the modal open
errorEl.hidden = false;     // js:178 — turns the inline alert on
```

The `preventDefault()` is what stops core's `registerCloseOnSave` from destroying the dialogue — the
exact counterpoint to `MOD.DELPLANS`, where the **absence** of that call makes the modal close before
the write returns. Here the state has to survive, and it does.

### What clears the alert, and why it clears **conditionally**

`refresh` (`js:153-159`) ends by taking the alert down together with the table it describes:

```
errorEl.hidden = errorEl.hidden || pointsEl.hidden;   // js:158
```

**Both** paths that remove the alert's subject hide the table — outcome = **None** and rule type ≠
points, the two branches of `js:156` — so taking it down off `pointsEl.hidden` covers both without a
second test.

**The `errorEl.hidden ||` is the decision, not a writing detail.** While the table is on screen the
alert **is still true**: switching between two **real** outcomes (e.g. *Attach an evidence* → *Mark as
complete*) keeps the table visible and leaves the same invalid points in place. An
`errorEl.hidden = true` at the top of `refresh` — the "one-line fix" — would erase a verdict that
**still holds**, making an invalid form look clean. Anyone about to simplify this line has to come
through here first.

A `grep -n 'errorEl\|showerror'` in the module returns **4** lines and **two** of them write: `js:178`
turns it on, `js:158` clears it. Until `d343716` (2026-07-15), `js:178` was the **only** write to
`errorEl` in the file, and the alert stayed lit until the modal was destroyed — including after the
outcome became **None** and the whole points table vanished. Saving in that state **worked** (the
`outcome === 0` branch, `js:166-169`, resolves before any validation) and the modal closed with the
alert still lit. The fix is a `.js` one because the alert is a sibling **above** the points region
(`mustache:68-70` vs `:71`), not a child of it: no markup would take the message with it.

## The three outcomes of the save

The handler (`js:164-182`) listens on `ModalEvents.save` and has **three** exits, in this order:

| Order | Condition | Resolves | Note |
| --- | --- | --- | --- |
| 1 | `outcome === OUTCOME_NONE` (`js:166`) | `{ruletype: null, ruleoutcome: 0, ruleconfig: null}` | **clears the whole rule** — `ruletype` goes to `null` along with the outcome, even if the type select is showing "Points required are met". It is what stops a `ruletype`-without-`ruleoutcome` state from being born here |
| 2 | `ruletype !== RULE_POINTS` (`js:171`) | `{ruletype, ruleoutcome: outcome, ruleconfig: null}` | the "all children" rule and its like carry no config — `ruleconfig` **is** `null`, not `'{}'` |
| 3 | points (`js:175`) | `{ruletype, ruleoutcome, ruleconfig: <json>}` or **error** | the JSON is `{base: {points: requiredpoints}, competencies: [...]}` (`js:127`) — core's format, hand-built |

The receiver is `structure.js:897`, which calls `persistRule` only when the value is **not** `null`
(cancel) — and `persistRule` (`:848-880`) reads the whole competency from core (`read_competency`,
`:850`), resends **every** field with the three rule ones swapped (`:854-867`), and then **writes the
state onto the tree row + flashes it**, without reloading the pane (`:873-876`; the comment at `:872`
says exactly that). It is the same toast+flash pair as `MOD.LINKS`.

## The first-render divergence

The Mustache and `refresh` **do not use the same rule** for the points table:

| Who | Rule | Ref |
| --- | --- | --- |
| Mustache (1st render) | `{{^ispoints}}hidden` → visible when `ruletype === points` | `mustache:71`, `js:88` |
| `refresh` (everything else) | `!(hasrule && ruletype === points)` → visible when **both** hold | `js:156` |

`refresh` **does not run at init** — it is only bound to the `change` of the two selects (`js:160-161`).
So a competency with `ruletype = points` **and** `ruleoutcome = 0` would open with the points table
visible and the type select hidden, until the first `change`. **The path is narrow**: save outcome 1
(above) zeroes both together, so the pair is not born through this modal. Recorded as a divergence
between two sources of the same truth, not as an observed bug. **It is a `.js` matter, not a layout
one** — no rearrangement of the modal body closes it.

## The driven error on the screen

The two controls at the foot of the panel are real (`<input type="checkbox">` + `:has()`, the
`mod-delplans` precedent, **no JS**): the first switches the required total to 3, paints the field in
danger and turns the alert on — the `total < requiredpoints` branch of `js:124`; the second hides the
rule, the table **and the alert with them**, which is what `refresh` does with outcome = None
(`js:158`).

## Contrast — measured on the alert the screen draws

The screen's `MOD.RULE-ERROR` is its `:root`'s `--text-danger`/`--bg-danger` pair
(`#a32d2d`/`#fcebeb` in light, `#f09595`/`#2a1313` in dark). Measured in the DOM with the alert **on**
(the state `js:178` produces), animations cancelled before reading:

| Pair | Theme | Ratio | Verdict |
| --- | --- | --- | --- |
| `#a32d2d` on `#fcebeb` (alert text) | light | **6.13:1** | passes |
| `#f09595` on `#2a1313` (alert text) | dark | **7.86:1** | passes |
| `#fcebeb` on `#fff` (fill) | light | **1.15:1** | decorative ink — see below |
| `#f09595` on `#fff` (border) | light | **2.23:1** | fails 3:1 (non-text) |
| `#2a1313` on `#26252a` (fill) | dark | **1.15:1** | decorative ink |
| `#791f1f` on `#26252a` (border) | dark | **1.47:1** | fails 3:1 |

**The surface has to be read from the painted ancestor, not from the parent.** The alert's immediate
parent (`.m-body`) is `rgba(0, 0, 0, 0)` — measuring against it returns `18.22:1` / `9.44:1`, numbers
that **look excellent and mean nothing** (transparent read as black). Climbing to the first ancestor
with a real fill (`.m` = `#fff`) yields the `1.15` / `2.23` in the table. Recorded because the wrong
reading is silent and flattering.

**The weak fill and border are not a failure here:** what carries the meaning is the **text** (which
passes in both themes) plus the `role="alert"` (`mustache:68`), which hands it to the screen reader
with no colour at all. The fill is reinforcement. The weak borders are the kit's known case
(`--border-strong`/`--border-stronger` fail 3:1 on every recent surface) and are **not** fixed here.

> **The six pairs above hold for the danger trio the screen uses today.** If the screen's `:root`
> migrates to the Moodle palette trio (`--text-danger:#ca3120` / `--bg-danger:#f4d6d2` in light,
> `#df8379` / `#51140d` in dark), the whole table changes — the light text drops to **3.88:1** and
> **fails** 4.5:1. Re-measure in the same pass that swaps the tokens.

## Pending — the one-sentence summary and the side-by-side pair

**Not built.** There is no mockup either: the three clauses are recorded with the place that was
looked at, on this date.

- **Outcome and rule side by side.** `mustache:52-59` and `:60-67` are still two **stacked**
  `div.mb-3`; there is no row, grid or wrapper joining the two controls.
- **The rule as a sentence** ("needs N of M points"), computed from the same `total` that `js:123`
  already sums. No such string exists for this modal: the plugin's only points keys are
  `central_rule_invalidpoints` (`lang/en:286` — the error message) and `rules_sr_progress`
  (`lang/en:561` = `'{$a->earned} of {$a->total} points earned'`), which is a screen-reader label on
  the **learner's Rules tab**, not on `rule_config`. The `total` at `js:123` only feeds the validation
  and is never rendered.
- **"When" instead of "Rule"** — the outcome answers *what*, the rule answers *when*. The label's only
  key is `central_rule_type` (`lang/en:287` = `'Rule'`, `lang/pt_br:287` = `'Regra'`); there is no
  `central_rule_when` and no "When"/"Quando" string for this control.
