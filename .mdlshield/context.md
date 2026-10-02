# Review context for local_dimensions

`local_dimensions` ("Competency Dimensions") extends core competencies and learning plan
templates with custom fields, adds learner-facing views (a competency tracker and a full plan
overview, plus a draggable return button), and provides an administrative hub for frameworks,
templates, cohort links and course and module links. It is a local plugin that supports
Moodle 4.5 through 5.2 on one branch. **It defines no database tables of its own**: all data
lives in core competency tables and `customfield_data`.

## Who is trusted

- Site administrators are fully trusted.
- The hub operations are gated by the **core competency capabilities**, not by plugin
  capabilities: `moodle/competency:competencyview`, `templateview`, `templatemanage`,
  `planmanage`, `coursecompetencymanage`, `coursecompetencyview`, `competencymanage`. Cohort
  role changes (`add_cohort_role`, `remove_cohort_role`, `list_template_cohort_roles`)
  require `moodle/role:manage` at system context. Treat a missing or weaker check on any
  write there as a privilege-escalation finding.
- `local/dimensions:view` (system context, default to manager, teacher and user, so every
  authenticated user) only opens the learner pages and their progress services. What a page
  may show about a plan or another user is decided by the core competency checks; a page that
  renders someone else's plan data without such a check is a finding.
- `local/dimensions:editcustomscss` is declared `RISK_XSS | RISK_CONFIG` and is system-scoped
  on both custom field handlers. Custom SCSS is compiled with `core_scss` and cached, so it is
  an injection surface: any path that lets someone without this capability write that custom
  field is a finding. Template import honours the same capability
  (`template_import_analyser::caneditscss`).
- Every authenticated user is untrusted input for names, descriptions and custom field values
  that reach a page.

## Surfaces

- 46 web service functions in `classes/external/`, one class per file, all `ajax`,
  session-based. Each does `validate_context()` and `require_capability()`; writes fire an
  event. `search_categories` registers no capability in `services.php`: it returns only
  categories the viewer can see (`helper::central_category_search` applies
  `moodle/category:viewhiddencategories` and `core_course_category::can_view_category`).
- Export and import of frameworks and templates (`export_framework`, `export_templates`,
  `preview_import_templates`, `apply_import_templates`).
- Adhoc tasks for cohort role and template cohort synchronisation, and one adhoc task per
  course, enrolment method and cohort for the enrolment actions queued from the hub.
- Page scripts: `central.php` (the hub, reachable from the admin tree or a course category's
  menu), `view-plan.php`, `view-competency.php`, and the custom field configuration pages.
- The privacy provider is preference-only; there is no table to export or delete.

## Facts that look like findings but are by design

- **Web service return structures are an allowlist.** `clean_returnvalue()` strips keys a
  structure does not declare, so a field added to a shared builder must be added to the
  returns of every function that channels it.
- **Names come in two spellings.** Values that land in a Mustache double stash, in `textContent`
  or in `PARAM_TEXT` return fields use the plain spelling
  (`format_string(..., ['escape' => false])`). Moodle form rows, labels and selects render raw
  and take the escaped spelling. A raw name in a triple stash or in a `PARAM_TEXT` return is a
  finding; a plain name in a double stash is correct.
- **`styles.css` ends with a Bootstrap 4 polyfill** gated by a body class that the plugin adds
  only below Moodle 5.0, and colours come from theme tokens with fallbacks. Neither is a
  security matter.
- **The plugin declares `$plugin->supported = [405, 502]` on one branch**, so code that
  branches on `$CFG->branch` or checks for a helper before calling it is deliberate.

## De-emphasise

- `amd/build/**` is minified output of `amd/src/**`; review the source.
- `docs/**` (design kits and specifications), `lang/**` and `tests/**` carry no production
  behaviour.
- Visual details of `styles.css` and the Mustache templates, unless they show data the viewer
  should not see.
