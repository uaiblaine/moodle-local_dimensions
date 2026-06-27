# Search learning plans by competency — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a competency autocomplete to the Competency hub Plans tab that filters the learning-plan list to templates containing the chosen competency.

**Architecture:** A new read web service `local_dimensions_search_competencies` powers a `core/form-autocomplete` field (via a small AMD datasource). Selecting a competency writes `competencyid` to the tab pane dataset and re-renders the tab via `core_dynamic_tabs_get_content`; `plans.php` intersects the context's templates with `api::list_templates_using_competency`.

**Tech Stack:** Moodle external API, `core/form-autocomplete`, `core/ajax`, `core_form`/dynamic tabs, PHPUnit, Behat.

**Spec:** `docs/2026-06-27-plans-search-by-competency-design.md`

> **Environment & verification:** This working copy has **no installed Moodle** (no `config.php`/DB), so PHPUnit/Behat/CLI cannot run here. Tests are written TDD-first and run in **CI**. Locally verifiable per step: `php -l <file>`, `npx eslint public/local/dimensions/amd/src`, `npx grunt amd --root=public/local/dimensions` (from the Moodle root `/Volumes/N1TB/dev/github/moodle`). Where a step says "run the test", that is the CI command — locally substitute `php -l`.
>
> **Commits:** the user commits only on request. Treat each "Commit" step as a logical checkpoint; actually run it only when the user approves.

---

## File structure

| File | Responsibility |
|---|---|
| `classes/external/search_competencies.php` (create) | Read WS: search competencies across readable frameworks, paginated. |
| `tests/external/search_competencies_test.php` (create) | PHPUnit for the WS. |
| `amd/src/central/competency_datasource.js` (create) | `core/form-autocomplete` ajax datasource calling the WS. |
| `classes/output/dynamictabs/plans.php` (modify) | Apply the `competencyid` filter; export filter state. |
| `templates/central/plans.mustache` (modify) | Autocomplete field, active-filter chip, filtered empty state. |
| `amd/src/central/plans.js` (modify) | Enhance the field; on change/clear set `competencyid` + reload. |
| `amd/src/central/context.js` (modify) | Reset `competencyid` on context switch. |
| `db/services.php` (modify) | Register the WS. |
| `version.php` (modify) | Bump (new WS installs on upgrade). |
| `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php` (modify) | New strings. |
| `tests/behat/search_plans_by_competency.feature` (create) | E2E regression (CI-only). |

---

## Task 1: `search_competencies` web service

**Files:**
- Create: `classes/external/search_competencies.php`
- Test: `tests/external/search_competencies_test.php`
- Modify: `db/services.php`, `version.php`

- [ ] **Step 1: Write the failing PHPUnit test**

Create `tests/external/search_competencies_test.php`:

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for the search_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests for the search_competencies external function.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_dimensions\external\search_competencies
 */
final class search_competencies_test extends \externallib_advanced_testcase {
    /**
     * Matches on shortname and idnumber, tagged with the framework.
     *
     * @return void
     */
    public function test_search_matches_shortname_and_idnumber(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW', 'idnumber' => 'FWID']);
        $fwid = (int) $framework->get('id');
        $alpha = $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Alpha skill', 'idnumber' => 'A-100']);
        $beta = $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => 'Beta skill', 'idnumber' => 'B-200']);

        $byname = search_competencies::execute('Alpha', 0, 25);
        $byname = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $byname);
        $this->assertSame(1, $byname['total']);
        $this->assertSame((int) $alpha->get('id'), $byname['items'][0]['id']);
        $this->assertSame('FWID', $byname['items'][0]['frameworktag']);

        $byidnumber = search_competencies::execute('B-200', 0, 25);
        $byidnumber = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $byidnumber);
        $this->assertSame(1, $byidnumber['total']);
        $this->assertSame((int) $beta->get('id'), $byidnumber['items'][0]['id']);
    }

    /**
     * A query shorter than the minimum returns nothing (avoids scanning everything).
     *
     * @return void
     */
    public function test_short_query_returns_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW']);
        $gen->create_competency(['competencyframeworkid' => (int) $framework->get('id'), 'shortname' => 'Alpha', 'idnumber' => 'A1']);

        $result = search_competencies::execute('A', 0, 25);
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['items']);
    }

    /**
     * Total reflects all matches while items honour the page size.
     *
     * @return void
     */
    public function test_pagination(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $gen->create_framework(['shortname' => 'FW']);
        $fwid = (int) $framework->get('id');
        for ($i = 0; $i < 5; $i++) {
            $gen->create_competency(['competencyframeworkid' => $fwid, 'shortname' => "Match $i", 'idnumber' => "M-$i"]);
        }

        $page = search_competencies::execute('Match', 0, 2);
        $page = \core_external\external_api::clean_returnvalue(search_competencies::execute_returns(), $page);
        $this->assertSame(5, $page['total']);
        $this->assertCount(2, $page['items']);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run (CI): `vendor/bin/phpunit local/dimensions/tests/external/search_competencies_test.php`
Expected: FAIL — class `local_dimensions\external\search_competencies` not found.
Local: `php -l public/local/dimensions/tests/external/search_competencies_test.php` (syntax only).

- [ ] **Step 3: Create the web service class**

Create `classes/external/search_competencies.php`:

```php
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Search competencies across readable frameworks for the Competency hub.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core\context\system as context_system;
use core_competency\competency_framework;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: paginated competency search (cross-framework, readable frameworks only).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_competencies extends external_api {
    /** @var int Minimum query length before a search runs. */
    const MIN_QUERY_LENGTH = 2;

    /** @var int Hard cap on the page size. */
    const MAX_LIMIT = 100;

    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW_TRIMMED, 'Search text (matches shortname or idnumber)'),
            'limitfrom' => new external_value(PARAM_INT, 'Offset for pagination', VALUE_DEFAULT, 0),
            'limitnum' => new external_value(PARAM_INT, 'Page size', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Search competencies in frameworks the user can read.
     *
     * @param string $query Search text.
     * @param int $limitfrom Offset.
     * @param int $limitnum Page size.
     * @return array Keys: items (list of {id, shortname, idnumber, frameworktag}), total (int).
     */
    public static function execute(string $query, int $limitfrom = 0, int $limitnum = 25): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query,
            'limitfrom' => $limitfrom,
            'limitnum' => $limitnum,
        ]);
        $query = $params['query'];
        $limitfrom = max(0, $params['limitfrom']);
        $limitnum = $params['limitnum'] > 0 ? min($params['limitnum'], self::MAX_LIMIT) : 25;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/competency:competencyview', $context);

        if (\core_text::strlen($query) < self::MIN_QUERY_LENGTH) {
            return ['items' => [], 'total' => 0];
        }

        // Readable frameworks → id => display tag (frameworks are few; filter by context readability).
        $tags = [];
        foreach (competency_framework::get_records([], 'shortname', 'ASC') as $framework) {
            if (competency_framework::can_read_context($framework->get_context())) {
                $idnumber = (string) $framework->get('idnumber');
                $tags[(int) $framework->get('id')] = $idnumber !== '' ? $idnumber : $framework->get('shortname');
            }
        }
        if (empty($tags)) {
            return ['items' => [], 'total' => 0];
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($tags), SQL_PARAMS_NAMED, 'fw');
        $like = $DB->sql_like('shortname', ':q1', false) . ' OR ' . $DB->sql_like('idnumber', ':q2', false);
        $likevalue = '%' . $DB->sql_like_escape($query) . '%';
        $selectparams = $inparams + ['q1' => $likevalue, 'q2' => $likevalue];
        $where = "competencyframeworkid $insql AND ($like)";

        $total = $DB->count_records_select('competency', $where, $selectparams);

        $items = [];
        $records = $DB->get_records_select('competency', $where, $selectparams, 'shortname ASC', '*', $limitfrom, $limitnum);
        foreach ($records as $record) {
            $items[] = [
                'id' => (int) $record->id,
                'shortname' => format_string($record->shortname),
                'idnumber' => s($record->idnumber),
                'frameworktag' => format_string($tags[(int) $record->competencyframeworkid] ?? ''),
            ];
        }

        return ['items' => $items, 'total' => (int) $total];
    }

    /**
     * Returns.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Competency id'),
                'shortname' => new external_value(PARAM_TEXT, 'Competency short name'),
                'idnumber' => new external_value(PARAM_RAW, 'Competency ID number'),
                'frameworktag' => new external_value(PARAM_TEXT, 'Origin framework tag'),
            ])),
            'total' => new external_value(PARAM_INT, 'Total matches across all pages'),
        ]);
    }
}
```

- [ ] **Step 4: Register the service in `db/services.php`**

Add this entry to the `$functions` array (match the surrounding entries' formatting):

```php
    'local_dimensions_search_competencies' => [
        'classname'    => 'local_dimensions\external\search_competencies',
        'methodname'   => 'execute',
        'description'  => 'Search competencies across readable frameworks for the Competency hub.',
        'type'         => 'read',
        'ajax'         => true,
        'capabilities' => 'moodle/competency:competencyview',
    ],
```

- [ ] **Step 5: Bump `version.php`** (services install on upgrade)

Change `$plugin->version = 2026062700;` to `$plugin->version = 2026062701;`.

- [ ] **Step 6: Verify**

Local: `php -l public/local/dimensions/classes/external/search_competencies.php` → "No syntax errors".
CI: `vendor/bin/phpunit local/dimensions/tests/external/search_competencies_test.php` → all pass.

- [ ] **Step 7: Commit** (on user approval)

```bash
git add classes/external/search_competencies.php tests/external/search_competencies_test.php db/services.php version.php
git commit -m "feat: add search_competencies web service for the Competency hub

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Filter the Plans tab by competency (`plans.php`)

**Files:**
- Modify: `classes/output/dynamictabs/plans.php`

- [ ] **Step 1: Add the `competency` import**

In the `use` block add:

```php
use core_competency\competency;
```

- [ ] **Step 2: Apply the filter after the context template list is built**

In `export_for_template()`, immediately **after** the `foreach (api::list_templates(...))` loop that fills `$templates` and **before** the `if ($templateid <= 0 || !isset($templates[$templateid]))` line, insert:

```php
        // Optional filter: only templates that contain the chosen competency (cross-framework).
        $competencyid = (int) ($data['competencyid'] ?? 0);
        $selectedcompetencylabel = '';
        if ($competencyid > 0) {
            $competency = competency::get_record(['id' => $competencyid]);
            $framework = $competency ? competency_framework::get_record(['id' => $competency->get('competencyframeworkid')]) : null;
            if ($competency && $framework && competency_framework::can_read_context($framework->get_context())) {
                $usingids = [];
                foreach (api::list_templates_using_competency($competencyid) as $usingtemplate) {
                    $usingids[(int) $usingtemplate->get('id')] = true;
                }
                foreach (array_keys($templates) as $id) {
                    if (!isset($usingids[$id])) {
                        unset($templates[$id]);
                    }
                }
                $tag = $framework->get('idnumber') !== '' ? $framework->get('idnumber') : $framework->get('shortname');
                $name = format_string($competency->get('shortname'));
                $tag = format_string($tag);
                $selectedcompetencylabel = $tag !== '' ? "$name · $tag" : $name;
            } else {
                // Unknown or unreadable competency: ignore the filter.
                $competencyid = 0;
            }
        }
```

- [ ] **Step 3: Export the filter state**

In the `return [ ... ];` array add these keys (next to `selectedcategoryid`):

```php
            'competencyid' => $competencyid,
            'filteredbycompetency' => $competencyid > 0,
            'selectedcompetencyid' => $competencyid,
            'selectedcompetencylabel' => $selectedcompetencylabel,
```

- [ ] **Step 4: Verify**

Local: `php -l public/local/dimensions/classes/output/dynamictabs/plans.php`.
Check line length ≤ 132: `awk 'length>132{print NR": "length}' public/local/dimensions/classes/output/dynamictabs/plans.php` → no output.

- [ ] **Step 5: Commit** (on user approval)

```bash
git add classes/output/dynamictabs/plans.php
git commit -m "feat: filter Competency hub plans by competency

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Autocomplete datasource (`competency_datasource.js`)

**Files:**
- Create: `amd/src/central/competency_datasource.js`

- [ ] **Step 1: Create the datasource**

```javascript
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * core/form-autocomplete datasource for the Competency hub competency search.
 *
 * @module     local_dimensions/central/competency_datasource
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Fetch competencies matching the query from the server.
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {String} query The user's search text.
 * @param {Function} success Callback receiving the raw result items.
 * @param {Function} failure Callback receiving an error.
 */
export const transport = (selector, query, success, failure) => {
    Ajax.call([{
        methodname: 'local_dimensions_search_competencies',
        args: {query: query, limitfrom: 0, limitnum: 25},
    }])[0].then((response) => success(response.items)).catch(failure);
};

/**
 * Map the raw items to autocomplete {value, label} pairs (label carries the framework tag).
 *
 * @param {String} selector Unused (autocomplete contract).
 * @param {Array} results Raw items from transport().
 * @return {Array}
 */
export const processResults = (selector, results) => results.map((competency) => ({
    value: competency.id,
    label: competency.frameworktag ? `${competency.shortname} · ${competency.frameworktag}` : competency.shortname,
}));
```

- [ ] **Step 2: Lint**

Run (from Moodle root): `npx eslint public/local/dimensions/amd/src/central/competency_datasource.js`
Expected: exit 0, no output.

(Build happens in Task 7 with the other JS changes.)

---

## Task 4: Plans template — search field, chip, empty state (`plans.mustache`)

**Files:**
- Modify: `templates/central/plans.mustache`

- [ ] **Step 1: Add the new context vars to the docblock and Example context**

In the `Context variables required` list add:

```
    * competencyid (int) · filteredbycompetency (bool)
    * selectedcompetencyid (int) · selectedcompetencylabel (string)
```

In the `Example context (json)` object add (after `"needscategoryselection": false,`):

```
        "competencyid": 7,
        "filteredbycompetency": true,
        "selectedcompetencyid": 7,
        "selectedcompetencylabel": "Communication · CORE",
```

- [ ] **Step 2: Add the search field + active-filter chip above the list**

Replace the opening of the `{{^needscategoryselection}}` block. Find:

```
    {{^needscategoryselection}}
    {{#hastemplates}}
```

Replace with:

```
    {{^needscategoryselection}}
    <div class="d-flex flex-wrap align-items-end gap-3 mb-3" data-region="plan-search">
        <div class="flex-grow-1">
            <label class="d-block small text-muted mb-1" for="local-dimensions-central-competency-search">
                {{#str}}central_searchcompetency, local_dimensions{{/str}}
            </label>
            <select id="local-dimensions-central-competency-search" data-region="competency-search" class="form-control">
                {{#filteredbycompetency}}
                <option value="{{selectedcompetencyid}}" selected>{{selectedcompetencylabel}}</option>
                {{/filteredbycompetency}}
            </select>
        </div>
        {{#filteredbycompetency}}
        <div class="align-self-center">
            <span class="badge bg-info text-dark">{{#str}}central_filteredbycompetency, local_dimensions, {{selectedcompetencylabel}}{{/str}}</span>
            <button type="button" class="btn btn-sm btn-link" data-action="clear-competency">
                {{#str}}central_clearcompetencyfilter, local_dimensions{{/str}}
            </button>
        </div>
        {{/filteredbycompetency}}
    </div>
    {{#hastemplates}}
```

- [ ] **Step 3: Make the empty state filter-aware**

Find:

```
    {{^hastemplates}}
    <div class="alert alert-info" role="status">{{#str}}noplans, local_dimensions{{/str}}</div>
    {{/hastemplates}}
    {{/needscategoryselection}}
```

Replace with:

```
    {{^hastemplates}}
    {{#filteredbycompetency}}
    <div class="alert alert-warning" role="status">{{#str}}central_noplanswithcompetency, local_dimensions{{/str}}</div>
    {{/filteredbycompetency}}
    {{^filteredbycompetency}}
    <div class="alert alert-info" role="status">{{#str}}noplans, local_dimensions{{/str}}</div>
    {{/filteredbycompetency}}
    {{/hastemplates}}
    {{/needscategoryselection}}
```

- [ ] **Step 4: Verify JSON**

Run: `python3 -c "import re,json; t=open('public/local/dimensions/templates/central/plans.mustache').read(); m=re.search(r'Example context \(json\):\s*(\{.*?\})\s*\}\}', t, re.S); json.loads(m.group(1)); print('JSON OK')"`
Expected: `JSON OK`.

---

## Task 5: Plans JS — enhance field, change/clear handlers (`plans.js`)

**Files:**
- Modify: `amd/src/central/plans.js`

- [ ] **Step 1: Replace the module body**

Replace the whole file body below the license header with:

```javascript
/**
 * Learning plans tab: select a template to load its competencies, and filter the plan list
 * by competency via a core/form-autocomplete field (no page reload). The System / Course
 * category context arrives via the pane dataset (set by local_dimensions/central/context).
 *
 * @module     local_dimensions/central/plans
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {reloadPane} from 'local_dimensions/central/tabs';

const DATASOURCE = 'local_dimensions/central/competency_datasource';

const SELECTORS = {
    region: '[data-region="plans"]',
    selectTemplate: '[data-action="select-template"]',
    competencySearch: '[data-region="competency-search"]',
    clearCompetency: '[data-action="clear-competency"]',
};

/**
 * Initialise the Learning plans tab. Re-runs after each tab refresh.
 */
export const init = () => {
    const region = document.querySelector(SELECTORS.region);
    if (!region) {
        return;
    }
    const pane = region.closest('[data-tab-content]');

    const search = region.querySelector(SELECTORS.competencySearch);
    if (search && pane && !search.dataset.enhanced) {
        search.dataset.enhanced = '1';
        search.addEventListener('change', () => {
            pane.dataset.competencyid = search.value || 0;
            reloadPane(pane).catch(Notification.exception);
        });
        getString('central_searchcompetency', 'local_dimensions')
            .then((placeholder) => enhance(SELECTORS.competencySearch, false, DATASOURCE, placeholder, false, true, '', true))
            .catch(Notification.exception);
    }

    region.addEventListener('click', (event) => {
        const item = event.target.closest(SELECTORS.selectTemplate);
        if (item && pane) {
            pane.dataset.templateid = item.dataset.id;
            reloadPane(pane).catch(Notification.exception);
            return;
        }
        if (event.target.closest(SELECTORS.clearCompetency) && pane) {
            pane.dataset.competencyid = 0;
            reloadPane(pane).catch(Notification.exception);
        }
    });
};
```

- [ ] **Step 2: Lint**

Run: `npx eslint public/local/dimensions/amd/src/central/plans.js` → exit 0.

---

## Task 6: Reset the filter on context switch (`context.js`)

**Files:**
- Modify: `amd/src/central/context.js`

- [ ] **Step 1: Reset `competencyid` in `applyContextToPanes`**

Find:

```javascript
        if ('templateid' in pane.dataset) {
            pane.dataset.templateid = 0;
        }
```

Replace with:

```javascript
        if ('templateid' in pane.dataset) {
            pane.dataset.templateid = 0;
        }
        if ('competencyid' in pane.dataset) {
            pane.dataset.competencyid = 0;
        }
```

- [ ] **Step 2: Lint**

Run: `npx eslint public/local/dimensions/amd/src/central/context.js` → exit 0.

---

## Task 7: Build AMD + lang strings

**Files:**
- Modify: `lang/en/local_dimensions.php`, `lang/pt_br/local_dimensions.php`
- Build: `amd/build/central/*`

- [ ] **Step 1: Add EN strings (alphabetical)**

In `lang/en/local_dimensions.php`, insert so the `central_*` block reads in this order — `central_clearcompetencyfilter` and `central_filteredbycompetency` go **before** `central_frameworks`; `central_noplanswithcompetency` goes **between** `central_frameworks` and `central_plans`; `central_searchcompetency` goes **after** `central_plans`:

```php
$string['central_clearcompetencyfilter'] = 'Clear competency filter';
$string['central_filteredbycompetency'] = 'Plans containing: {$a}';
```
```php
$string['central_noplanswithcompetency'] = 'No learning plans contain this competency.';
```
```php
$string['central_searchcompetency'] = 'Filter plans by competency';
```

- [ ] **Step 2: Add pt_br strings (same positions)**

```php
$string['central_clearcompetencyfilter'] = 'Limpar filtro de competência';
$string['central_filteredbycompetency'] = 'Planos que contêm: {$a}';
```
```php
$string['central_noplanswithcompetency'] = 'Nenhum plano de aprendizagem contém esta competência.';
```
```php
$string['central_searchcompetency'] = 'Filtrar planos por competência';
```

- [ ] **Step 3: Verify lang ordering + sync**

Run:
```bash
for f in en pt_br; do
  grep -oE "^\\\$string\['[^']+'\]" public/local/dimensions/lang/$f/local_dimensions.php \
    | sed -E "s/.*\['([^']+)'\]/\1/" | awk 'NR>1 && $0<p{print FILENAME" disorder: "$0" after "p} {p=$0}' FILENAME=$f
done
echo "en/pt key counts:"; grep -cE "^\\\$string" public/local/dimensions/lang/en/local_dimensions.php; grep -cE "^\\\$string" public/local/dimensions/lang/pt_br/local_dimensions.php
```
Expected: no "disorder" lines; equal counts.

- [ ] **Step 4: Rebuild AMD**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
```
Expected: "Done." and updated `amd/build/central/competency_datasource.min.js`, `plans.min.js`, `context.min.js` (+ `.map`).

- [ ] **Step 5: Verify build artifacts**

```bash
for m in competency_datasource plans context; do grep -q "local_dimensions/central/$m" public/local/dimensions/amd/build/central/$m.min.js && echo "OK $m" || echo "MISSING $m"; done
```
Expected: `OK` for all three.

- [ ] **Step 6: Commit** (on user approval)

```bash
git add amd/src/central/competency_datasource.js amd/src/central/plans.js amd/src/central/context.js amd/build/central templates/central/plans.mustache lang/en/local_dimensions.php lang/pt_br/local_dimensions.php
git commit -m "feat: competency autocomplete filter on the Competency hub Plans tab

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Behat regression (CI-only)

**Files:**
- Create: `tests/behat/search_plans_by_competency.feature`

> Cannot run locally (no installed Moodle). Uses the core_competency generators and the
> built-in autocomplete steps. If a step needs adjusting it will surface on the first CI run.

- [ ] **Step 1: Create the feature**

```gherkin
@local @local_dimensions @javascript
Feature: Filter learning plans by competency in the Competency hub
  In order to find which plans use a competency
  As an administrator
  I need to search a competency and see only the plans that contain it

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And the following "core_competency > frameworks" exist:
      | shortname       | idnumber |
      | Behat framework | BF1      |
    And the following "core_competency > competencies" exist:
      | shortname        | idnumber | competencyframework |
      | Alpha competency | AC1      | BF1                 |
    And the following "core_competency > templates" exist:
      | shortname    |
      | Plan with    |
      | Plan without |
    And the following "core_competency > template_competencies" exist:
      | template  | competency |
      | Plan with | AC1        |
    And I log in as "admin"

  Scenario: Searching a competency filters the plan list
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I should see "Plan without"
    And I open the autocomplete suggestions list
    And I set the field "Filter plans by competency" to "Alpha competency"
    And I click on "Alpha competency · BF1" item in the autocomplete list
    Then I should see "Plan with"
    And I should not see "Plan without"
    And I click on "Clear competency filter" "button"
    And I should see "Plan without"
```

- [ ] **Step 2: Verify (CI)**

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config <behat_dataroot>/behat/behat.yml --tags @local_dimensions
```

- [ ] **Step 3: Commit** (on user approval)

```bash
git add tests/behat/search_plans_by_competency.feature
git commit -m "test: Behat for plan filtering by competency

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Update status doc

**Files:**
- Modify: `docs/implementation-status.md`

- [ ] **Step 1:** Move "Plans: search / filter by competency" out of "Next slices" into "Done" (note the WS `search_competencies`, the `core/form-autocomplete` field, and that `search_competencies` is reused by the future cross-framework picker). Renumber the remaining slices (CRUD becomes #1).

- [ ] **Step 2: Commit** (on user approval)

```bash
git add docs/implementation-status.md docs/2026-06-27-plans-search-by-competency-*.md
git commit -m "docs: mark plan-search-by-competency done; add spec + plan

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```
