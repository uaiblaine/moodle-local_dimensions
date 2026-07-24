# Field map — `OVW` · Full Plan Overview (`view-plan.php`)

"Full Plan Overview" mode: an accordion of every competency in a learning plan. Renderable
`view_plan_summary_page` → `view_plan_summary.mustache`. Each item's **expanded detail** is built
client-side by `accordion.js` from three plugin web services, called on first open:
`local_dimensions_get_user_competency_summary_in_plan` (Status / Description / Evidence) +
`local_dimensions_get_competency_courses` (linked courses) + `local_dimensions_get_competency_rule_data`
(Rules tab, lazy on first activation). The hero, filter bar and accordion shell are server-rendered.

## Hero, filter bar and accordion shell (server-rendered)
| ID | Label | Type | Origin | Data | Rule |
|---|---|---|---|---|---|
| `OVW-HERO-TITLE` | — | h1 | `hero_header.mustache:56` | template shortname, or plan name for individual plans | triple-stash (trusted HTML) |
| `OVW-HERO-DESC` | — | collapsible HTML | `hero_header.mustache:57-63` + `collapsible_description` | `format_text(template desc)`, or plan desc for individual plans | 30vh cap; toggle only when it overflows |
| `OVW-HERO-DUEDATE` | Due date | glass card | `hero_header.mustache:64-72` | `userdate(duedate, strftimedaydatetime)` | **only the plan carries a due date** |
| `OVW-HERO-BG` | — | inline style + CSS vars | `hero_header.mustache:51-54` | template `lp` custom fields: `custombgcolor`, `customtextcolor`, `custombgimage` | image → wrapper sets `--hero-bg-image` (+ `--hero-overlay-color` from bgcolor); no image → inline `background-color` from bgcolor, else `bg-primary`; `customtextcolor` sets inline `color` independently |
| `OVW-PCTMODE` | — | wrapper div | `view_plan_summary.mustache:70` | `percentagemode` | `percentagemode-{fixed\|hover\|hidden}` class (default hover); gates how ring % surfaces in the detail |
| `OVW-BAR-TABS` | Not completed / All | pill tabs | `view_plan_summary.mustache:77-86` | `incompletecount` / `competencycount` | starts on "Not completed" (`data-filter="incomplete"` active) |
| `OVW-BAR-SEARCH` | Search competency | input | `view_plan_summary.mustache:87-94` | — | accent-insensitive (`normalizeText`); 100ms debounce |
| `OVW-BAR-SEARCHCLEAR` | Clear (core) | X button | `view_plan_summary.mustache:95-97` | — | `local-dimensions-search-clear`, hidden until input has text; click clears + refocuses (`initSearch`) |
| `OVW-CHIP` | field name | pill group | `chip_filters.mustache:49-63` | `viewplan_filter_fields` (competency area) | one group per field; per-competency values in `filtervaluesjson` |
| `OVW-CHIP-CLEAR` | Clear filters | button + group label | `chip_filters.mustache:51,64-71` | `filter_chip_clear` | group label `local-dimensions-chip-group-label`; clear button hidden until a chip is selected (`chip_filters.js`) |
| `OVW-ACC-LIVE` | — | list region | `view_plan_summary.mustache:108-113` | — | `#local-dimensions-viewplan-accordion` `role="list"` `aria-live="polite"` `aria-relevant="additions removals"` |
| `OVW-ACC-ITEM` | — | card | `view_plan_summary.mustache:114-118` | `id`, `isproficient`, `hasrating`, `filtervaluesjson` | left border by state: `completed` (proficient) / `rated` (graded, not proficient) / pending |
| `OVW-ACC-TITLE` | — | span | `view_plan_summary.mustache:128` | `format_string(shortname)` | — |
| `OVW-ACC-SUBLINE` | — | badge/pill | `view_plan_summary.mustache:129-160` | subline source per template (`get_template_subline_source`) | status: rating badge (check/warning icon) or muted "To do" pill · rating: rating badge only, when graded · text: tag1/tag2 field value badge · none: no subline |
| `OVW-ACC-TOGGLE` | — | button | `view_plan_summary.mustache:122-126` | `aria-controls`, `aria-expanded` | loads detail by AJAX on first open; single-open (closes the others) |
| `OVW-ACC-LOADING` | Loading competency summary | spinner | `view_plan_summary.mustache:171-176` | — | per-item `spinner-border`; markup server-rendered, shown/hidden by `accordion.js` (`loadCompetencySummary`) |
| `OVW-ACC-ERROR` | Error loading summary | alert-danger | `view_plan_summary.mustache:180-182` | — | per-item `alert alert-danger`; markup server-rendered, revealed by `accordion.js` `.catch` |
| `OVW-EMPTY` | — | alert-info | `view_plan_summary.mustache:192-196` | — | plan with no competencies (`no_competencies_in_plan`) |
| `OVW-FILTER-NORESULTS` | — | (proposed) empty state | — | — | **absent in code today** — `applyFilter` only hides items via `display:none`, no "no results" message. to-be proposal |

## Expanded detail (client-side, `accordion.js`)
| ID | Label | Type | Origin | Data | Rule |
|---|---|---|---|---|---|
| `OVW-TAB-NAV` | Assessment / Description / Evidence / Rules | tab strip | `renderSummaryTabNavigation` (`buildSummaryTabs`) | — | tabs shown per available data + settings; `role="tablist"`; `tabs[0]` renders active. The linked-course section is **not** a tab — it renders after the whole wrapper |
| `OVW-TAB-KBD` | — | roving tabindex | `attachTabListeners` | — | ArrowLeft/Right/Up/Down + Home/End move active tab; active tab `tabindex=0`, rest `-1` (ARIA APG) |
| `OVW-STATUS` | Rating | headline + pill | `renderStatusSection` | `usercompetency.grade`/`gradename`, `proficiency` | the scale level as strong text, then a success/warning proficiency pill; with no grade the level reads `status_notyetrated` and the pill is dropped. No card, no label above it |
| `OVW-STATUS-SCALE` | About this scale | button → modal | `renderStatusSection` + `initScaleAbout` | `scaledescription`, injected onto the payload by the plugin's WS wrapper | rendered below the headline whenever the scale description is non-empty; **no setting gates it** |
| `OVW-DESC` | — | collapsible HTML | `renderDescriptionSection` | `comp.description` | gated by `showdescription` |
| `OVW-DESC-TOGGLE` | Show more / Show less | toggle button | `renderDescriptionSection` + `collapsible_description` | `show_more` / `show_less` | shared 30vh collapsible; toggle only when content overflows. Same mechanism wraps the hero desc server-side (`collapsible_description.mustache`, wired by `view-plan.php:101`) |
| `OVW-PATH` | Competency path | breadcrumb | `renderCompetencyPath` | framework › parents | gated by `showpath` |
| `OVW-REL` | Related dimensions | pills | `renderRelatedCompetencies` | `relatedcompetencies` | gated by `showrelated`; link (new tab, `rel=noopener`) when `showrelatedlink` |
| `OVW-TAX` | (taxonomy label) | aside card | `renderTaxonomyCard` / `getTaxonomyCardMeta` | `taxonomy.current.term` | gated by `showtaxonomycard`. **12 keys / 11 icons** (behavior aliases behaviour → same icon); the `taxonomy-card-<type>` accent classes it emits **do not exist in `styles.css`** — the accent is icon-driven only |
| `OVW-EVID-RULE` | Completed by competency rule | orange strip + "View rule" | `renderEvidenceList` / `isRuleCompletion` | the **last** evidence row whose `descidentifier` is the competency-rule one with the complete action | lifted above the list and omitted from it, so the same fact is not stated twice; followed by a stale-rating note (+ review request) when the rule fired but proficiency is still not 1 |
| `OVW-EVID-CARD` | evidence type | row in a flat list | `renderEvidenceRow` / `getEvidenceTypeInfo` | `ucs.evidence[]` | icon + description + type label + grade pill + date, in a flex row. 6 type styles (descidentifier → icon + colour class): activity / coursegrade / manual / file / prior / other. **The card slider is already gone** — this is the shipped list |
| `OVW-EVID-DETAIL` | View details | whole row | `renderEvidenceRow` | — | the row itself gets `role="button"` + `tabindex` only when note/URL/grade is present → opens the modal |
| `OVW-EVID-NAV` | prev / next | — | — | — | **no longer exists**: the slider and its arrow pill were removed with the list rewrite |
| `OVW-EVID-EMPTY` | — | muted text | `renderEvidenceList` | — | "no evidence" (`no_evidence`) when the evidence array is empty |
| `OVW-EVID-SUBMIT` | Submit evidence | button | `renderEvidencePane` | url `user_evidence_list.php?userid=` | `enableevidencesubmitbutton` + `moodle/competency:userevidencemanageown` (resolved in `view-plan.php`) |
| `OVW-RULES-INFO` | How it is completed | info box | `renderRuleInfoBox` | `outcometext` (+ optional `requiredwarningtext`) | `role="note"`; only when text present |
| `OVW-RULES-PROGRESS` | Progress | header + bar | `renderRulesSection` | `earnedpoints` / `totalrequired` | points (pts) or count; orange fill |
| `OVW-RULES-ARIA` | — | progressbar + sr-only | `renderRulesSection` / `renderRulesChild` | `rules_sr_progress`, `rules_sr_proficient/inprogress/todo` | track `role="progressbar"` with valuenow/min/max/label; each child icon carries an sr-only status label |
| `OVW-RULES-ALERT` | — | warning triangle + striped bar | `renderRulesSection` | `hasmissingmandatory` | when a mandatory child is unmet: triangle by the label, `progress-bar-striped` fill, alert notice (`rules_missing_mandatory_notice`) |
| `OVW-RULES-FILTER` | All / Required | pill tabs | `renderRulesFilterTabs` | `childcount`, `mandatorycount` | rendered only when `mandatorycount > 0` |
| `OVW-RULES-CHILD` | child name | list item | `renderRulesChild` | `children[]` | icon proficient / in-progress / to-do; required tag; assessment line; points (points rules); links to `view-competency.php` |
| `OVW-RULES-LOADING` | — | spinner | `renderRulesPane` | — | Rules-tab `fa-spinner` `role="status"` `aria-live="polite"`; lazy-loaded via `loadRulesTabIfNeeded` on first activation |
| `OVW-CRS` | Related content | scrollable cards, **outside the tab wrapper** | `renderCourseCardsScrollable` | `local_dimensions_get_competency_courses` (own SQL on `competency_coursecomp` JOIN `course`, `visible = 1`; **not** `list_courses_using_competency` — the WS docblock is stale) | section heading + count badge, then cards: image / initials placeholder, name, outcome badge, progress bar + `%`. **The whole card is the link** to the course — the per-card Access button is gone. Skipped entirely when no course survives the enrolment filter, leaving a blank below the pane |
| `OVW-CRS-OUTCOME` | Completes the competency / Sends for review / Attach evidence | badge | `renderOutcomeBadge` | the course link's `ruleoutcome` | nothing rendered for the "do nothing" default |
| `OVW-CRS-ACT` | N activities | disclosure + rows | `renderCourseCardsScrollable` / `renderActivityRow` + `initActivityDisclosures` | `activities[]` from the same WS (`competency_modulecomp` joined through modinfo) | plain `hidden` toggle, not a Bootstrap collapse; each row is icon + name + its own outcome badge + done/to-do/lock marker. **Course-level lock is deliberately not applied** to these rows |
| `OVW-CRS-NAV` | prev / next | scroll controls | `renderCourseCardsScrollable` + `initCourseScroll` | — | in the section header; arrows disabled at edges, shown only when the cards overflow; `fa-check-circle` when a course hits 100% |
| `OVW-MODAL` | Evidence details | modal | `openEvidenceDetailModal` → `evidence_detail_modal.mustache` | type, description, note, link, grade, author, date | `core/modal`; grade badge gains a proficient class when the scale value is proficient |

**Settings that affect this view:** `showdescription`, `showtaxonomycard`, `showpath`, `showrelated`,
`showrelatedlink`, `showevidence`, `enableevidencesubmitbutton`, `percentagedisplaymode`
(fixed / hover / hidden), `enablecustomscss`, the per-template subline source
(`get_template_subline_source`), and the chip-filter fields (`viewplan_filter_fields`). The
`enrollmentfilter` cascade (competency → plan's template → global) is resolved server-side inside
`local_dimensions_get_competency_courses`, so it shapes which linked courses appear.

## Planned — accordion refinement slice (not yet in code)
Proposed IDs, kept separate from the as-is inventory above. Screens:
`ovw-detail-tabs.html`, `ovw-detail-progress.html`, `ovw-detail-courses.html`,
`ovw-detail-evidence.html`, plus `../design-kit/tooltip.html`.

| ID | What | Touches |
|---|---|---|
| `OVW-TAB-ORDER` | Tab set becomes **Related content · Description · Progress · Rules**; the course scroller stops rendering outside the wrapper, Assessment and Evidence merge | `buildSummaryTabs`, `renderSummaryTabPanes`, `renderCompetencySummary`; one new string for the Progress label |
| `OVW-TAB-FIRST` | Which tab leads — courses when present, otherwise Description; falls out of the existing `tabs[0]` rule | — |
| `OVW-TAB-COUNT` | Card count moves from the section heading onto the Related-content tab | `renderSummaryTabNavigation` |
| `OVW-PROG-PANE` | `renderStatusPane` + `renderEvidencePane` → one `renderProgressPane`, gated `hasStatus \|\| hasEvidence` | `accordion.js` |
| `OVW-PROG-ORDER` | Pane order: assessment card → decisive strip → stale note → journey label + count → rows → submit | `accordion.js` |
| `OVW-STATUS-CARD` | The rating headline gains a card, an eyebrow label, and the scale button right-aligned in its header | `renderStatusSection` |
| `OVW-STATUS-SCALE` | Second gate: new `showscaledescription` checkbox (default on); also lets the WS skip reading the scale | `settings.php`, two lang strings, `get_user_competency_summary_in_plan` |
| `OVW-EVID-JOURNEY` | Row becomes a 4-column grid: `28px · minmax(0,1fr) · auto · 84px` | `renderEvidenceRow` |
| `OVW-EVID-DATE` | `strftimedatefullshort` (`12/01/26`), right-aligned, tabular-nums. Needs `%y` support and zero-padded `%d` in the client formatter | `formatTimestamp` |
| `OVW-EVID-TIP` | Grade chip capped + ellipsised, full value in a CSS-only tooltip; same pair on the rating level | `styles.css`; `design-kit/tooltip.html` |
| `OVW-EVID-COUNTS` | Journey count as a badge on the group label; the proposed `showevidencecounts` setting is dropped | `renderEvidenceList` |
| `OVW-CRS-PANE` | The scroller becomes the leading tab pane; heading dropped | `accordion.js` |
| `OVW-CRS-STATE` | Per-card access state (enrol / locked + date), artwork dimmed; lock rule = not actively enrolled and cannot self-enrol | `get_competency_courses` (new return keys), `calculator` helpers, `accordion.js` |
| `OVW-CRS-SINGLE` | Single trackable activity replaces the progress bar; activities drawer suppressed when it would repeat it | new lean `calculator` helper, `get_competency_courses`, `accordion.js` |
| `OVW-CRS-MORE` | "View detailed progress" link in the pane footer, right-aligned, only when `visibleCourses.length >= 3` → `view-competency.php?id={planid}&competencyid={competencyid}` | `accordion.js` (reuses `displaySettings.viewcompetencyurl`); one new lang string |

Dropped from earlier proposals: `OVW-CRS-TITLE` (label-driven section title — the tab
label replaces the heading) and `OVW-CRS-EMPTY` (empty-state line — no courses now
means no tab). The web-service return changes require a `version.php` bump, and
`execute_returns` is an allowlist, so every new key must be declared there.
