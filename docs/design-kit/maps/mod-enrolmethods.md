# Field map — `MOD.ENROL` · Enrolment methods (as-is)

**4th tab** of the Participants modal (`MOD.PART`), after Cohorts / Users / Assign roles.
It configures **in bulk** the enrolment methods of the courses linked to the template's competencies,
always tied to one of the plan's cohorts. The pane is born **empty** in the host
(`participants_manager.mustache:150-151`) and is mounted by `enrol_methods.js:1082-1112`.

- **Mustache:** [`enrol_methods.mustache`](../../../templates/central/enrol_methods.mustache) (121, the tab's skeleton), [`enrol_group.mustache`](../../../templates/central/enrol_group.mustache) (65, one accordion group), [`enrol_detail.mustache`](../../../templates/central/enrol_detail.mustache) (82, body of the detail modal)
- **AMD:** [`enrol_methods.js`](../../../amd/src/central/enrol_methods.js) (1112) — reuses `action_button.js` (`iconButton`, `:38-49`) and `errors.js` (`notifyError`)
- **WS (5, all in `db/services.php:373-412`):** `list_enrol_competencies` (paginated roots + mount *bootstrap*), `list_enrol_courses` (rows with the status of **both** methods), `queue_enrol_action`, `get_enrol_queue_status`, `set_enrol_instance_status`
- **Task:** [`process_enrol_method`](../../../classes/task/process_enrol_method.php) — adhoc, per `(courseid, method, cohortid)`
- **Helper:** [`classes/local/enrol_methods.php`](../../../classes/local/enrol_methods.php) — `eligible_roles()` (`:58-73`), `default_roleid()` (`:81-89`)
- **CSS:** [`styles.css:7289-7354`](../../../styles.css) — the accordion's scroll box, the group's chevron/fade, the search width and the detail modal's table

> **Resync 2026-07-15 — this map was a _spec_, and the code ran over it the same night.**
> Measured, not estimated:
>
> - **Zero broken refs — because there were zero refs.** The previous version (`0b3782c`) **had no
>   `Origin` column**: the table header was `| ID | Label | Type | Data | Rule / notes |`.
>   A `grep -oE '[a-z_/.]+\.(php|js|mustache|css):[0-9]+'` over the old file returns **0**. This is not
>   the other maps' damage (Task 7: 23/23; Task 9: 21/21; Task 10: 12/24) — here the provenance column
>   **was missing entirely**. The 22 IDs existed without a single origin.
> - **The real window is 69 minutes.** The map landed in `0b3782c` (**2026-07-11 21:53:13**,
>   author and committer agree) and `3d1d5cb` shipped the tab at **23:03:05** — `(1783821785 − 1783817593)
>   / 60 = 69`. **There is no commit at 21:37** (the 21:00–23:30 window holds 8 commits; none in that
>   minute), and **no "21:37 / 86 minutes" pair exists** in `mod-participants.md` — on the contrary,
>   that map **corroborates** this window, reporting the same ~70 minutes (23:03 − 21:53). And the
>   feature's **first** code predates the tab: the task in `5df19b7` (22:41, +48 min) and the WSes in
>   `ee7a9e8` (22:51, +58 min) — the old map's three "planned" bullets were already false before the
>   tab existed.
> - **Eight commits ran over** `enrol_methods.js`: `3d1d5cb` (the tab), `432195c` (polish after the
>   1st manual test), `1d15e9f` (plugins shortcut + both-disabled gate), `545ba17`
>   (the accordion becomes a **labelled table** + contrast), `33f7697` (**DOM-built** rows, removes
>   `enrol_row.mustache`), `a5ef9a8` (per-row toggle), `8eea9ef` (toast + distributed bar +
>   primary segment), `ec9d813` (competency search). After those came `c2d9471` (error region),
>   `c07d5e5` (per-method icon + named action) and `7d69197` (the refresh leaves the pane and moves to
>   the modal header).
> - **What the old map had no way of having:** the search (`ENROL-SEARCH`), the both-disabled gate
>   (`ENROL-DISABLED`), per-pane refresh buttons (which **existed and later went away** —
>   see `ENROL-REFRESH`), the per-row enable/disable toggle (`ENROL-TOGGLE-STATUS`), the two "Load
>   more"s, the per-row role column, the table's `<caption>`/`<thead>`, and the whole mount
>   `bootstrap`.
> - **`ENROL-METHOD` is not `sync`.** The old map said `sync | self`; the code says
>   **`cohort | self`** (`enrol_methods.mustache:59`, `:63`) and the state is born `method: 'cohort'`
>   (`enrol_methods.js:1091`). **`sync` exists nowhere** — it is the *visible* label that reads
>   "Cohort sync" (`central_enrol_method_cohort`). Confusing the two breaks
>   `data-method`, `state.method`, the `method` argument of the 3 WSes and the task's key.
> - **`enrol_row.mustache` was deleted** in `33f7697`, with the reason recorded: the Mustache lint
>   renders the template in isolation and the HTML validator **rejects a loose `tr` fragment**. The
>   rows became `createElement` in `makeRow` (`:366-463`) — the same pattern as the Users/Roles tabs.

> **About this file's refs.** The `c07d5e5` slice (per-method icon + named action) inserted ~50
> lines at the top of the JS and shifted **every** `enrol_methods.js:NNN` ref; the `7d69197` slice
> (refresh in the header) removed four buttons and shifted the rest. Everything was re-measured
> against the current `HEAD`, including the refs of `enrol_methods.mustache`, of `styles.css` (which
> were ~1300 lines out), of `participants_manager.js` and of `tabs.js`. **The plugin version is not
> frozen:** `version.php:28` is `2026072700` and has been bumped several times since this feature — do
> not use "frozen at 2026071306" as an argument for anything.

## Gates — four regions, one revealed at a time

All four are born `hidden` in the Mustache; `init()` reveals **one**: on success, one of
`enrol-disabled` / `enrol-empty` / `enrol-main` (`enrol_methods.js:954-967`); on an **early** failure
— the initial load rejects before any `hidden = false` — the `catch` reveals `enrol-error` (`:939`)
and hides the other three (`:940-942`). The three alerts are **message-only blocks**, with no inner
wrapper and no button: the comment at `enrol_methods.mustache:33-35` gives the two reasons — a
`.d-flex` on the alert itself would **beat** the `hidden` attribute (`display` is `!important` in the
utilities), and reloading is now the modal header's refresh, *"so each alert carries just its
message"*.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROOT` | `[no label]` | region/root | `enrol_methods.mustache:32` | `data-region="enrol"` · `.local-dimensions-enrol` | it is the `state.root`; the delegated listener lands on it (`enrol_methods.js:990`) |
| `ENROL-DISABLED` | warning: both plugins disabled on the site | alert | `enrol_methods.mustache:36-38` | `data-region="enrol-disabled"` · `alert-warning` | `central_enrol_bothdisabled` (`:37`). Revealed by `enrol_methods.js:954-959` when `!cohortenabled && !selfenabled` — the whole tab goes inert. What fixes it is `PART-LINK-ENROL`, which **only a site admin sees** (see the lock mismatch below) |
| `ENROL-EMPTY` | warning: no cohort linked | alert | `enrol_methods.mustache:39-41` | `data-region="enrol-empty"` · `alert-info` · `role="status"` | `central_enrol_empty` (`:40`) sends the user to the **Cohorts tab**. Revealed by `enrol_methods.js:961-965` when `!cohortdata.cohorts.length` |
| `ENROL-ERROR` | warning: failed to load the methods | alert | `enrol_methods.mustache:42-44` | `data-region="enrol-error"` · `alert-warning` | the **4th door**, shipped in `c2d9471`. `central_enrol_loadfailed` (`:43`). Revealed in the `catch` of `init`'s initial load (`enrol_methods.js:939`), which hides the other three (`:940-942`), and **rethrows** (`:943`) so the mount's *swallow* still emits the toast. `alert-warning` (not `danger`): the failure is transient/retryable. Recovery is the modal header's refresh (`PART-REFRESH`) |
| `ENROL-MAIN` | `[no label]` | main region | `enrol_methods.mustache:45` | `data-region="enrol-main"` | revealed at `enrol_methods.js:967`. **Everything** that follows lives inside it |
| `ENROL-REFRESH` | **Retired** (`7d69197`, 2026-07-18) | — | absent | — | There were **four** `data-action="enrol-refresh"` buttons — one in each alert and one on the filters bar —, the pane's only refresh affordance. They went out together with the click handler: a `grep -rn 'enrol-refresh' templates/ amd/src/` returns **nothing**. Refreshing is now the **modal header's** button (`PART-REFRESH`), which consumes the `{refresh: () => init(state)}` handle returned by `mount` (`enrol_methods.js:1111`) via `attachRefresh` (`participants_manager.js:232`, callback at `:213-229`). The ID is kept here only for whoever comes looking for it; there is no corresponding control |

## Configuration bar

A `d-flex` row with the three controls distributed (`enrol_methods.mustache:46`), the hint underneath.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-COHORT` | **Plan cohort** | select | `enrol_methods.mustache:51` | `data-region="enrol-cohort"` · `form-select` | label at `:48-50`. Options via `list_template_cohorts` (`enrol_methods.js:969-970`) — **the cohorts already linked to the template**, not every cohort on the site. Changing it fires `reload` (`:1048-1050`) |
| `ENROL-METHOD` | Method | button group | `enrol_methods.mustache:57-67` | `data-region="enrol-method"` · `role="group"` | **`cohort`** (`:59-62`, born `active`/`btn-primary`/`aria-pressed="true"`, static `fa-users` icon) and **`self`** (`:63-66`, static `fa-user-plus` icon). Labelled by `aria-labelledby` → the `<span>` at `:54-56` (not a `<label>`: there is no single control to point at). Changing it **does not refetch** — `applyMethodChange` (`:756-780`) repaints from the row's `data-*` |
| `ENROL-METHOD-OFF` | `[no label]` | availability rule | `enrol_methods.js:891-898` | `button.disabled = !enabled` | each segment is disabled if the corresponding plugin is off on the site (`enrol_is_enabled`, `list_enrol_competencies.php:202-203`). If **only** `cohort` is off, the pane switches itself to `self` (`:896-898`) |
| `ENROL-ROLE` | **Assigned role** | select | `enrol_methods.mustache:73` | `data-region="enrol-role"` · `form-select` | label at `:70-72`. `eligible_roles()` = `$CFG->gradebookroles` **∩** `get_default_enrol_roles($context)` (`classes/local/enrol_methods.php:58-73`) — gradebook **and** assignable by enrolment. Default = the *student* archetype when eligible, otherwise the first one (`:81-89`). Changing it does **not** reload (`enrol_methods.js:1051-1052`): it only counts on the next action |
| `ENROL-HINT` | `[no label]` | text | `enrol_methods.mustache:76` | `data-region="enrol-hint"` | `central_enrol_hint_cohort` / `_hint_self`, swapped at `enrol_methods.js:766-767` and `:976-977` |

## Filters bar

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SEARCH` | Search competencies | text input | `enrol_methods.mustache:82-84` | `data-region="enrol-search"` · `.local-dimensions-enrol-search` | **was missing entirely** (`ec9d813`). `visually-hidden` label (`:79-81`) **and** a `placeholder` with the same string. **300 ms** debounce → `reload` (`enrol_methods.js:1033-1044`, the `setTimeout` at `:1040-1043`); the comment at `:1037-1038` says why it is server-side: the list is paginated, and a client-side filter would lose the pages not yet loaded. Fixed width `14rem` (`styles.css:7332-7334`) |
| `ENROL-CAT` | Course category | select | `enrol_methods.mustache:90-91` | `data-region="enrol-category"` | `visually-hidden` label (`:87-89`). Options = `central_enrol_categoryall` + the categories **of the linked courses** (`enrol_methods.js:882-885`; `list_enrol_competencies.php:185-197`). Changing it fires `reload` (`:1053-1055`) |
| `ENROL-HIDDEN` | Show hidden courses | switch | `enrol_methods.mustache:94-95` | `data-region="enrol-hidden"` · `.form-check.form-switch` | real label at `:96-98` (`for`/`id` — Behat's `"checkbox"` selector requires a `<label>`, not an `aria-label`). Hidden courses hidden by default (`enrol_methods.js:1095`); changing it fires `reload` (`:1056-1058`) |
| `ENROL-VISCOUNT` | `[no label]` | counter | `enrol_methods.mustache:100` | `data-region="enrol-viscount"` | `central_enrol_viscount` ("N courses shown") with `data.totalcourses` = **distinct configurable courses after the filters** (`enrol_methods.js:498-503`; `list_enrol_competencies.php:151`) |

## Accordion — competency groups

`ENROL-TREE` is a scroll box of its own: `max-height: 50vh; overflow-y: auto`
(`styles.css:7294-7297`) so the config bar above and the actions footer below stay visible at all
times. Groups via `renderGroupHtml` → `appendNodeContents` (`enrol_methods.js:340-354`, `:487`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-TREE` | `[no label]` | JS container | `enrol_methods.mustache:102` | `data-region="enrol-tree"` | empty when `!data.total` → a `nothingtodisplay` paragraph (`enrol_methods.js:488-493`) |
| `ENROL-GROUP` | `[no label]` | group | `enrol_group.mustache:36` | `data-group={id}` · `data-name={name}` | `data-name` is read back in `loadCourses` (`enrol_methods.js:529`) to stamp the competency name onto the row |
| `ENROL-GROUP-CB` | Select all courses of {competency} | checkbox | `enrol_group.mustache:38-39` | `data-groupcheck={id}` | `aria-label` via `central_enrol_selectall`. It only reaches the group's **already-loaded** rows and **skips the ones processing** (`enrol_methods.js:1063-1071`) |
| `ENROL-TOGGLE` | {competency name} | button | `enrol_group.mustache:40-47` | `data-action="enrol-toggle"` · `aria-expanded` | chevron (`:44`) + name (`:45`) + count badge (`:46`). **The name is the `shortname`** (`enrol_methods.js:349`), not the `fullname`. The chevron rotation and the *fade/slide* are **pure CSS** keyed on `aria-expanded` (`styles.css:7303-7325`) |
| `ENROL-GROUP-COUNT` | N courses | badge | `enrol_group.mustache:46` | `badge bg-secondary text-dark` | `central_enrol_courses` / `_coursesone` (its own singular, `enrol_methods.js:342-344`). The `bg-secondary` + `text-dark` pair is deliberate — see the contrast note |
| `ENROL-CHILDREN` | `[no label]` | container | `enrol_group.mustache:49` | `data-children={id}` · `data-offset="0"` · `hidden` | **lazy load on 1st expansion**, with a `data-loaded` latch that is **reverted on error** (`enrol_methods.js:568-576`), so re-expanding always tries again. The host's latch **also** recovers, but only on a pre-wiring rejection — see the mount-latch section |
| `ENROL-CAPTION` | {competency name} | caption | `enrol_group.mustache:51` | `visually-hidden` | — |
| `ENROL-HEAD` | Select · Course · Category · Role · Status · Actions | header | `enrol_group.mustache:53-60` | — | **6 columns** (`545ba17` swapped the loose accordion for a striped `table generaltable`). The 1st is `{{#str}}select{{/str}}` and **`visually-hidden`** (`:54`); the other five are core strings (`course`, `category`, `role`, `status`, `actions`) |
| `ENROL-ROWS` | `[no label]` | JS container | `enrol_group.mustache:62` | `data-region="enrol-rows"` | `<tbody>`; rows via `makeRow` |
| `ENROL-MORECOMPS` | Load more | button | `enrol_methods.js:496` | `data-action="enrol-morecomps"` · `data-offset` | page of **20** competencies (`PAGE_COMPETENCIES`, `:39`); the button removes itself on click (`:1008`) |
| `ENROL-MORECOURSES` | Load more | button | `enrol_methods.js:544` | `data-action="enrol-morecourses"` · `data-competencyid` · `data-offset` | page of **25** courses (`PAGE_COURSES`, `:40`) |

## Course row — **DOM-built**, not Mustache

`makeRow` (`enrol_methods.js:366-463`). Each row carries the status of **both** methods in its own
`data-*` (`:370-384`), so switching the segment and opening the detail **do not refetch**.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-ROW` | `[no label]` | row | `enrol_methods.js:368-369` | `.local-dimensions-enrol-row` · `data-courseid` + 14 `data-*` | the **same course can appear under more than one competency**: every write sweeps the *twins* by `data-courseid` (`:261`, `:589`, `:619`, `:705`) |
| `ENROL-ROW-CB` | Select {shortname} | checkbox | `enrol_methods.js:387-391` | `data-rowcheck="1"` | `aria-label` via `central_enrol_selectcourse`. **Hidden** (not disabled) while processing (`:239`) |
| `ENROL-ROW-SPIN` | `[no label]` | spinner | `enrol_methods.js:392-396` | `data-region="row-spinner"` · `fa-spinner fa-spin` | it takes the checkbox's place in the processing state (`:240`) — a 1-for-1 swap |
| `ENROL-ROW-NAME` | {shortname} · {fullname} | row header | `enrol_methods.js:400-418` | `<th scope="row">` | shortname in bold + `·` + the **whole** fullname (`:407`) — it **does not truncate**. A hidden course gains `fa-eye-slash` + `visually-hidden` `hiddenfromstudents` text (`:408-418`) |
| `ENROL-ROW-CAT` | {category} | cell | `enrol_methods.js:420-421` | — | plain text since `545ba17` (it used to be a badge) |
| `ENROL-ROW-ROLE` | {role} | cell | `enrol_methods.js:423-424` | `data-region="row-role"` | filled **only** when `configured` (`:226-228`); it is the **instance's effective** role, not the one in `ENROL-ROLE` |
| `ENROL-STATUS` | Configured / Processing / Not configured | badge | `enrol_methods.js:426-437` | `data-region="row-status"` · `-icon` + `-text` | the pill is a `span.badge` with `<i data-region="row-status-icon">` + `<span data-region="row-status-text">` (`makeRow`, `:426-437`); `paintRow` (`:223-225`) sets the colour class (`STATUS_BADGES`, `:89-93`), the icon class **per method** (`'fa ' + methodIcon + ' me-1'`) **and** writes the status word into `-text`. The visible **text** is Configured/Processing/Not configured — deliberate: the Behat assertion "Not configured" still holds; the per-method icon (`fa-users`/`fa-user-plus`) went in without touching the text. It reflects **only** the selected method+cohort (`rowStatus`, `:192-194`) |
| `ENROL-TOGGLE-STATUS` | Enrolment enabled / disabled | button | `enrol_methods.js:441-453` | `data-action="enrol-toggle-status"` | **was missing entirely** (`a5ef9a8`). It only appears if `configured` (`:231`); `fa-eye`/`fa-eye-slash` icon (`:233`). It calls `set_enrol_instance_status` (`:608-617`), repaints the twins (`:619-627`), **flashes** (`:626`) and emits a toast (`:629`); the button is disabled in a `finally` (`:606`, `:630-631`) |
| `ENROL-INFO` | Details | button | `enrol_methods.js:454` | `data-action="enrol-info"` | via `iconButton('enrol-info', 'fa-circle-info', …)` — the visible text is the accessible name |

## Actions footer

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-SELCOUNT` | N selected | counter | `enrol_methods.mustache:104` | `data-region="enrol-selcount"` | `central_enrol_selcount`; `state.selected.size` (`enrol_methods.js:274-279`) |
| `ENROL-PROC` | N processing | indicator | `enrol_methods.mustache:105-108` | `data-region="enrol-proccount"` · `hidden` | `fa-spinner fa-spin` (`:106`) + text at `:107`. Hidden when `pending.size === 0` (`enrol_methods.js:280-289`) |
| `ENROL-REMOVE` | Remove · {method} | button | `enrol_methods.mustache:110-113` | `data-action="enrol-remove"` · `disabled` | `btn-outline-danger`; born disabled, enabled only with a selection (`enrol_methods.js:290-292`). Not a static `{{#str}}`: it carries `<i data-region="enrol-remove-icon">` + `<span data-region="enrol-remove-text">`; `setActionLabels` (`:302-311`) sets the method's icon and the `central_enrol_remove_method` text = "Remove · <method>". The mustache keeps the generic `central_enrol_remove` as the pre-JS fallback label (`:112`) |
| `ENROL-APPLY` | Apply · {method} | button | `enrol_methods.mustache:114-117` | `data-action="enrol-apply"` · `disabled` | `btn-primary`; same rule (`enrol_methods.js:290-292`). `<i data-region="enrol-apply-icon">` + `<span data-region="enrol-apply-text">`; `setActionLabels` (`:302-311`, synchronous) sets the `central_enrol_apply_method` text = "Apply · <method>" (fallback `central_enrol_apply`, `:116`). Called from `init` (`:978`) and from `applyMethodChange` (`:768`) on every method change; the 4 resolved labels (apply/remove × cohort/self) are **preloaded** in the 2nd `getStrings` of `loadLabels` (`:131-136`, with the reason at `:128-130`), so the repaint is synchronous (`method` = `central_enrol_method_cohort`/`_self`) |

## Modals

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `ENROL-CONFIRM` | Remove enrolment method | modal | `enrol_methods.js:647-659` | `Notification.saveCancelPromise` | **title** = `central_enrol_confirm_remove_title` (`:650`) — that is what Behat finds the dialogue by, not the word "Confirmation". The `central_enrol_confirm_remove` body is an **object** placeholder (`{$a->method}` + `{$a->count}`), and the JS call passes `{method: <name>, count: courseids.length}` (`:651`); button = core str `remove` (`:652`). Cancelling **returns** without queueing (`:656-658`) |
| `ENROL-DETAIL` | {fullname} | modal | `enrol_methods.js:839-862` + `enrol_detail.mustache` | `Modal.create({large: true})` (`:859`) · `setRemoveOnClose(true)` (`:860`) | a label/value table: category (`:55-58`), visible (`:59-62`), competency (`:63-66`) and **the two method rows** (`:67-74`) — whose `<th>`s (cohortlabel/selflabel) carry a method icon (`fa-users` `:68` / `fa-user-plus` `:72`) — assembled by `statusLine` (`enrol_methods.js:807-830`), which composes status + date + `Inactive` + role. Everything is **pre-localised** in the JS: the template has no `{{#str}}` |
| `ENROL-DETAIL-LINK` | Open course | link | `enrol_detail.mustache:77-81` | `target="_blank" rel="noopener noreferrer"` | `/course/view.php?id=` — new tab |

## Business rules

### The tab's two locks are different — and one of them is mismatched

The **tab** is gated on `canmanageenrol`, which `plans.mustache:136` feeds with **`{{canmanage}}`** =
`moodle/competency:templatemanage` **in context** (`dynamictabs/plans.php:98`, `:334`). The footer
**link** (`PART-LINK-ENROL`) wants `canenrolpage` = `moodle/site:config` **at system level**
(`:243`). Therefore: **a template manager sees the tab and does not see the link** — and the link is
exactly the fix `ENROL-DISABLED` asks for. The 5 WSes re-require `templatemanage` in the template's
context (e.g. `list_enrol_competencies.php:104`, `queue_enrol_action.php:109`), so the tab's lock is
the real one; the link's only decides whether the shortcut appears. It is documented nowhere else.

### The mount latch and the blank pane on first mount — both closed

**How it used to be.** In `participants_manager.js`, `enrolmounted = true` (like
`usersmounted`/`rolesmounted`) was written **before** the await and `mountEnrol(...)` **was not
awaited** (`.catch(notifyError)`). If the mount rejected, the toast appeared and the latch stayed
`true`: coming back to the tab did **not** try again, and with `setRemoveOnClose(true)` the only
recovery was **closing and reopening the modal**. The Cohorts pane was worse — mounted once on
`shown`, **with no latch at all**, and since the Cohorts tab itself does not run `ensureMounted`, a
default pane that failed had no recovery whatsoever inside the modal.

**The latch — fixed in `c96a3e9` (2026-07-16).** All four mounts go through a single
`startMount(key, mountfn, selector)` (`participants_manager.js:198-210`) over one `mounted =
{cohorts, users, roles, enrol}` table (`:191`). It **claims the latch synchronously**
(`mounted[key] = true`, `:202`) — a double click still fires a single mount — and **releases it in the
`.catch`** (`mounted[key] = false`, `:207`), so the **next activation of the tab tries again**.
Cohorts joined the table (`:237`, and via `ensureMounted` at `:243-248`), so re-clicking the default
tab recovers it too. Release-on-catch is only safe because a released latch always means an
**unwired** pane: Cohorts and Roles do a `replaceNodeContents` clear and wire **fresh child nodes**, so
a remount discards the old listeners and starts clean.

**A correction this map makes against itself: enrol is NOT idempotent under `replaceNodeContents`.**
`mount` (`enrol_methods.js:1082-1112`) clears the container with `replaceNodeContents` (`:1085`), but
that only empties the **children** — `wireEvents` (`:1103`) **delegates** the `click` listener on the
**container element itself** (`state.root`, `:990`), which **survives** the clear. A naive remount
would stack a second set of listeners. That is why the only post-wiring await is **swallowed into a
toast**: `await init(state).catch(notifyError)` (`:1108`, with the reason in the comment at
`:1104-1107`). A **post-wiring** failure **resolves** the mount — the latch stays `true`, no re-click
redoes it, and there is **exactly one** wired state. This is why enrol cannot simply
release-and-remount like Cohorts/Roles, and why the *swallow* at `:1108` is **mandatory**, not
optional.

**The blank pane on first mount — closed in `c2d9471` (2026-07-16), with the 4th door.** The three
success regions are born `hidden` and `init` is what reveals **one** (`:954-967`). If the initial
load's `Promise.all` (`:918-934`) rejected — WS down, network dropping —, `init` **used to** exit by
exception before any `hidden = false`, the error was swallowed by the `.catch` at `:1108`, and all
three regions stayed hidden: blank pane, no refresh within reach, recovery only by reopening the
modal. **Today** the initial load runs inside a `try` (`:917-934`); in the `catch`, `init` **reveals
`ENROL-ERROR`** (`error.hidden = false`, `:939`), **hides** the other three (`:940-942`) and
**rethrows** (`:943`) — the `.catch` at `:1108` still emits the toast. The host's latch stays `true`
(the *swallow* remains deliberate), and the retry is the **modal header's refresh**: `mount` returns
`{refresh: () => init(state)}` (`:1111`) and the host's `attachRefresh` consumes it
(`participants_manager.js:232`, `refreshActiveTab` callback at `:213-229`).

The **late** failure (`loadCompetencies`, `:980`) was always recoverable and stays **deliberately
outside** the `try`: `main.hidden = false` has already run at `:967`, so `enrol-main` is visible and
the header refresh re-runs `init(state)`. With `ENROL-ERROR` closing the **early** failure and
`enrol-main` covering the **late** one, `mod-participants.md`'s conclusion ("only the refresh lets you
try again") holds for **every** state: the `ENROL-EMPTY`/`ENROL-DISABLED` alerts (where `init`
**succeeded** and revealed an alert), the late failure and the early failure.

### Concurrency — dedup by `(course, method, cohort)`

Each combination is an **independent adhoc task**; different combinations run in parallel. The key is
`process_enrol_method::key($courseid, $method, $cohortid)` (`:102`), `pending_map()`
(`:114`) is consulted under the queue lock before enqueueing (`queue_enrol_action.php:144-148` →
`status = 'skipped'`) and execution serialises on the Lock API (`process_enrol_method.php:206-209`,
60 s timeout → `central_enrol_busy`). The JS only marks `processing` on what did **not** come back
`skipped` (`enrol_methods.js:671-675`). The same course stays free for the **other** combination — which
is why `pending` is rebuilt from scratch on every method change (`:769-777`).

### The poll

`POLL_MS = 5000` (`:41`), `setInterval` only while there is something `pending` (`ensurePolling`,
`:723-733`). Each turn queries `get_enrol_queue_status` (`:693-696`) and flips the finished rows to
`configured`/`notconfigured`, with a yellow **flash** (`:708`). The timer stops by itself when
`pending` empties (`:712-714`) **or** when the root leaves the DOM (`!state.root.isConnected`, `:688`)
— which is what stops the poll from outliving the modal's `setRemoveOnClose`. House rule respected:
**row changed → flash** (`:626`, `:708`), never a whole-pane spinner.

### Contrast: why `bg-secondary` comes with `text-dark`

The comment at `enrol_methods.js:87-88` records the decision, and it measures out: Boost's `secondary`
is a light grey (`#ced4da`) and the badge's default text is white — **1.49:1**, fails. The pair the
code ships, `bg-secondary` + `text-dark` (`#1d2125`), gives **10.84:1**. It applies to the
`ENROL-STATUS` "Not configured" (`:92`) and to `ENROL-GROUP-COUNT` (`enrol_group.mustache:46`).

## Refresh (`mtube: refresh`) — where it lives, and what this map pins down

The decision and the checks live in [`bar-contextbar.md`](bar-contextbar.md). What **this** map pins
down, independently:

- **This pane's refresh affordance is the modal header's refresh**, not an internal button: `mount`
  returns `{refresh: () => init(state)}` (`enrol_methods.js:1111`) and the host wires it with
  `attachRefresh` (`participants_manager.js:232`). The *busy* discipline the four extinct buttons
  lacked — clicking twice fired two concurrent `init`s — **exists** in the replacement:
  `modal_refresh.js:70-84` disables the button (`:74`), adds `fa-spin` (`:75`) and clears both in a
  `finally` (`:80-83`). It is the same shape as `format_mtube`'s `course_report.js`
  (`:286-299`, via the sourcemap's `sourcesContent`).
- **Measured precision (this map confirms it independently):** `reloadPane` exists
  (`tabs.js:69`) and has **24 calls across 5 modules** — `structure` 9, `frameworks` 6, `plans` 6,
  `context` 2, `competency_browser` 1. (A `grep -rn reloadPane amd/src/` returns **36** lines: the 24
  calls + 1 definition at `tabs.js:69` + 5 imports + 6 comments — e.g. `frameworks.js:18` and
  `plans.js:787`. Counting the 36 as calls is the easy mistake.) There **is** a UI control firing it
  today: the contextbar's `data-action="refresh"` button (`context.js`, 2 of the 24).
- **This pane does not use `reloadPane`** and is not an exception to anything: its refresh is
  `init(state)` because it is a **modal pane**, not a `core/dynamic_tabs` tab pane; `reloadPane` would
  not reach it.
- **Traceability of the mtube refs:** `format_mtube` has **no** `amd/src` in this checkout, only
  `amd/build`. An mtube JS ref by `file:line` **resolves for nobody** — which is why this map cites
  mtube by **symbol name**. A `grep` on disk for that `.js` finding nothing is **expected**, not
  absence.
