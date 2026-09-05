# Field map — `MOD.LINKS` · Course↔activity links modal (as-is)

Modal opened by the **🔗 Courses & activities** button in the Competencies tab's **sticky footer**. It
manages a competency's links at **two levels** — course and activity — each with its own **outcome**,
saved **immediately** (there is no save button). Each linked course is a **card** carrying a count and
a completion-rule badge; the activities live **inside** the card's border and load **lazily** on first
expansion.

The shell is Mustache; **everything else is built in JS**. The Mustache is **55 lines** and delivers 6
controls — `competency_links.js` is **946** and delivers the rest.

- **Mustache:** [`competency_links.mustache`](../../../templates/central/competency_links.mustache) (55, shell only) · trigger in [`structure_footer_actions.mustache`](../../../templates/central/structure_footer_actions.mustache) (`:53-56`)
- **AMD:** [`competency_links.js`](../../../amd/src/central/competency_links.js) (946) · [`course_datasource.js`](../../../amd/src/central/course_datasource.js) (98, the autocomplete's datasource) · uses `errors.js` (`notifyError`, `:68-80`) and the shared helper `flash.js` (`flashRow`, imported at `js:32`)
- **WS:** 9 functions, all verified in `db/services.php` — see the section "The 9 web service functions"
- **CSS:** [`styles.css:7367-7536`](../../../styles.css) (card, activity row, badge, activity search, hidden selection strip) · [`styles.css:7583-7596`](../../../styles.css) (the autocomplete chevron) · [`styles.css:5218-5324`](../../../styles.css) and [`:5074-5103`](../../../styles.css) (the header controls and the close chip)
- **Screen in the DS:** [`screens/mod-links.html`](../screens/mod-links.html) (with the scripted, measured expansion)

> **How this map's refs are derived.** Every `file:line` ref is obtained by **opening the file** and
> reading the block's boundaries (opens at the selector/symbol, closes at the brace), never by
> arithmetic over an earlier ref — a block deleted above shifts everything below it and an inherited
> ref lies without warning. **Core** refs (`core/modal`, `core/modal_save_cancel`, Boost,
> `lib/db/install.xml`) and `format_mtube` ones are cited **by symbol/selector, with no line number**:
> the plugin supports 4.5 through 5.2 and the number would diverge between branches; and mtube does
> not version `amd/src`.

## Trigger (in the Competencies tab, outside the modal)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ACTION` | Courses & activities | button (trigger) | `structure_footer_actions.mustache:53-56` | `data-action="links"` · `fa fa-link` | str `central_links_button`. Lives in the tab's shared **sticky footer**, not in a row. `structure.js:1263-1272` calls `openLinksModal({competencyid, competencyname, courseoutcomes, moduleoutcomes, onClose})` (imported at `:38`) with the active row's `data-*`. Both outcome arrays come from the **server**, not from JS |
| `MOD.LINKS-COUNT-REFRESH` | `[no label]` | close effect | `competency_links.js:937-944` → `structure.js:909-921` | `onClose(count)` | the modal counts `state.rowsEl.children.length` (`js:941` — each child of the container is **one** card) and hands it back; `updateCourseCount` writes it to `row.dataset.courses` (`:913`), repaints the detail only if the row is still the active one (`:914-919`) and **flashes** the row (`:920`). **No pane reload** — the tree's selection and expansion survive. `count` can be `null` (modal closed before `shown`), and the handler returns silently (`:910-912`) |

## Modal shell (Mustache)

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-TITLE` | Courses & activities — {name} | title | `competency_links.js:831` (str), `:862` (`Modal.create`) | str `central_links_title`, `$a` = name | **plain** `core/modal`, no `footer` in the config. **`large: true`** (`:862`, `modal-lg`) is the base width; `MOD.LINKS-EXPAND` takes it full screen (`width:100%`, full height, square corners) when expanded. `setRemoveOnClose(true)` at `:863` |
| `MOD.LINKS-ROOT` | `[no label]` | region/root | `competency_links.mustache:32` | `data-region="competency-links"` · `.local-dimensions-central-links` | both delegated listeners (click `js:921` and change `:922-933`) land here, **not** on the modal root |
| `MOD.LINKS-HIDDENFW` | This competency belongs to a hidden structure and cannot be linked to courses. | alert | `competency_links.mustache:33-35` | `data-region="hiddenframework"` · `role="status"` · `tabindex="-1"` · ships `hidden` | str `central_links_hiddenframework`. **Not a decorative note:** `js:468` turns the alert on and `js:473` **hides the entire** add-course block (`hiddenframeworkEl.hidden = response.canlink` / `addsel.parentElement.hidden = !response.canlink`). **Why hide rather than `disabled`:** core's `enhance()` swaps the `<select>` for an input of its own plus a downarrow that opens the suggestion list **ignoring** the select's `disabled` (comment at `js:469-472`), so disabling would be inert — the user would type, pick a course and only then hit the wall, with `api::add_competency_to_course` throwing into `notifyError`. Hiding removes input, downarrow and label from view **and** from the tab order; the alert's `tabindex="-1"` makes it the focus target when the block disappears (see "Pagination by exclusion"). **It was never a security hole** — core blocks it server-side — it was the UI promising what it does not deliver. The WS's `canlink` is literally the structure's visibility — `get_competency_links.php:106`: `(bool) $competency->get_framework()->get('visible')`. **Existing** links stay listed, with an editable outcome: the block applies only to **new** ones |
| `MOD.LINKS-ADD-LABEL` | Add course | label | `competency_links.mustache:37-39` | str `central_links_addcourse` · `for="local-dimensions-links-add"` | a real `<label>`, with `for` — unlike `MOD.RELATED-ADDLABEL`, which targets a tree and is therefore a `<div>` |
| `MOD.LINKS-ADD` | Link a course — search by name, short name or ID number… | autocomplete | `competency_links.mustache:40-44` | `data-region="course-add"` · `data-competencyid` · `data-exclude` · `.form-select` | str `central_links_addcourse_placeholder`. The autocomplete's **selected-items strip** is hidden (`styles.css:7529-7536`, together with those of the participants modal's pickers): the picker links immediately and resets itself, so the strip would only flash the chosen course before the card appears. See the picker section below |
| `MOD.LINKS-ROWS` | `[no label]` | JS container | `competency_links.mustache:46` | `data-region="course-rows"` | **one child = one course card**, and that is what `MOD.LINKS-COUNT-REFRESH` counts |
| `MOD.LINKS-EMPTY` | No courses linked. | empty state | `competency_links.mustache:47-49` | `data-region="course-empty"` · ships `hidden` | str `central_links_nocourses`. The condition is a **count, not a position**: `refreshListState` (`js:437-440`) shows it with `state.emptyEl.hidden = state.total > 0` (`:438`), and `total` is the **full** link count the server returns (`get_competency_links.php:128-132`). It leaves the field in `onAddCourse` (through `refreshListState`, `:761`) and in `removeCourse` (`:647`), which decrements `total` (`:646`) — unlinking the **last** course lights the message instead of leaving the panel blank. **The predicate is not its sibling's** — `related_competencies.js`'s `removeRelated` (`:154-179`) solves this with `children.length > 0` (`:172`), and there it is right because **that list is not paginated** (it rebuilds everything from the server on each read, `related_competencies.js:104-117`); this one reads `total` precisely because it paginates, and so does not have to consult "Load more". It leaves the screen without a reload |
| `MOD.LINKS-LOADMORE` | Load more | button | `competency_links.mustache:50-53` | `data-action="loadmore"` · wrapper `data-region="loadmore-wrap"` ships `hidden` | str `central_links_loadmore`. Pages of **25** (`PAGE_SIZE`, `js:45`, sent as `limitnum` **without** `limitfrom`). `refreshListState` (`:439`) hides it with `state.loadMoreEl.hidden = state.rowsEl.children.length >= state.total` — **cards on screen vs. total**, both counts. **A button, not a sentinel** — unlike `MOD.RELATED`'s tree, there is no infinite scroll here; and a `state.loading` (`:451-453`) discards a re-entrant click. While visible it is also `restoreFocus`'s **preferred** target (`js:606-608`) |
| `MOD.LINKS-TOAST` | `[no label]` | feedback | `competency_links.js:914` (region), `:911` (`ModalEvents.shown`) | `addToastRegion(modal.getBody()[0])` | **5** `addToast` of its own: `courseremoved` (`:652`), `activityadded` (`:675`), `activityremoved` (`:710`), `courseadded` (`:762`) and `saved` (`:927`) — plus the network one from `notifyError`. See the toast section below: this file is the pattern's reference case |

## The add-course picker

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ADD-ENH` | `[no label]` | wiring | `competency_links.js:816-821` (`bindPicker`) | `enhance(selector, false, DATASOURCE, placeholder)` (`:820`) | `false` = **single-select**. Called on `shown` (`:934`) — core's `enhance` resolves through `document.querySelector`, so before `modal.show()` it would find nothing. **`enhance` hides the original `<select>`** — which is why `restoreFocus` targets the enhanced input (`SELECTORS.addInput`, `js:54`) and never `state.addsel` |
| `MOD.LINKS-ADD-RESET` | `[no label]` | re-render | `competency_links.js:764` | `Templates.replaceNodeContents(addsel.parentElement, state.addshtml, '')` | a `core/form-autocomplete` single-select **has no clear API**: the house re-renders the whole `<div class="mb-3">` from the HTML stored on `shown` (`:920`) and redoes `bindPicker` (`:766`). That is why `state.addsel` is **re-queried** at `:817` on every bind |
| `MOD.LINKS-ADD-EXCL` | `[no label]` | exclusion | `course_datasource.js:85-87` | `data-exclude` (CSV of ids) | read through `element.dataset` **on every search** (`processResults`), never through jQuery `.data()` — which would cache it. `state.excluded` is a `Set` maintained at the three points that touch the list: `loadCourses` (`js:482`), `onAddCourse` (`:759`) and `removeCourse` (`:641`, `:644`). That `Set` is also **the pagination cursor**: it is what goes to the WS as `excludecourseids` (`js:463`) |
| `MOD.LINKS-ADD-SUG` | {name} `{short name}` | suggestion | `course_datasource.js:90-96` | `.local-dimensions-central-links-code` | the short name goes in **monospaced** inside the label; `escapeHtml` (`:66-70`) escapes both sides, because the autocomplete label is HTML. `styles.css:7509-7527` gives the code its own colour **and** a `:hover`/`[aria-selected]` so it does not vanish on the highlighted suggestion. When the server finds more than one page, the `transport` returns **the warning string** instead of options (`course_datasource.js:49-53`, str `search_toomany`) — and `processResults` passes it through untouched (`:81-84`) |
| `MOD.LINKS-ADD-CHEVRON` | `[no label]` | visual tuning | `styles.css:7583-7596` | `.local-dimensions-central-page .form-autocomplete-downarrow` | the selector looks like a page scope (core's modal is **not** born inside the page `<div>`: `core/modal` does `document.body.append(this.attachmentPoint)`), except that `local-dimensions-central-page` is a **body class** (`central.php:57`, `$PAGE->add_body_class`). Since the modal is a child of `body`, the descendant matches and **the chevron reaches here**. `font-size:0` kills core's glyph; the `::before` puts Font Awesome's `\f107` in, matching the `.form-select` chevron. The loading spinner keeps its own size (`:7210-7212`) |

## The course card (built in JS)

`makeCourseRow` (`competency_links.js:341-427`) assembles **one** card: header (toggle, decorative
`fa-graduation-cap` at `:361-363`, name, short name, counter, trash button) + outcome row +
whole-course note + activities container, all inside **one** border
(`.local-dimensions-central-links-card`, `styles.css:7377-7383`).

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-CARD` | `[no label]` | card | `competency_links.js:341-427` | `data-courseid` · `data-fullname` (`:344-345`) | `data-fullname` exists only so the removal confirm can read the name without re-querying the DOM (`js:624`). There is no page marker on the card: pagination is by **id exclusion**, not by position, so whoever calls `makeCourseRow` (the picker **or** `loadCourses`) does not need to tag the row |
| `MOD.LINKS-CARD-TOGGLE` | Course activities | button (expand) | `competency_links.js:350-359` | `data-action="toggle-course"` · `aria-label` · `aria-expanded` · `fa fa-chevron-right` | the accessible name is the str `central_links_toggleactivities` ("Course activities"), set at `js:355` from the batch (`:856`); `aria-expanded` carries the state. `toggleCourse` (`js:566-581`) swaps the icon to `fa-chevron-down` and loads on the **first** open (`:575`). **It is also the preferred focus target of both removals** (`:651`, `:708-709`) — chosen precisely because it is the row's only control that is **always** rendered: the trash button is gated by capability (`:393`) |
| `MOD.LINKS-CARD-NAME` | {course name} | link | `competency_links.js:365-378` | `target="_blank"` · `rel="noopener noreferrer"` | becomes a `<span>` when the WS sends no `courseurl` (the user cannot see the course). As a link it gains `fa-external-link` + an `.sr-only` "opens in new window" (`decorateExternalLink`, `js:137-146`, str **core's `opensinnewwindow`** — no new string) |
| `MOD.LINKS-CARD-SHORT` | {short name} | text | `competency_links.js:380-382` | `.font-monospace small text-muted` | |
| `MOD.LINKS-CARD-COUNT` | 1 activity / {n} activities / Whole course | counter | `competency_links.js:384-386` (node), `:185-198` (`updateCourseMeta`) | `data-role="modcount"` | strs `central_links_modulecountone` / `central_links_modulecount` / `central_links_wholecourse`. **Three states, not two:** with 0 linked the counter **becomes the label "Whole course"** and the note appears. **Only the linked count, no denominator** — there is no "2 / 5 activities"; the course's total activity count is never shown. `{count}` is substituted in JS (`:192`) over a template requested **once** in the batch (`:854`) |
| `MOD.LINKS-CARD-REMOVE` | Remove course | button | `competency_links.js:393-395` (`iconButton`, `:101-116`) | `data-action="remove-course"` · `fa fa-trash` | `title` + `.sr-only`. `removeCourse` (`js:622-653`): `deleteCancelPromise` confirm (`:630`) → WS (`:635`) → **idempotent guard** `if (!state.excluded.delete(id)) return` (`:641-643`) → removes the card (`:645`) → `total -= 1` (`:646`) → `refreshListState` re-lights the empty state + "Load more" (`:647`) → **restores focus** (`:651`) → **toast** (`:652`). Rendered only when `course.canmanage`. **The button stays alive across both `await`s** (confirm and unlink) — that is the race the `excluded.delete` guard (which returns `false` on the second pass) makes idempotent, with the reason written at `:638-640` |
| `MOD.LINKS-CARD-OUTCOME` | Outcome: | select | `competency_links.js:397-407` | `data-role="course-outcome"` · `name="course-outcome"` | str `central_links_outcomeprefix`. The options come from `state.courseoutcomes`, passed by the **server** through `opts`. Saves on `change` (`js:922-933`) → `saveOutcome` (`:720-734`) → "Saved" toast (`:927`) + flash (`:928`). `disabled` when `!canmanage` |
| `MOD.LINKS-CARD-BADGE` | Completion rule configured / Create completion rule | badge | `competency_links.js:407` → `makeCompletionBadge` (`:157-175`) | `.local-dimensions-central-links-badge-ok` / `-warn` | strs `central_links_completionrule_ok` / `_missing`. Green with `fa-check`, amber with `fa-exclamation-triangle`. Becomes an **`<a>`** (to `course/completion.php`) only when the WS sends `completionurl`; with no url it is a `<span>` — the same node, two tags (`:159`). `styles.css:7400-7441` |
| `MOD.LINKS-WHOLENOTE` | Linked at course level, without a specific activity. | note | `competency_links.js:409-413` | `data-role="wholecoursenote"` · ships `hidden` | str `central_links_wholecoursenote`. Toggled **only** by `updateCourseMeta` (`:190`, `:193`, `:196`), always in counterpoint to the counter |
| `MOD.LINKS-ACTS` | `[no label]` | lazy container | `competency_links.js:415-419` | `data-role="activities"` · `data-loaded="0"` · ships `hidden` | `.local-dimensions-central-links-acts` (`styles.css:7394-7397`) — the indentation and the dashed border that put the activities **inside** the card. `data-loaded` is the load's memory: `toggleCourse` calls `loadActivities` only when it is `!== '1'` (`js:575`), and `addModule`/`removeModule` zero it (`:672`, `:703`) to force a re-read |

## The activities (inside the card, lazily loaded)

`loadActivities` (`competency_links.js:519-556`) makes **one** read that returns `linked` +
`available` together, and rebuilds the whole container. **It recomputes the card's count from the
fresh data** (`:555`) — the point where a stale server count corrects itself.

| ID | Label | Type | Origin | Data | Rule / notes |
| --- | --- | --- | --- | --- | --- |
| `MOD.LINKS-ACTS-HDR` | Linked activities · outcome on activity completion | heading | `competency_links.js:536-539` | str `central_links_activitieshdr` | the string **is** the explanation of the modal's axis: the activity's outcome fires on **its** completion, not the course's |
| `MOD.LINKS-ACTSEARCH` | Add an activity — search by name… | search field | `competency_links.js:271-331` (`makeActivitySearch`), input `:276-283` | `data-role="activity-search"` · `id` unique per course (`:279`) | strs `central_links_addactivity` (`aria-label`) / `_placeholder`. **Client-side search**, no WS: it filters the `available` that already came in the same read. Built only when `response.canmanage && response.available.length` (`js:540`) — a course with no free activity gets no field |
| `MOD.LINKS-ACTSEARCH-FOLD` | `[no label]` | matching rule | `competency_links.js:65` (`fold`), used at `:291-292` | `toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')` | **folds accents on both sides** — "prova" finds "Prova diagnóstica"; "trabalho" finds "Trabalho de Lógica". Matches by `includes`, not by prefix. **Only the name** enters the match: the module type is displayed but **not** searchable (`:292`) |
| `MOD.LINKS-ACTSEARCH-LIST` | `[no label]` | dropdown | `competency_links.js:285-288`, render `:290-318` | `data-role="activity-search-list"` · ships `hidden` | opens on `focus` **and** on `input` (`:320-321`), closes on `Escape` (`:322-326`) and on an outside click — and the outside click is swept at the **top** of the delegated `onClick` (`js:779-786`), before any routing, for every `activity-search` that does not contain the target. `styles.css:7476-7493` |
| `MOD.LINKS-ACTSEARCH-ITEM` | {name} + {type} | button | `competency_links.js:301-315` | `data-action="add-module"` · `data-cmid` | `addModule` (`js:663-676`): WS (`:668`) → zeroes `data-loaded` (`:672`) → reloads (`:673`) → **flashes the new row** (`:674`, `flashRow`) → toast (`:675`). The flash targets `[data-cmid="…"]` **after** the reload, because the old node is already dead. `styles.css:7484-7506` |
| `MOD.LINKS-ACTSEARCH-NONE` | No matching activities. | search empty state | `competency_links.js:294-299` | str `central_links_nomatches` | |
| `MOD.LINKS-ACT` | `[no label]` | row (two lines) | `competency_links.js:211-260` (`makeModuleRow`) | `data-cmid` · `data-name` (`:214-215`) | `.local-dimensions-central-links-act` (`styles.css:7444-7452`). **Two lines:** name+type+✕ on top (`:217-230`), outcome+badge below (`:232-239`) |
| `MOD.LINKS-ACT-NAME` | {activity name} | text | `competency_links.js:219-222` | `.local-dimensions-central-links-actname` · `title` = name | **clamped in CSS** to 2 lines (`styles.css:7448-7463`), with the whole name in the `title` — that is the answer to a long activity name |
| `MOD.LINKS-ACT-MTYPE` | {module type} | tag | `competency_links.js:124-129` (`mtypeTag`) | `.local-dimensions-central-links-mtype` | the module's **localised** name, from the WS (`styles.css:7459-7475`). Appears on the row (`:224-226`) **and** in the search suggestion (`:311-313`) |
| `MOD.LINKS-ACT-REMOVE` | Remove activity | button | `competency_links.js:228` (`iconButton`) | `data-action="remove-module"` · `fa fa-times` | **✕, not 🗑** — the trash belongs to the course, the times to the activity: the difference in glyph is the difference in scope. `removeModule` (`js:685-711`): confirm (`:693`) → WS (`:698`) → reloads the course's container (`:702-704`) → **restores focus** (`:708-709`) → toast (`:710`). Focus is resolved **after** the reload, which empties and repopulates the container, and lands **inside** the card the user was in: the next ✕, or the card toggle when the last activity leaves. Only with `module.canmanage` (`:227`) |
| `MOD.LINKS-ACT-OUTCOME` | `[no label]` | select | `competency_links.js:234-236` | `data-role="module-outcome"` · `name="module-outcome"` | options from `state.moduleoutcomes` — **a different array** from the course's, and that is why `saveOutcome` has two branches (`js:722-733`) and two WSes. `aria-label` = str `central_links_outcome` |
| `MOD.LINKS-ACT-BADGE` | Completion rule configured / Create completion rule | badge | `competency_links.js:238` | the same `makeCompletionBadge` | here the url is the `editurl` (the module's settings), not `course/completion.php` — same component, different destination |
| `MOD.LINKS-ACT-SHARED` | {n} other competencies are linked to this activity… | alert | `competency_links.js:241-258` | `.alert-warning` · link `central_links_opencompetencies` | strs `central_links_sharedwarning` / `_sharedwarningone` (**its own singular**, chosen at `js:531`). Emitted only with `sharedcount > 0` **and** non-empty text (`:241`). It is the warning that the completion rule **is not yours**: touching it affects the other competencies. The N strings are requested **in parallel** and **outside** the batch (`js:527-533`), because they depend on the row's data |
| `MOD.LINKS-ACTS-EMPTY` | No activities linked in this course. | empty state | `competency_links.js:548-552` | str `central_links_noactivities` | it is the **activities'** empty state; not to be confused with `MOD.LINKS-EMPTY`, which is the courses' |

## The toast — this file is the pattern's reference case

`competency_links.js:914` calls `addToastRegion(modal.getBody()[0])` on `ModalEvents.shown`
(`:911`), with the reason written in the comment just above (`:912-913`). It is one of the plugin's
**4** modules with this pattern — `participants_manager.js:236`, `related_competencies.js:269`,
`frameworks.js:316` and this one —, counted with `grep -rn 'addToastRegion(' amd/src/`, **with the
parenthesis**: without it there are **11** lines in **5** files, because it also picks up the hosts'
4 `import`s plus three lines of the `toast.js` wrapper (the docblock `:21`, the core import `:29` and
the re-export `:41`).

The reason is `z-index` arithmetic, and the two numbers are the ones the plugin's `CLAUDE.md` fixes
as house rule:

- the page's `.toast-wrapper`: **`z-index: 1051`** (`theme/boost/scss/moodle/core.scss`).
- `$zindex-modal`: **`1055`** (`theme/boost/scss/bootstrap/_variables.scss`).

With no region of its own, a toast fired from here would land in the page wrapper and sit **behind**
the dialog. Core's comment on the line **above** `z-index: 1051` still says it sits *"above any
modals"* — and it has **aged**: in Bootstrap 4 `$zindex-modal` was 1050 and the arithmetic worked;
the jump to BS5 raised the modal to 1055 and left the wrapper underneath. Core removes the region
itself on close (`removeToastRegion` in `core/modal`), so there is no leak and global `z-index` is
**not** touched.

**This modal is the one that depends on it most:** it has **5** `addToast` of its own —
`courseremoved` (`js:652`), `activityadded` (`:675`), `activityremoved` (`:710`), `courseadded`
(`:762`) and `saved` (`:927`) —, plus the network one `notifyError` raises. None of those actions
closes the dialog, so **all** of them need visible confirmation in place. **Three** of them also
**flash** the affected element, through the shared helper `flashRow` (`flash.js`, imported at
`js:32`), which is the other half of the pair: the toast says *what*, the flash says *where*. They
are `addModule` (`js:674`), `onAddCourse` (`:758`) and the outcome `change` (`:928`) — a
`grep -n 'flash' amd/src/central/competency_links.js` returns **4** lines: those three plus the
`import`. The two removals do **not** flash, and would have nowhere to: the element the action
affects leaves the DOM — which is exactly why they restore **focus** instead of flashing (see the
next section).

## Pagination by exclusion — and focus after a removal

**The page is defined by exclusion, not by position.** `loadCourses` (`js:448-491`) sends
`excludecourseids` = the set already on screen (`Array.from(state.excluded).join(',')`, `:463`) and
only `limitnum` — **no** `limitfrom` —, the server drops those courses with a `NOT IN` (see the WS
section) and always returns the **next unshown page**; `total` remains the **full** count. A
`grep -nE 'offset|data-paged'` over the module returns **nothing** (positive control:
`grep -n 'excluded'` finds **11** lines — the `Set` that occupies that place).

**Why it is not a numeric cursor.** A `state.offset` that only moves forward has three defects the
exclusion does not, and it is worth knowing which so as not to reintroduce it: (a) **removal skips** —
unlinking an already-fetched row shrinks the list **beneath** the cursor and the next "Load more"
asks for an index that no longer exists, a trap that arms at **26** links (25 render, the cursor
stops at 25, `25 >= 26` is false, the button stays and the course that moved up is never fetched);
(b) **the ordering has to be total**, otherwise the cursor skips or duplicates **even without a
mutation**, because course names repeat and the database may return the tied rows in a different
sequence on each call; (c) **the mirror image on add** — appending the new row without counting it
makes "Load more" re-fetch an index already on screen and **duplicate a card**, and it cannot be
closed with `offset += 1`, since the added course's position depends on its `fullname` and the client
cannot know it without re-reading. With exclusion by id none of the three exists: `onAddCourse`
(`:742-768`) only increments `total` and recounts through `refreshListState`.

Three guards hold off the doubles:

- **Idempotent removal** (`js:641-643`): `if (!state.excluded.delete(id)) return` — the `Set` is the
  truth of what is on screen; the second pass of a double click (the unlink resolves with
  `success: false` instead of throwing) does not decrement `total` again.
- **Idempotent addition** (`js:754`): `if (!state.excluded.has(id))` — a double submit returns the
  same course, and only the first assembles the card and counts.
- **`state.loading`** (`js:451-453`) discards a re-entrant "Load more" (the same page fetched twice
  would append double), and `loadCourses` further **skips** a course already on screen when appending
  (`:478-480`), closing the window in which an add through the picker lands mid-fetch and the
  server's page still carries it.

**The `ORDER BY` tiebreak stabilises the display, not the correctness.** The
`c.fullname ASC, c.id ASC` in `get_competency_links.php` (`:151`) and the comment just above it
(`:144-145`) say exactly that: with the exclusion guaranteeing that each course is fetched **exactly
once**, `c.id` does **not** guard against skips and duplicates — that is the exclusion's job — it
only keeps the **display order** stable between loads. Why `(fullname, id)` is a total order: the
query filters **one** competency and joins
`{competency_coursecomp} cc JOIN {course} c ON c.id = cc.courseid`, and core's **unique** index
`courseidcompetencyid` over `(courseid, competencyid)` (`lib/db/install.xml`, `UNIQUE="true"`)
guarantees **one** row per course in the result — so `c.id`, already a primary key, stays unique
across the set and always breaks the tie.

**Focus** is the other problem of the two removals, and the target it picks depends on what the list
offers. The confirm hands focus back to the button that opened it — the row's own trash button — and
the two removals then **detach that row**: untreated, focus would fall to the `<body>` and the
keyboard user would have to re-traverse the whole dialog (every keyboard removal; mouse users never
see it). `restoreFocus` (`js:592-613`) acts only **if** focus is already on the `<body>`
(`:593-595`), so it never steals focus from where the user put it. The fallback order is
`preferred || loadmore || picker || frameworkalert` (`:609`):

- **"Load more" takes precedence** while visible (`js:606-608`): on the paginated path the empty
  state does **not** appear when the last loaded row leaves, so the button — not the picker — is the
  way forward.
- **The picker is the autocomplete's *enhanced* input** (`SELECTORS.addInput`, `js:54`), **never**
  `state.addsel`: core's `enhance()` **hides** the original `<select>`, which is therefore not
  focusable. And it only enters **when its own block is visible** (`:602`) — in a hidden structure
  the add block disappears (`MOD.LINKS-HIDDENFW`), so the fallback **falls through to the alert** for
  the hidden structure, which carries `tabindex="-1"` precisely so that it can take focus
  (`:603-605`). Without the `!container.hidden` guard, the hidden input would still answer
  `querySelector` and beat the alert, dropping focus back on the `<body>`.

The preferred targets are the **always rendered** ones: the card toggle comes before the next card's
trash button, because the trash is gated by capability and the toggle is not. The twin in
`related_competencies` (`restoreFocus`, `:137-145`) carries none of these choices — its fallback is
just `preferred || filter`. No Behat scenario removes a course or an activity.

## The 9 web service functions

All verified in `db/services.php` (the key's line), with the call site in the JS. **The "Called at"
column always points at the `methodname` line** — the one that names the WS, which is the useful
anchor in a table indexed by WS name:

| WS | `db/services.php` | Called at | Role |
| --- | --- | --- | --- |
| `local_dimensions_get_competency_links` | `:229` | `js:457` | a page of linked courses + `total` + `canlink`. The **`excludecourseids`** parameter (`PARAM_SEQUENCE`, `VALUE_DEFAULT`, defined at `get_competency_links.php:60-65`) makes the **records** query drop the ids already on screen with a `NOT IN` (`:138-142`, `get_in_or_equal(…, 'ex', false)` at `:139`), while **`total`** stays the **full** count (`:128-132`). The **`ORDER BY c.fullname ASC, c.id ASC`** (`:151`) **only stabilises the display order** — correctness against skips and duplicates belongs to the exclusion (comment at `:144-145`); see "Pagination by exclusion" |
| `local_dimensions_search_linkable_courses` | `:237` | `course_datasource.js:46` | the autocomplete's search (name, short name, ID; excludes hidden ones). `ORDER BY c.fullname ASC` **with no tiebreak** (`search_linkable_courses.php:119`) — and the missing tiebreak does not affect this modal's pagination, which uses no offset |
| `local_dimensions_link_competency_course` | `:245` | `js:748` | returns **the course already assembled**, which is why `onAddCourse` can append the card without re-reading the list |
| `local_dimensions_unlink_competency_course` | `:253` | `js:635` | **resolves with `success: false` on a duplicate, instead of throwing** — the detail that forces the **removal** to be idempotent through the `excluded.delete` guard (`js:641-643`) |
| `local_dimensions_set_course_link_outcome` | `:261` | `js:725` | |
| `local_dimensions_get_competency_module_links` | `:269` | `js:523` | **one** read returns `linked` + `available` + `canmanage` |
| `local_dimensions_link_competency_module` | `:277` | `js:668` | |
| `local_dimensions_unlink_competency_module` | `:285` | `js:698` | |
| `local_dimensions_set_module_link_outcome` | `:293` | `js:731` | |

## The six reworks — what today's code confirms

Checked commit by commit against today's code, not against the message:

| Commit | Claim | Holds up? |
| --- | --- | --- |
| `fb8c725` | course count updated in place, no tree reload | **yes** — `js:937-944` → `structure.js:909-921` |
| `93e4f69` | course card | **yes** — `makeCourseRow`, `js:341` |
| `93e4f69` | **activities with checkboxes** | **NO** — reverted by `d7578b3` the same day. `grep -niE 'checkbox\|form-check'` over this modal's JS returns **nothing** (positive control: the same grep over `amd/src/central/` finds **seven** other hub modules, so it is not the pattern that failed) |
| `93e4f69` | completion badge | **yes** — `makeCompletionBadge`, `js:157` |
| `d7578b3` | activity search | **yes** — `makeActivitySearch`, `js:271` |
| `d7578b3` | two-line rows | **yes** — `makeModuleRow`, `js:211-239` |
| `d7578b3` | count correction | **yes** — `updateCourseMeta` recomputes from the fresh data, `js:555` |
| `7902bd8` | network-resilient errors | **yes** — the file's **11** `.catch(notifyError)`; `errors.js:68-80` sends a connectivity failure to a toast and keeps an application error in core's modal |
| `c10acd0` | string batching | **yes** — **24** `getString` in one `Promise.all` (`js:832-857`) and **24** `labels[…]` on the `state` (`:881-904`); `updateCourseMeta` is not `async` |
| `c10acd0` | unified chevron | **yes, and it reaches this modal** — `styles.css:7583-7596` under a **body** class (`central.php:57`), and core's modal is a child of `body` |
| `e0fe81d` | toast on course removal | **yes** — `js:652` |

## `MOD.LINKS-EXPAND` — expand/restore (and the refresh beside it)

This modal's header carries **three** plugin-owned controls, all with the same blue chip as the close
button, in the order **[refresh][expand][restore][close]**. The wiring chains the refresh **after**
the expander, in both of the hub's dense modals:
`attachExpander(dialog).then(() => attachRefresh(dialog, () => reloadCourses(state))).catch(notifyError)`
(`competency_links.js:909`); the participants one does the same with `refreshActiveTab`
(`participants_manager.js:232`, the function at `:213`).

**Refresh** (`central/modal_refresh.js`, `attach(dialog, onrefresh)`) injects **one**
`.local-dimensions-modal-refresh` button (`data-action="modal-refresh"`, icon
`<i class="fa fa-rotate">`, `:36-48`), anchored **before** the first
`.local-dimensions-modal-sizetoggle` — or, failing that, before the `.btn-close` (`:67-68`). The
label is core's `refresh` through `getString('refresh')` (`:63`) — **no new lang string**. The button
owns the **busy state**: on click it disables itself and puts `fa-spin` on the icon, runs
`onrefresh()` and clears both in a `finally` (`:70-84`), so a reload that fails never leaves the
button stuck. Here the reload is `reloadCourses` (`competency_links.js:501-509`): **a no-op while
`state.loading`** (`:502-504`), otherwise it empties the container (`rowsEl.textContent = ''`,
`:505`), clears the exclusion `Set` (`:506`), zeroes `total` (`:507`) and calls `loadCourses`, which
fetches **page 1** (`:508`).

**Expand/restore** comes from the shared module `central/modal_expander.js` (`attach(dialog)`).
**Both** buttons are always inserted (`makeButton`, `:60-72`), before the `.btn-close` (`:97-99`),
with the strs `central_modal_expand` / `central_modal_restore` (`:92-95`); the title yields width to
them through the rule `.modal-header:has(.local-dimensions-modal-sizetoggle) .modal-title`
(`styles.css:5313-5324` — the modal used to rely implicitly on a long title to push expand+close to
the right). Which one shows is chosen by the **CSS** (`styles.css:5255-5280`), **zero icon swapping
in JS**; the click toggles the class on the `.modal-dialog` (`:108`) and persists it (`:109`), and
the size follows from the class.

**Visually — the three controls share the close button's blue chip.** The combined base rule
`.local-dimensions-modal-sizetoggle, .local-dimensions-modal-refresh` (`styles.css:5229-5253`) gives
both the same `1.75rem`, `background-color:#e7f0f9` and `color:#0f4d85` as the restyled `.btn-close`
(`:5074-5103`); the hover for both (`:4947-4953`) is `#d4e6fb`; the dedicated focus ring
(`:4955-4959`) draws its own `:focus-visible` — neither carries `.btn`. The refresh's busy state has
its own rule `.local-dimensions-modal-refresh[disabled]` (`:4962-4964`).

**Expanded = full screen**, edge to edge, full height and square corners
(`.modal-dialog.local-dimensions-modal-expanded`, `styles.css:5288-5295`; the `.modal-content` zeroes
border and radius at `:4993-4997`). The width is **`width: 100%`, not `100vw`** — and the comment
(`:4982-4985`) says why: the dialog matches the `position:fixed` `.modal` that contains it, whose
width already excludes the scrollbar column, whereas `100vw` would include it and paint underneath
it. `large: true` (`js:862`, = `modal-lg`, 800px) and the expanded class do **not** stack:
`.modal-dialog.local-dimensions-modal-expanded` (0,2,0) **beats** `.modal-lg` (0,1,0), so expanded =
full screen, restored = `modal-lg`.

The precedent is `format_mtube`, **cited by symbol**:

- `getFullscreenButtonsHtml(expandLabel, narrowLabel)` emits **two always-present buttons** —
  `.enterfullscreen` (`fa fa-expand`) and `.exitfullscreen` (`fa fa-compress`), each with its own
  `aria-label` + `title`.
- `renderFullscreenButtons()` requests the `expand` / `narrow` strings and returns the markup.
- `setModalFullscreen(root, enabled, storageKey)` does **one** visual thing:
  `root.toggleClass('fullscreen', enabled)`. The `.each` above it only discards the tooltip of the
  button about to disappear — **it does not swap icons**.
- Which button shows is chosen by the **CSS**, with
  `.modal.mtube-modal-fullscreen-capable:not(.fullscreen) .exitfullscreen` and
  `.modal.mtube-modal-fullscreen-capable.fullscreen .enterfullscreen` — **zero icon swapping in JS**.
- The width comes from `.modal…fullscreen .modal-dialog`, not from an inline style.

**The divergence is persistence, and it is not cosmetic.** mtube writes to `localStorage`
(`STORAGE_PREFIX = 'format_mtube.modal.fullscreen.'`), which **never leaves the browser**. The hub
uses `amd/src/central/preferences.js`, which calls `setUserPreference` from `core_user/repository`
(`:28`) with a **400 ms debounce** (`SAVE_DELAY`, `:36`), and the module's docblock says why
(`:20-21`): *"across sessions and devices — replaces the previous per-session sessionStorage
persistence"*. The hub's prefs are two of the five declared in `local_dimensions_user_preferences()`
(`lib.php:143-157`), with `permissioncallback` = `\core_user::is_current_user` (`:148`), and the
names live in `constants.php:87` / `:90`.

And expand **needed no new pref**: it fits as the `modalexpanded` key inside the
`PREF_CENTRAL_DISPLAY` that already existed — it is in `DISPLAY_DEFAULTS` (`preferences.js:50`), in
`init` (`:97`) and, because the server **validates** the JSON and copies only known keys, also in
`helper::get_central_prefs()` (`helper.php:2380`), or the key would be discarded on reload. **No new
WS and no new setting string.** The state is seeded **synchronously** (`modal_expander.js:90`, before
the first `await`), so the modal opens already at the saved size; and the pref is **shared** —
expanding one modal expands the other on its next opening (a global size preference).

**Two decisions the adversarial sweep forced** (mtube has neither): the buttons do **not** use `.btn`
— a `.btn` with no variant has `--bs-btn-focus-shadow-rgb` undefined, so core's focus ring is invalid
and its `outline:0` erases the native one; here each draws its own ring on `:focus-visible`
(`styles.css:5255-5272`). And the click **hands focus back** to the newly revealed opposite button
(`modal_expander.js:110-118`), because the one that was activated hides itself in the CSS swap and
would drop focus on the `<body>`.
