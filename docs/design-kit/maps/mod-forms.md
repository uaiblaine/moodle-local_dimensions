# Field map — the five `dynamic_form` bodies (as-is)

The kit maps the **shell** of every modal (`modal-shell.html`); this file maps the **bodies** of
`core_form\dynamic_form` (the gap recorded in [`mod-scale.md`](mod-scale.md) and in the README). An
`ls classes/form/` returns **five**, all opened as `core_form/modalform` (no page reload):

| Form | Opened from | Creates/edits | Shell |
| --- | --- | --- | --- |
| `framework_dynamic_form.php` | `frameworks.js` (Structures tab) | `competency_framework` | `modal-shell.html` + the "Open scales page" link |
| `competency_dynamic_form.php` | `structure.js` (Competencies tab) | `competency` | `modal-shell.html` |
| `template_dynamic_form.php` | `plans.js` (Plans tab) | `competency_template` | `modal-shell.html` |
| `import_framework_dynamic_form.php` | `frameworks.js` (Structures tab) | imports CSV | `modal-shell.html` |
| `import_templates_dynamic_form.php` | `plans_transfer.js` (Plans tab) | **imports nothing** — uploads a CSV and hands it to the preview | `modal-shell.html` |

**Abbreviation in the tables:** inside each section, `form.php:` is **that section's form class** —
`framework_dynamic_form.php` in `FORM-FWK`, `competency_dynamic_form.php` in `FORM-COMP`,
`template_dynamic_form.php` in `FORM-TPL`, `import_framework_dynamic_form.php` in `FORM-IMP`. The other
paths are relative to the plugin root.

ID convention here: `FORM-FWK-*`, `FORM-COMP-*`, `FORM-TPL-*`, `FORM-IMP-*`, `FORM-TPLIMP-*` — the `IMP` in
`FORM-IMP-*` stands for **import**, not for improvement. **Migration:** the IDs
`MOD.SCALE-ACTION/-SUMMARY/-HIDDEN` were **provisional** in `mod-scale.md` (the scale trigger lives in
the framework form's body, not in the scale modal); they now live here as `FORM-FWK-SCALE-*`.

## Shared foundations (all five)

- **The shell is `core_form/modalform`.** Each opener does `new ModalForm({formClass, args, modalConfig:{title}})`.
  The title is `getString(<key>, <comp>)` in the opener, not in the form.
- **ids are randomised.** `dynamic_form` suffixes **every** element id (`id_scaleid` →
  `id_scaleid_c5fLCIS8…`), so **every piece of JS that talks to a field selects it by `name`**, never by
  `#id_<name>` (see [[moodle-hub-ui-gotchas]]). Where an id **has** to be fixed (core's
  `tool_lp/scaleconfig` matches by selector), the form **pins** it explicitly — `id_scaleconfiguration`,
  `id_scaleid_central`, `tool_lp_scaleconfiguration_central`, `id_scaleconfigbutton_central`.
- **`js_call_amd` lives in `definition_after_data()`, never in `definition()`.** `definition()` runs in
  the moodleform constructor, **before** the modalform's `start_collecting_javascript_requirements()`;
  a `js_call_amd` there never reaches the modal. Holds for the contrast panel, the swatch and the SCSS
  pin of the competency/template forms (see [[moodle-hub-ui-gotchas]], the 2 dynamic_form traps).
- **The description editor is URL-only media.** The three forms with a `description` use
  `{maxfiles:1, return_types:FILE_EXTERNAL, enable_filemanagement:false}` — the `maxfiles:1` is the
  workaround for the `tiny_media` crash (embed without fpoptions) on 5.0–5.2, and `FILE_EXTERNAL` takes
  the repository out of the picker: images by URL only, with no file area (see
  [[dimensions-tinymce-media-crash]]).
- **Two customfield areas, and only two.** `competency_handler` (competency area) and `lp_handler`
  (template area) inject the customfield block through `instance_form_definition()`. **The framework
  form injects zero** — a `grep -c 'competency_handler\|lp_handler' classes/form/framework_dynamic_form.php`
  returns `0`; **the import** is zero too (the customfields travel as `cf_*` columns of the CSV, applied
  by the importer). Only **competency** and **template** carry the block.

---

## `FORM-FWK` — the structure form body (`framework_dynamic_form.php`)

Opens on two paths, both from `frameworks.js`: **edit** (`editFramework`, `:192`, args `{id}`, title
`central_frameworks_edit`, from the sticky footer) and **create** (`createFramework`, `:201-202`, args
`{id:0, contextid}`, title `central_frameworks_new`, from the toolbar button). **Save** → toast
`central_frameworks_saved` + `reloadPane` (`frameworks.js:178-181`); the tab has no in-place path.
Gate: `moodle/competency:competencymanage` on the submission context (`form.php:115-117`).
**No customfields.**

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FORM-FWK-ID` | `[hidden]` | hidden | `form.php:137-138` | `PARAM_INT` | structure id; 0 = create. Drives the create-vs-update branch (`:247`, `:263`) |
| `FORM-FWK-CONTEXTID` | `[hidden]` | hidden | `form.php:139-141` | `PARAM_INT` | seeded on create from `region.dataset.contextid` (`frameworks.js:203`); scopes the unique-shortname check. **Not** re-read on edit |
| `FORM-FWK-SHORTNAME` | Short name | text | `form.php:143-146` | `PARAM_TEXT` · maxlength 100 | **native** `tool_lp` label. `required` is client-only (`:145`); uniqueness is server-side (see validation) |
| `FORM-FWK-IDNUMBER` | ID number | text | `form.php:148-151` | `PARAM_RAW` · maxlength 100 | `RAW` on purpose (idnumber accepts arbitrary chars). `required` is client-only (`:150`); **no** uniqueness check in this form |
| `FORM-FWK-DESC` | Description | editor | `form.php:157-164` | `PARAM_CLEANHTML` · rows 4 | URL-only media (foundation above). `set_data` `{text,format}` (`:224-227`) |
| `FORM-FWK-SCALE` | Scale | select | `form.php:166-184` | `PARAM_INT` | **freezable select** (see Design controls). Label `central_frameworks_scale`; options `get_scales_menu()` (core). `required` only when not frozen (`:183`). It is where the incomplete-scale error is anchored (`:295`), even though the carrier is the hidden field |
| `FORM-FWK-SCALE-HIDDEN` | `[hidden]` | hidden | `form.php:186-187` | `name="scaleconfiguration"` · `PARAM_RAW` · fixed id `id_scaleconfiguration` | **the scale's real destination** (migrated from `MOD.SCALE-HIDDEN`). Written by JS (`frameworks.js:77`), cleared when the scale changes (`:117`). Persisted verbatim (`:261`) |
| `FORM-FWK-SCALE-ACTION` | Configure scale | static (button+summary) | `form.php:189-195` | `data-action="configure-scale"` · `data-region="scaleconfig-summary"` | **the `MOD.SCALE` trigger** (migrated from `MOD.SCALE-ACTION`). Hand-built string: a `.btn.btn-secondary.btn-sm` button + a summary span. Wired by **document-level capture-phase delegation** (`frameworks.js:96-124`, once per page) because the form body lives in a modalform whose life cycle never runs the tab's init. Click → `openScaleConfigForForm` (`:62-86`) opens `MOD.SCALE`. The summary shows `central_frameworks_scaleconfigured`="Configured" only when the stored config is already complete (`:189-190`) |
| `FORM-FWK-VISIBLE` | Visible | selectyesno | `form.php:197-198` | default 1 | the framework's own flag — **distinct** from `FWK-ROW-VIS` (the sticky-footer toggle that flips it over a WS without opening the form) |
| `FORM-FWK-TAXONOMY` | Level {i} | select (loop) | `form.php:200-204` | — | `taxonomies[1..N]`, N = `taxonomy_levels()` = `max(depth, 4)` (`:85-87`); options `get_taxonomies_list()` (core). Persisted as CSV (`:255-256`). **Load gotcha:** the persistent's magic getter explodes the CSV column into a 1-indexed array — a `(string)` on it = a warning that developer debug escalates into an exception (`:232-235`) |

**Validation (`form.php:281-299`) — both block:** (1) **unique shortname** within the same `contextid`
→ `shortnametaken` (`:287-292`); (2) **incomplete scale** → `central_frameworks_scaleincomplete`
anchored on `scaleid` (`:294-296`), via `helper::scaleconfig_is_complete` (`helper.php:2924`, requires
≥1 default **and** ≥1 proficient), the same thing the child modal requires before resolving — **it
blocks on both sides**. It does not re-check the required rule on shortname/idnumber/scaleid
(client-only), nor idnumber uniqueness.

**Design controls:** (a) **`FORM-FWK-SCALE` frozen** — when `scale_frozen()` (`:75-77`, that is
`framework->has_user_competencies()`),
the select turns `readonly`+`disabled` + `setConstant` + **no** `required` rule (`:173-184`): the
disabled attribute drops it from the POST but the constant feeds `get_data()` and the JS still reads
`.value` (the freeze-a-select recipe, [[moodle-hub-ui-gotchas]]); changing the scale does **not** clear
the config when frozen. Two visual states (editable dropdown × padlock), and the greyed value must not
read as empty. (b) **`MOD.SCALE` trigger** (migrated, above). (c) **The "Open scales page" link in the
footer** — injected by `injectScalesLink` (`frameworks.js:134-162`) on `LOADED`, as the **first child of
`.modal-footer`** (`:160`, so that `margin-right:auto` pushes Save/Cancel across), **only** when
`activeRegion.dataset.canscalespage === '1'` (`:138`). The form's close chip comes from
`.modal-form-dialogue` (pure CSS, `styles.css:5063-5103`), not from a class injected in JS —
`injectScalesLink` stopped touching the dialogue (`025c2f6`); before that the class arrived on `LOADED`
and produced a flash. (d) URL-only description.

---

## `FORM-COMP` — the competency form body (`competency_dynamic_form.php`)

Opens from `structure.js` in three places: **edit** (`:1253-1258`, title `editcompetency`), **add
child** (`:1259-1260`) and the header's "Add competency" button (`:1425-1428`), the latter two with
title `addcompetency` (**native** `tool_lp` strings). **Save** (`structure.js:804-810`): on an edit →
`refreshNode` **in place** (keeps expansion + selection); on a create → `reloadPane`. The parent-change
case lives **inside** `refreshNode` (`:745-754`): a different parent → `reloadPane` + `revealNode` at
the new spot. **No toast** — the confirmation is the in-place flash/re-render. Gate:
`moodle/competency:competencymanage` on the structure's context (`form.php:112-114`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FORM-COMP-ID` | `[hidden]` | hidden | `form.php:131-132` | `PARAM_INT` | 0 = create |
| `FORM-COMP-FWKID` | `[hidden]` | hidden | `form.php:133-135` | `PARAM_INT` | picks the submission context + scopes the parent options and idnumber uniqueness |
| `FORM-COMP-PARENT` | Parent competency | select | `form.php:136-142` | `PARAM_INT` | **native** label. Options from `get_parent_options` (`:79-97`): the root + every competency in the structure **minus** the one being edited (`:87-89`) and its descendants (`:90-92`) — it cannot become a child of itself. **Decoupled on edit:** the submitted parent does **not** go to `update_competency` (`:292` resends the original parent) — only a separate `set_parent_competency` (`:300-302`) reparents, and client-side that forces `reloadPane`+`revealNode` |
| `FORM-COMP-SHORTNAME` | Short name | text | `form.php:144-147` | `PARAM_TEXT` · maxlength 100 | core label. `required` is client-only |
| `FORM-COMP-IDNUMBER` | ID number | text | `form.php:149-151` | `PARAM_RAW` | **unique, server-side** (see validation → `idnumberexists`); it is the only field with a blocking validator of its own |
| `FORM-COMP-DESC` | Description | editor | `form.php:157-164` | `PARAM_CLEANHTML` | URL-only media (foundation) |
| `FORM-COMP-SCALE` | Scale | select | `form.php:169-172` | `PARAM_INT` · fixed id `id_scaleid_central` | one third of the inline scale trio. Options `[null=>inheritfromframework] + get_scales_menu()`; null = inherit. `addHelpButton` (`:172`) |
| `FORM-COMP-SCALE-HIDDEN` | `[hidden]` | hidden | `form.php:174-175` | `PARAM_RAW` · fixed id `tool_lp_scaleconfiguration_central` | destination of the **native** scale dialogue (`tool_lp/scaleconfig`) — a **distinct mechanism** from the framework's `MOD.SCALE` (that one is bespoke, this one is core's, with no summary span) |
| `FORM-COMP-SCALE-BTN` | Configure scales | button | `form.php:176-181` | fixed id `id_scaleconfigbutton_central` | **native** `tool_lp/scaleconfig` trigger; unconditional (it exists on create too) |
| `FORM-COMP-CFIELD` | {core category headers} | customfield (block) | `form.php:184` → `competency_handler:160-176` | `customfield_<shortname>` | the **competency area block**. The handler adds **no** heading at all and passes the identifier through untouched (`:166-170`), so what labels the block is core's categories. Members provisioned in this area (`helper::ensure_custom_fields_exist`, `:468-490`): `enrollmentfilter`/`singlecourseredirect`/`lockedcardmode`/`showlockeddate` (the cascade), `custombgcolor`/`customtextcolor` (hex text — the graded pair), `tag1`/`tag2`/`type`, `customscss` (only with `enablecustomscss`), and `customcard`/`custombgimage` (picture, **only** in external mode, `:475-478`). Rows carry `itemid=0, instanceid=<compid>` |
| `FORM-COMP-IMG` | custombgimage / customcard | filemanager | `form.php:184` → `picture_manager:144-166` | areas `competency_bgimage`/`_cardimage` | **plugin-custom**, only in the image **built-in mode** — mutually exclusive with the `picture` customfields. Two filemanagers, `maxbytes` 10MB + `maxfiles` 1 + `accepted_types` `web_image` (`picture_manager:100-107`) |
| `FORM-COMP-CASCADE` | `[no label]` | static | `form.php:187-198` | — | explainer for the competency→template→global cascade, `insertElementBefore` above `enrollmentfilter` (`:195`; fallback `addElement` at the end if the field is absent, `:197`) |

**Validation (`form.php:325-339`) — blocks:** unique idnumber within the structure → `idnumberexists`
(`:328-334`); + `helper::validate_customscss` (`:337`, compiles the SCSS and blocks on error, only with
the feature on). It does **not** validate the colour pair — the panel **advises, it does not block**.

**Design controls (all wired in `definition_after_data`, `:214-239`):** (1) the **inline scale trio**
via `tool_lp/scaleconfig` (`:222-226`) — native, fixed ids. (2) **WCAG contrast panel** via
`local_dimensions/central/contrast` (`:235-238`) over the `custombgcolor`/`customtextcolor` pair: it
computes the real ratio (sRGB linearisation → luminance → `(L1+.05)/(L2+.05)`, `contrast.js:82-105`), a
verdict pill in four bands (excellent ≥7 / pass ≥4.5 / caution ≥3 / fail, `contrast.js:234-245`,
thresholds at `:43`) + AA/AAA badges, and even **two one-click fixes** below AA (`:404`, `:408`) — but
it **advises, it never touches the save** (`contrast.js:22-23`); it relayouts the two `.fitem` into a
two-column flex (`:475-491`). (3) **Colour swatch** (`colour_swatch`, `:229-232`). (4) **SCSS pinned to
`FORMAT_PLAIN`** (`helper::force_customscss_plain`, `:220`).

> **The contrast panel's real gap** (inherited from [`pln-plans.md`](pln-plans.md), not re-litigated
> here): it grades **text × background**, but the header paints **three derived stops** + translucent
> chips that nobody grades — the pair the panel shows is not what the header renders. This map only
> records that the panel **advises**; the blocking surface is `validation()` (idnumber + SCSS), above.

---

## `FORM-TPL` — the template form body (`template_dynamic_form.php`)

Opens from `plans.js`: **new** (`new-template`, `:714-720`, args `{id:0, contextid}`, title
`managetemplates_addtemplate`) and **edit** (`edit-template`, `:721-727`, args `{id}`, native title
`edittemplate`). **Save** → `reloadKeepingScroll` (`plans.js:207` → `:93-109`: snapshots the scroll of
both regions, `reloadPane` in `quiet` mode, restores). **No toast.** Server-side save
(`form.php:295-325`): `create/update_template` + `lp_handler::instance_form_save_with_image` (2-arg,
`:317`) — and it is **the handler**, not the form, that wraps the save in a `dml_write_exception` retry
(`lp_handler:202-209`, the id-0 INSERT race on `customfield_data`'s unique index).
Gate: `moodle/competency:templatemanage` (`form.php:93`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FORM-TPL-ID` | `[hidden]` | hidden | `form.php:112-113` | `PARAM_INT` | 0 = create |
| `FORM-TPL-CONTEXTID` | `[hidden]` | hidden | `form.php:114-116` | `PARAM_INT` | scope for the unique shortname; 0 → the system context (the dataset-as-truth gotcha) |
| `FORM-TPL-SHORTNAME` | Short name | text | `form.php:118-121` | `PARAM_TEXT` · maxlength 100 | the only always-required visible field; uniqueness server-side (`shortnametaken`) |
| `FORM-TPL-DESC` | Description | editor | `form.php:127-134` | `PARAM_CLEANHTML` | URL-only media (foundation) |
| `FORM-TPL-VISIBLE` | Visible | selectyesno | `form.php:136-138` | default 1 | `addHelpButton` |
| `FORM-TPL-DUEDATE` | Due date | date_time_selector | `form.php:140-141` | `['optional'=>true]` | an enable checkbox; 0 = no due date. The due-date hero only shows on the **plan** view, not on the tracker |
| `FORM-TPL-CFIELD` | {core headers} | customfield (block) | `form.php:145` → `lp_handler:169-172` | `customfield_<shortname>` | the **lp area block**. The handler only emits an `<h2>` when the identifier is **not** `''` (`:169-172`); the modal passes `''` (`form.php:145`), so the heading is suppressed here. Besides the ones itemised below, the lp area also provisions `subline_source`, `template_idnumber`, `lockedcardmode`, `showlockeddate`, `tag1`/`tag2`/`type` and (in external mode) `customcard`/`custombgimage` — `helper::ensure_custom_fields_exist:457-490` |
| `FORM-TPL-DISPLAYMODE` | Display mode | select (customfield) | `form.php:168-170` | 1=Competency tracker (`DISPLAYMODE_COMPETENCIES`), 2=Full plan overview (`DISPLAYMODE_PLAN`) | **the cascade's engine** — **five** `hideIf` rules depend on it (`:173-203`), and a sixth depends on `showrelated` (`:205-210`). The option's 1-based index **is** the `DISPLAYMODE_*` constant by construction (comment at `:168-169`; `constants.php:177`/`:180`) |
| `FORM-TPL-REDIRECT` | Redirect single course | select (customfield) | `form.php:173-178` | `hideIf displaymode eq DISPLAYMODE_PLAN` (2) | only in **Competency tracker** mode. The `hideIf` rules on `lockedcardmode` (`:179-184`) and `showlockeddate` (`:185-190`) are the same rule, over the same two values |
| `FORM-TPL-SHOWRELATED` | Show related | select (customfield) | `form.php:192-197` | `hideIf displaymode eq DISPLAYMODE_COMPETENCIES` (1) | only in **Full plan overview** mode; the gate for the link below |
| `FORM-TPL-SHOWRELATEDLINK` | Link related | select (customfield) | `form.php:198-203` + `:205-210` | **two** `hideIf` rules: displaymode eq 1 **and** showrelated eq index-of-No | only Full plan overview **and** with Show related = Yes. The 2nd value is **not** a literal: it is `array_search(SHOWRELATED_NO, array_keys(showrelated_options())) + 1` (`:209`), computed at definition time — `3` today, and reordering `showrelated_options()` is followed automatically |
| `FORM-TPL-ENROLFILTER` | Enrollment filter | select (customfield) | `form.php:161-163` | — | the anchor for the cascade explainer (`insertElementBefore` above it) |
| `FORM-TPL-BGCOLOR` / `-TEXTCOLOR` | custombgcolor / customtextcolor | text (customfield) | from the lp block (`form.php:145`); decorated at `:230-233` (swatch) and `:236-239` (contrast) | hex | **the graded pair** — plain text (not a colorpicker). Header defaults when empty: `#0f6cbf`/`#ffffff` (`dynamictabs/plans.php:274-275`) |
| `FORM-TPL-SCSS` | Custom SCSS | textarea (customfield) | `form.php:226` (pin at render), `:272-288` (pin in `get_data`) | — | only with `enablecustomscss`. Pinned to `FORMAT_PLAIN` at render **and** in `get_data` (all 4 possible shapes of the value); **blocks** the save on a compilation error |
| `FORM-TPL-CASCADE` | `[no label]` | static | `form.php:148-166` | — | explainer, `insertElementBefore` above `enrollmentfilter` (`:163`; fallback `addElement` at `:165`) |

**Validation (`form.php:334-352`) — blocks:** unique shortname within the `contextid` →
`shortnametaken` (`:337-346`); + `validate_customscss` (`:349`, invalid SCSS). It does **not** validate
the colour pair (it advises), nor duedate/visible.

**Design controls:** (1) **WCAG contrast panel** (`:236-239`) + (2) **swatch** (`:230-233`) — identical
to the competency form, same `contrast.js`/`colour_swatch`, same relayout, same "advises, does not
block". (3) **`hideIf` cascade** driven by `displaymode` (progressive disclosure, 5 rules + 1 depending
on `showrelated`). (4) Blocking **`FORMAT_PLAIN` SCSS**. (5) URL-only description. (6) **No toast** on
save (diverges from the house pattern; the confirmation is the scroll-preserving reload).

---

## `FORM-TPLIMP` — the learning plan import form body (`import_templates_dynamic_form.php`)

Opens from the `PLN-IMPORT` button (`data-action="import-templates"`, `plans.mustache:155-157`) →
`openImportModal` (`plans_transfer.js:432`), args `{contextid}`, title `central_plans_import_title`.
Gate: a **SYSTEM or COURSECAT** context (otherwise `invalidcontext`) + `competency:templatemanage`
(`form.php:64-71`). **Nothing is imported here.** Unlike `FORM-IMP`, whose submission runs the whole
import in-request, this form is **step one of two**: `process_dynamic_submission` (`form.php:151-159`)
returns only the draft handle and the parse settings and **contains no write of any kind**, which is
what lets the preview that follows promise that nothing has happened yet.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FORM-TPLIMP-CONTEXTID` | `[hidden]` | hidden | `form.php:92-94` | `PARAM_INT` | the import target; seeded from `region.dataset.contextid` by the opener, and re-resolved server-side |
| `FORM-TPLIMP-FILE` | CSV file | filepicker | `form.php:96-102` | `accepted_types ['.csv','.txt']` | its own key `central_plans_import_file`, whose English value matches the Frameworks one. `required` is client-only (`:103`) |
| `FORM-TPLIMP-DELIM` | CSV separator | select | `form.php:105-110` | `PARAM_ALPHA` | same language-sensitive default as `FORM-IMP-DELIM`: `listsep == ';'` → `semicolon` (`:113`) |
| `FORM-TPLIMP-ENCODING` | Encoding | select | `form.php:115-120` | `PARAM_RAW` | `core_text::get_encodings()`; default UTF-8 (`:122`) |
| `FORM-TPLIMP-UPDATE` | Update templates that already exist here | advcheckbox | `form.php:127-131` | `PARAM_BOOL` | **default off** (`:133`), and that default is a decision, not an oversight: on would pre-tick every matched template and arm, on the first Apply, the bulk UPDATE that renames every open learner plan built from it. Off makes the first run a pure comparison the operator opts into row by row. `addHelpButton` (`:134`) |

**Validation (`form.php:172-191`) — server-only:** an **empty** file (`:176-179`), one the parser
refuses, and one **with no template row** (`:187-189`, `central_plans_import_notemplaterow`). The
parser's **own** error is carried through verbatim (`:185-186`) rather than replaced by a generic
message, so "this looks like a competency structure file, import it from the Structures tab" survives
to the user instead of becoming "the file could not be read".

**Design controls:** the upload, the delimiter, the encoding and the update toggle — four, exactly as
`FORM-IMP`. What differs is everything after Save: see `PLN-IMP` in
[`pln-plans.md`](pln-plans.md) for the preview this hands off to.

---

## `FORM-IMP` — the import form body (`import_framework_dynamic_form.php`)

Opens from the `FWK-IMPORT` button (`data-action="import"`, `frameworks.mustache:85-86`) →
`openImportForm` (`frameworks.js:248-265`), args `{contextid}`, title `central_frameworks_import_title`.
Gate: a **SYSTEM or COURSECAT** context (otherwise `invalidcontext`) + `competency:competencymanage`
(`form.php:65-71`) — a superset of core's `tool/lpimportcsv` (system only). **The import runs
in-request** in `process_dynamic_submission` (`:148-158`), **with no WS**: it reads the CSV from the
draft, parses it and imports synchronously. **No customfields** — the plugin's customfields travel as
`cf_*` columns of the CSV, applied by the importer.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `FORM-IMP-CONTEXTID` | `[hidden]` | hidden | `form.php:93-95` | `PARAM_INT` | the import target; defaults to the submission context, seeded from `region.dataset.contextid` by the opener |
| `FORM-IMP-FILE` | CSV file | filepicker | `form.php:97-104` | `accepted_types ['.csv','.txt']` | **the central control.** `required` is client-only (`:103`) — the **only** client-side validation. On save, `$data->importfile` is the **draft id**, read from the draft area by `read_uploaded_csv` (`:190-200`) |
| `FORM-IMP-DELIM` | CSV separator | select | `form.php:106-114` | `PARAM_ALPHA` | options from `csv_import_reader::get_delimiter_list()` (core). **Language-sensitive default:** `listsep==';' ? 'semicolon' : 'comma'` (`:113-114`) — ';' for locales such as pt_br |
| `FORM-IMP-ENCODING` | Encoding | select | `form.php:116-123` | `PARAM_RAW` | options from `core_text::get_encodings()`; default UTF-8 (`:123`). `RAW` because charset names contain chars that `ALPHA` would strip |
| `FORM-IMP-UPDATE` | Update existing by ID number | advcheckbox | `form.php:125-131` | `PARAM_BOOL` | default off. `addHelpButton` (`:131`) explains the merge-by-idnumber (existing ones updated, new ones added, **none removed**; off = always create a new one). 3rd arg of the importer (`:155`) |

**Validation (`form.php:167-182`) — server-only, everything blocks:** it re-reads the draft and rejects
an **empty** file (`:171-174`), an **unparseable** one (`:176-177`,
`central_frameworks_import_invalidfile`) and one **with no framework row** (`:178-179`,
`central_frameworks_import_noframeworkrow`), all anchored on `importfile`. There is no "warning-only"
validation here.

**Design controls:** the upload (`FORM-IMP-FILE`), the **language-sensitive delimiter default**, the
encoding, and the **merge toggle** (`FORM-IMP-UPDATE`). No scale, no contrast, no frozen select.
**The loading/feedback UX** (the `data-region="import-loading"` banner, the
`central_frameworks_import_done` toast, and `makeSpinner`'s ARIA defect) **is already mapped** in
[`fwk-structures.md`](fwk-structures.md), section "Import modal" — cross-referenced, not re-derived
here.

---

## Cross-references (do not contradict)

- [`fwk-structures.md`](fwk-structures.md) covers the tab's **shell** (`FWK-ROW-EDIT`, `FWK-IMPORT`,
  the import banner/toast, the scales link). This map covers the **bodies**; `FORM-FWK-SCALE-ACTION`
  here is what the `FWK-ROW-EDIT` row over there calls "the form with `MOD.SCALE` built in".
- [`mod-scale.md`](mod-scale.md) covers the scale **child modal** (what `FORM-FWK-SCALE-ACTION` opens).
  Its `MOD.SCALE-ACTION/-SUMMARY/-HIDDEN` IDs were provisional and **migrate** to `FORM-FWK-SCALE-*`
  here.
- [`pln-plans.md`](pln-plans.md) cites the contrast panel (and the gap of the header's three stops);
  [`est-competencies.md`](est-competencies.md) cites the opener (`EST-DETAIL-EDIT`). This map supplies the
  field inventory that was missing, without re-litigating the contrast finding.
