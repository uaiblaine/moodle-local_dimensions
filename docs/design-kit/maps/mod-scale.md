# Field map — `MOD.SCALE` · Framework scale/proficiency (as-is)

Editor for a scale's proficiency configuration: **one row per scale value**, with a "default" radio
and a "proficient" checkbox. It is a **bespoke `core/modal_save_cancel` modal** (zero YUI), opened by
the **Configure scale** button that `framework_dynamic_form` draws — that is, **a modal on top of a
`ModalForm`**. It reads the scale values from core and resolves core's `scaleconfiguration` JSON
(`[{scaleid}, {id, scaledefault, proficient}, …]`), which **the caller** writes into a hidden field of
the form.

- **Mustache:** [`framework_scaleconfig.mustache`](../../../templates/central/framework_scaleconfig.mustache)
  (51, the rows only — **not** the modal shell) · trigger in
  [`framework_dynamic_form.php`](../../../classes/form/framework_dynamic_form.php) (`:191-195`), as a
  **raw HTML string** in a `static` element
- **AMD:** [`framework_scaleconfig.js`](../../../amd/src/central/framework_scaleconfig.js) (155) —
  `open` at `:125-155`, `serialize` at `:56-68`, `isComplete` at `:76-90`, `parseExisting` at `:40-47`,
  `buildRows` at `:99-116`. Wired by [`frameworks.js`](../../../amd/src/central/frameworks.js):
  `openScaleConfigForForm` (`:62-86`) and `setupScaleConfigDelegation` (`:95-123`)
- **WS: one, core's.** `core_competency_get_scale_values` (`js:129-132`). **No plugin WS** — the modal
  has no entry in `db/services.php`
- **Screen in the DS:** [`screens/mod-scale.html`](../screens/mod-scale.html) — single panel, with the
  real title and the driven validation

**Abbreviations used in the tables:** `js:` = `amd/src/central/framework_scaleconfig.js` · `mustache:` =
`templates/central/framework_scaleconfig.mustache` · `frameworks.js:` =
`amd/src/central/frameworks.js` · `form.php:` = `classes/form/framework_dynamic_form.php`.

## Three things this map fixes before the tables

- **`MOD` stands for modal, and it is literal.** `js:139` is
  `const modal = await ModalSaveCancel.create({title, body: html})`: the rows are the **body of a
  native modal**, not content injected into the form. `framework_scaleconfig.mustache` hands over only
  the rows because the **shell is core's** — hence the easy (and wrong) reading that this would be a
  chunk of form. The reference shell in the kit is [`modal-shell.html`](../modal-shell.html), **not**
  [`form-section.html`](../form-section.html), which is the card *"Form section · Heading + description
  + field rows"*.
- **It is a modal _on top of another modal_.** `FWK-NEW` and `FWK-ROW-EDIT`
  ([`fwk-structures.md`](fwk-structures.md)) open `framework_dynamic_form` in a `ModalForm`
  (`frameworks.js:28`, `:172`); the **Configure scale** button lives **inside** that form and opens
  this `ModalSaveCancel` above it. That is why the wiring is delegated on `document` in the **capture**
  phase — see "The trigger".
- **This modal's refs do not rot, and that is not the map's doing.** Neither
  `framework_scaleconfig.mustache` nor `framework_scaleconfig.js` has been touched since `283e9a7`
  (2026-06-28), the commit that created the modal — a `git log --oneline` on each returns **one** line.
  What ages in the "scale" subject is `framework_dynamic_form.php` and `frameworks.js`, which grow
  underneath.

## The trigger — born in the form, and the form has a map of its own

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-ACTION` | Configure scale | button (trigger) | `form.php:191-195` | `data-action="configure-scale"` · `.btn.btn-secondary.btn-sm` | str `central_frameworks_configurescale` — **the same str as the modal title** (`js:135`), as in `MOD.RULE`/`EST-DETAIL-RULES`. **Provisional ID**: by house convention the trigger belongs to the surface it lives on, and it lives in the body of `framework_dynamic_form`. **Migrated** to `FORM-FWK-SCALE-ACTION` in [`mod-forms.md`](mod-forms.md), where the form body is mapped; this modal keeps the **child** the button opens |
| `MOD.SCALE-SUMMARY` | Configured / `[empty]` | text | `form.php:194` (node), `:189-190` (initial value) | `data-region="scaleconfig-summary"` · `.text-muted.small.ms-2` | str `central_frameworks_scaleconfigured` = "Configured" (`lang/en:159`). **It is the only thing `$configured` changes.** `form.php:195` adds the button **unconditionally** — `$configured` (`:189`, via `helper::scaleconfig_is_complete`) only picks the summary text (`:190`). Consequence: **the button exists on the create path**, not only on the edit one, and this modal **does not depend on the sticky footer** in any way. After the save, what writes the summary is `frameworks.js:77-82` |
| `MOD.SCALE-HIDDEN` | `[no label]` | hidden field | `form.php:186-187` | `name="scaleconfiguration"` · `PARAM_RAW` | **the modal's real destination.** `open` resolves a string and `frameworks.js:76` writes it here; it is the form that persists, on its own save. If the user closes the `ModalForm` without saving, the chosen configuration **is lost** — the scale modal writes nothing to the server |

**The wiring is global, and the comment in the code says why.** `setupScaleConfigDelegation`
(`frameworks.js:95-123`) registers **once** (`scaleconfigwired`, `:96-99`) a listener on `document` in
the **capture** phase (`:107`, the `true`). The docblock (`:90-91`) and the capture comment
(`:100-101`) explain it: the form renders inside a `ModalForm` whose life cycle **does not run the
plugin's `init`**, and capture guarantees the click is seen on the way down, before anything inside the
modalform stops it.

**The selectors go by `name`, never by `id`** (`frameworks.js:65-67`), and the comment at `:63-64`
gives the reason: `core_form\dynamic_form` suffixes ids with a random string
(`id_scaleid_c5fLCIS8ExDrcVf`), so a fixed `#id_scaleid` would never match. The same finding is in
`fwk-structures.md`, in the "Business rules" section.

**Changing the scale wipes the configuration — unless the select is frozen.** The `change` handler
(`frameworks.js:108-122`) clears `MOD.SCALE-HIDDEN` and `MOD.SCALE-SUMMARY`, because the old
configuration points at value ids from another scale. But it bails out first if the select carries
`readonly` (`:109`) — and the comment at `:110-111` gives the reason: a framework that has already been
assessed has its scale frozen, and the server pins `scaleid` through a form constant; wiping the
configuration there would destroy data over an event the user cannot even fire.

## Modal shell

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-TITLE` | Configure scale | title | `js:135` (str), `:139` (`ModalSaveCancel.create`) | str `central_frameworks_configurescale` | `lang/en:129` = `'Configure scale'`; `lang/pt_br:129` = `'Configurar escala'`. **No scale name and no framework name** — `title` is the raw string. The modal's two strings (title and error) are fetched **in parallel** in a `Promise.all` (`js:134-137`). `setRemoveOnClose(true)` at `:140` |
| `MOD.SCALE-SAVE` | Save changes | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="save"` | comes with `ModalSaveCancel` (import `js:28`). **It is the only validation point** — see "The validation" |
| `MOD.SCALE-CANCEL` | Cancel | button (footer) | `lib/templates/modal_save_cancel.mustache` | `data-action="cancel"` | closing via Cancel, via the X or via ESC lands on `ModalEvents.hidden` (`js:152`) → `resolve(null)` → `frameworks.js:73-75` sees `null` and **does not touch** the hidden field or the summary |
| `MOD.SCALE-NOSCALE` | `[nothing — the modal does not open]` | guard | `js:126-128` | `if (!scaleid) { return null; }` | no scale chosen → `open` returns `null` **without opening anything and without warning**. The click on "Configure scale" simply does nothing. Recorded, not fixed |

## Body — one row per scale value

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.SCALE-HEAD` | **Value** · Default · Proficient | header | `mustache:34-38` | strs `central_frameworks_scalevalue` (`:35`) / `central_frameworks_scaledefault` (`:36`) / `central_frameworks_scaleproficient` (`:37`) | `lang/en:163` = **"Value"** — **not** "Scale value", which is the key paraphrased. The other two: `:160` = "Default", `:162` = "Proficient". Fixed widths in **inline** style (`4rem` / `5rem`), not in a class |
| `MOD.SCALE-ROW` | {value name} | row | `mustache:40` (row), `:41` (name) | `data-value="{{id}}"` · `.d-flex` | one per value, from `buildRows` (`js:99-116`). **The `data-value` is the whole contract with the AMD**: `serialize` (`js:58`) and `isComplete` (`js:79`) sweep `[data-value]` and read `row.dataset.value` (`js:62`). No table — they are flexed `div`s |
| `MOD.SCALE-DEFAULT` | `[aria-label only]` — "{name} Default" | radio | `mustache:43-44` | `name="dimensions-scaledefault"` · `data-role="default"` · `value="{{id}}"` | **the `name` is fixed and literal**, and it is what guarantees "exactly one default": the grouping is the native radio's, not the JS's. The `aria-label` (`:44`) concatenates the value name + the header str — it is the only accessible name, since the column has no `<label>` |
| `MOD.SCALE-PROFICIENT` | `[aria-label only]` — "{name} Proficient" | checkbox | `mustache:47-48` | `data-role="proficient"` · `value="{{id}}"` · **no `name`** | **one or more** proficient values — there is no grouping, each row is independent. Unlike the radio, it has **no `name`**; nothing reads it by `name`, only by `data-role` (`js:60`, `:81`) |

**The pre-selections come from a JSON that drops its first element on purpose.** `parseExisting`
(`js:40-47`) does a `JSON.parse` and a `.slice(1)` (`:43`) — core's format keeps `{scaleid}` at
position 0 and the per-value configurations after it. The `try/catch` (`:41-46`) returns `[]` on
invalid JSON, so a corrupted configuration opens the modal **empty** instead of blowing up.
`buildRows` (`:99-116`) turns the two arrays into maps (`defaults`/`proficients`) and matches **by
value id**, not by position — a value removed from the scale simply does not appear.

## The validation — popup, not inline

`isComplete` (`js:76-90`) sweeps the rows and returns `hasdefault && hasproficient` (`:89`). On save
(`js:144-151`):

```
if (!isComplete(root)) {
    event.preventDefault();                    // js:146 — holds the modal open
    Notification.alert('', incomplete);        // js:147 — popup ON TOP of the modal
    return;
}
resolve(serialize(root, scaleid));             // js:150
```

The str is `central_frameworks_scaleincomplete` = **"Select at least one default value and one
proficient value."** (`lang/en:161`). The first argument to `alert` is `''` — the popup **has no
title**.

**The idiom divergence from `MOD.RULE` is the finding worth recording.** The two modals were born a day
apart (`a78c3f6` 27/06 and `283e9a7` 28/06), both validate on `ModalEvents.save`, both call
`event.preventDefault()` to hold the dialogue — and there they diverge:

| | `MOD.RULE` | `MOD.SCALE` |
| --- | --- | --- |
| Where the error appears | **inline** alert in the body (`data-region="error"`) | **popup** `Notification.alert` on top |
| What turns it off | `refresh`, together with the points table (`rule_config.js:158`) | the user, closing the popup |
| Has a title | — | **no** (`''`, `js:147`) |
| Stacking | none | **a third level**: popup > scale modal > modalform |

Neither is the house pattern for feedback in a modal (the toast hosted in the body, which
`mod-links.md` documents) — but both are **blocking validation errors**, not confirmations, and for
that the toast would be the wrong vehicle. What is recorded is the **inconsistency between the two**,
not a fix: unifying them touches shipped behaviour in two modals.

## What the save resolves

`serialize` (`js:56-68`) builds core's format by hand:

```
const config = [{scaleid: Number(scaleid)}];                       // js:57 — the position 0 that parseExisting discards
root.querySelectorAll('[data-value]').forEach((row) => {           // js:58
    config.push({id, scaledefault: def && def.checked ? 1 : 0,     // js:61-65 — 1/0, not boolean
                 proficient: prof && prof.checked ? 1 : 0});
});
```

The `1`/`0` (not `true`/`false`) is what core expects. The `def &&` / `prof &&` guards against a row
without the controls — a situation the Mustache does not produce, but which `serialize` does not assume
away.

The receiver is `frameworks.js:71-85`: `null` (cancel) → it bails (`:73-75`); a string → written into
`MOD.SCALE-HIDDEN` (`:76`) and "Configured" written into `MOD.SCALE-SUMMARY` (`:77-82`). A network
error goes to `notifyError` (`:85`). **Nothing is persisted here** — `scaleconfiguration` only reaches
the database when `framework_dynamic_form` is saved.

## The form side of the "scale" subject lives in `maps/mod-forms.md`

`MOD.SCALE-ACTION`, `MOD.SCALE-SUMMARY` and `MOD.SCALE-HIDDEN` are born at `form.php:186-195`, in the
body of a `core_form\dynamic_form`. The plugin's four `dynamic_form` bodies (`framework_`,
`import_framework_`, `competency_`, `template_dynamic_form.php`) have their own map in
[`maps/mod-forms.md`](mod-forms.md), and the three IDs above **migrated** there as
`FORM-FWK-SCALE-ACTION`/`-HIDDEN`: the trigger, the summary and the hidden field belong to the
**framework form's body**, and what stays here is the **child modal** the trigger opens.

That is why **this modal is clean and `MOD.SCALE` as a subject is not**: the three July commits on the
scale — `a2112fe` (scales shortcut in the header + parity with the native scale), `8ab5635` (freeze the
scale select) and `c8901c0` (a frozen select does not trip over the required rule) — **all** went
through `framework_dynamic_form.php` and **none** touched `framework_scaleconfig` (neither the mustache
nor the `.js`).

## Pending — the state as a pill

**Not built.** There is no mockup either: the clauses are recorded with the place that was looked at, on
this date.

- **Readable pills in place of the raw radio/checkbox.** `mustache:43-44` is still
  `<input type="radio" name="dimensions-scaledefault" … data-role="default">` and `:47-48` is still
  `<input type="checkbox" … data-role="proficient">`, each with only an `aria-label`; a
  `grep -nE 'pill|badge' templates/central/framework_scaleconfig.mustache amd/src/central/framework_scaleconfig.js`
  returns **zero**.
- **"initial" as the label for the default state.** The only key is `central_frameworks_scaledefault`
  (`lang/en:160` = `'Default'`, `lang/pt_br:160` = `'Padrão'`); there is no key and no value
  `Initial` (en) / `Inicial` (pt_br) in either language file.
- **The header already uses the real string.** `mustache:35` has rendered
  `central_frameworks_scalevalue` ("Value") since `283e9a7` — nothing is pending in that clause; it was
  the map that paraphrased it.

None of this touches the popup validation, the silent `MOD.SCALE-NOSCALE` or the `scaleconfiguration`
that only persists with the form: all three are `.js`/`.php` matters, not layout ones.
