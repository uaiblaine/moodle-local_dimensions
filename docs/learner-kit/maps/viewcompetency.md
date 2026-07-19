# Field map — `TRK` · Competency Tracker (`view-competency.php`)

"Competency Tracker" mode: a grid of the courses linked to one competency of a plan.
Renderable `view_competency_page` → `view_competency.mustache`. Each card's progress body
loads over AJAX (`competency_view.js` → `get_course_progress` → `progress_card_body.mustache`),
preceded by a lightweight `get_courses_completion_status` batch that tags each card and orders
the loader. Visual replica: `screens/trk-tracker.html`, `screens/trk-card-states.html`,
`screens/trk-locked.html`, `screens/trk-empty.html` (+ shared `screens/hero.html`,
`screens/chips.html`).

| ID | Label | Type | Origin | Data | Rule |
|---|---|---|---|---|---|
| `TRK-HERO-TITLE` | — (title) | h1 | `hero_header.mustache:56` | `format_string(competency->shortname)` | triple-stash (trusted HTML) |
| `TRK-HERO-DESC` | — | collapsible HTML | `hero_header.mustache:57-63` + `collapsible_description` | `format_text(description)` | capped at 30vh; "Show more" toggle only if it overflows |
| `TRK-HERO-BG` | — | inline style | `hero_header.mustache:51-54` | custom fields `custombgcolor`/`customtextcolor`/`custombgimage` | else `bg-primary`; image → radial overlay |
| — | — | — | — | — | **competency has no due date** (only the plan does) |
| `TRK-CHIP-COMP` | field name | pill group | `chip_filters.mustache:49-62` | `viewcompetency_filter_fields_competency` | single page-level value, repeated onto every card |
| `TRK-CHIP-COURSE` | field name | pill group | `chip_filters.mustache:49-62` | `viewcompetency_filter_fields_course` | shortname prefixed `course:` (avoids key collision), remapped in `view_competency_page.php:187-190` |
| `TRK-CHIP-ACTIVE` | — | chip pressed state | `chip_filters.mustache:57` + `chip_filters.js:107-108` | `aria-pressed` | toggled on click; read back by `readSelection` (OR within a group, AND across groups) |
| `TRK-CHIP-NAV` | — | scroll paddles + indicator + arrow-key nav | `filter_tabs_nav.js` `wrapTabsContent` / `FilterTabsNav._onKeyDown:397` | — | wraps each chip group; left/right paddles hidden at edges; ArrowLeft/ArrowRight move focus; honours `prefers-reduced-motion` |
| `TRK-CHIP-CLEAR` | Clear filters | button | `chip_filters.mustache:64-71` | — | hidden until a selection exists (`chip_filters.js` `refreshClear`) |
| `TRK-GRID` | — | `row` region | `course_grid.mustache:41-46` | `courses[]` | `role=region`, `aria-live=polite` |
| `TRK-CARD` | — | `col-md-6 col-lg-4` | `course_card.mustache:41-43` | `courseid`, `filtervaluesjson` | `data-completed` set by `get_courses_completion_status`; drives the `__status` virtual filter key |
| `TRK-CARD-HEAD` | course name | link / muted span | `course_card.mustache:45-52` | `fullname`, `courseurl` | muted `<span>` when `locked`, `<a>` otherwise |
| `TRK-CARD-A11Y` | View course: {name} | aria-label | `course_card.mustache:50` | `viewcoursestr` (`view_course`) | on the course link only (unlocked cards) |
| `TRK-CARD-PROG` | — | AJAX container | `course_card.mustache:53-58` | `get_course_progress` | not-completed cards prioritised; 2s soft-timeout per card |
| `TRK-CARD-LOADING` | Loading progress… | spinner | `course_card.mustache:54-57` (initial) / `competency_view.js:177-181` (retry) | `loading_progress` / `course_loading` | every card's initial state; re-shown while a Retry re-fetches (`role=status`) |
| `TRK-CARD-NOCOMPLETION` | Completion disabled. | muted text | `progress_card_body.mustache:102-106` | `^enabled` | course has completion tracking switched off |
| `TRK-CARD-ERROR` | Could not load progress. + Retry | error + button | `competency_view.js:162` `renderErrorState` | `course_load_error`, `course_load_retry` | AJAX failed / empty payload / render failed; `role=alert`; Retry re-runs the soft-timeout fetch |
| `TRK-CARD-ERROR-INLINE` | — | alert-danger | `progress_card_body.mustache:173-175` | `{{#error}}` from the payload | server-side error string carried inside a rendered body |
| `TRK-TL-LOCK` | section | timeline marker | `progress_card_body.mustache:114-116` | section `locked` | per-section lock icon; **top** of the marker priority (locked > completed > started > empty/info) |
| `TRK-TL-DONE` | section | timeline item | `progress_card_body.mustache:119-121` | `is_completed` | check icon; green connector `#28a745` |
| `TRK-TL-RING` | section | SVG ring | `progress_card_body.mustache:124-134` | `percentage`, `dasharray` | started, not completed; percentage text shown per the `.percentagemode-{fixed\|hover\|hidden}` wrapper |
| `TRK-TL-RING-ARIA` | Section progress: {n}% | progressbar | `progress_card_body.mustache:125` | `role=progressbar`, `aria-valuenow` | ring container; label from `aria_completion_percentage` |
| `TRK-TL-CIRCLE` | section | neutral icon | `progress_card_body.mustache:138-140` | `has_activities` | has activities, not started |
| `TRK-TL-INFO` | section | info icon | `progress_card_body.mustache:141-143` | `^has_activities` | no completion tracking on the section |
| `TRK-TL-INFO-TIP` | No completion tracking | tooltip | `progress_card_body.mustache:142` | `title` attr (`no_completion_tracking`) | hover title on the info icon |
| `TRK-TL-SEC` | section name | link | `progress_card_body.mustache:159-162` | `name`, `url` | `aria-label` from `view_section` |
| `TRK-TL-SEC-SPAN` | section name | span | `progress_card_body.mustache:163-165` (also `154-156` locked) | `name` | fallback when the section has no URL |
| `TRK-LOCK` | — | overlay | `progress_card_body.mustache:70-100` | `locked` | blur; `calculator::is_locked`; overlay is re-parented onto `.card` by `competency_view.js:144-150` |
| `TRK-LOCK-ICON` | — | lock / FA icon | `progress_card_body.mustache:73-80` | `customicon` (setting `cardicon`) | fallback lock SVG when no custom icon |
| `TRK-LOCK-LEARNMORE` | Learn more | button | `progress_card_body.mustache:82-84` | `learnmorebuttoncolor` (default `#667eea`) | only in `learnmore` mode |
| `TRK-LOCK-MSG` | Locked content | text | `progress_card_body.mustache:85-87` | — | only in `blocked` mode |
| `TRK-LOCK-DATE` | Available on / Enrolment starts | text | `progress_card_body.mustache:89-98` | `formatted_start_date`, `is_enrolment_start` | only if `showlockeddate` |
| `TRK-LOCK-BORDER` | — | dashed SVG rect | `competency_view.js:149` (call) / `injectAnimatedBorder:314` | `stroke-dasharray '8 8'`; `ResizeObserver` | **dashed border is ALWAYS drawn on a locked card**; `animatelockedborder` gates only the marching-ants **animation** (`js:331-333` + `@keyframes local-dimensions-dashoffset-move`) |
| `TRK-EMPTY-NOCOURSES` | — | info alert | `view_competency.mustache:71-73` | — | competency with no visible courses |
| `TRK-EMPTY-NOCOMP` | — | warning alert | `view_competency.mustache:78-80` | — | invalid / missing `competencyid` |
| `TRK-FAB` | Return to Plan | floating button | **global** — `hook_callbacks::before_footer_html_generation:49` → `return_button.mustache` + `return_button.js` | stored return context | reference only (does not coin a new ID); own-plan owner, draggable, position in `sessionStorage`; page writes `noredirect=1` into its URL |

**Settings affecting this screen:** `percentagedisplaymode` (**fixed / hover (default) / hidden**),
`lockedcardmode` (blocked/learnmore), `showlockeddate`, `cardicon`, `learnmorebuttoncolor`,
`animatelockedborder`, `enablecustomscss`. Cascade `enrollmentfilter` + `singlecourseredirect`
(redirects when exactly one accessible course remains).
