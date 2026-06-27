# Learning plan (template) CRUD — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add create / edit / delete of learning plan templates to the Competency hub Plans tab, in a modal, with no page reload.

**Architecture:** A new `\core_form\dynamic_form` (`template_dynamic_form`) opened by `core_form/modalform` mirrors the shipped `competency_dynamic_form` and reuses `template_form.php`'s field set. Create/update run server-side via `core_competency\api` + `lp_handler`; delete uses core AJAX WS `core_competency_template_has_related_data` + `core_competency_delete_template` with a radio dialog (delete vs unlink plans).

**Tech Stack:** Moodle dynamic forms, `core_form/modalform`, `core/modal_save_cancel`, `core/ajax`, `lp_handler` (core_customfield), PHPUnit/Behat.

**Spec:** `docs/2026-06-27-plans-crud-design.md`

> **Environment & verification:** This working copy has **no installed Moodle** (no DB) — PHPUnit/Behat/CLI cannot run here; they run in **CI**. Locally verifiable: `php -l <file>`, `npx eslint public/local/dimensions/amd/src`, `npx grunt amd --root=public/local/dimensions` (from `/Volumes/N1TB/dev/github/moodle`). Where a step says "run the test", that is the CI command.
>
> **Commits:** the user commits only on request. Treat "Commit" steps as checkpoints; run them only when the user approves.

---

## File structure

| File | Responsibility |
|---|---|
| `classes/form/template_dynamic_form.php` (create) | Modal create/edit form for templates. |
| `classes/output/dynamictabs/plans.php` (modify) | Export `contextid` for the New action. |
| `templates/central/plans.mustache` (modify) | New/Edit/Delete buttons + `data-contextid`. |
| `templates/central/delete_template_plans.mustache` (create) | Radio body for the "has plans" delete dialog. |
| `amd/src/central/plans.js` (modify) | Open the form modal; delete flow. |
| `version.php` (modify) | Bump (AMD change). |
| `tests/behat/manage_plans.feature` (create) | CRUD E2E (CI-only). |
| `docs/implementation-status.md` (modify) | Mark slice done. |

No new web service, no new lang string (reuse `tool_lp` + `managetemplates_addtemplate`).

---

## Task 1: `template_dynamic_form`

**Files:**
- Create: `classes/form/template_dynamic_form.php`

- [ ] **Step 1: Create the form class**

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
 * Modal (dynamic) form to create or edit a learning plan template — for the Competency hub.
 *
 * Mirrors template_form / edit_template.php, but runs inside a modal (core_form/modalform)
 * with no page reload. Custom (lp) fields are rendered and saved by lp_handler.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\form;

use core_competency\api;
use core_competency\template;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;

/**
 * Create/edit a learning plan template in a modal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_dynamic_form extends \core_form\dynamic_form {
    /**
     * @return int Template id (0 when creating).
     */
    private function get_templateid(): int {
        return $this->optional_param('id', 0, PARAM_INT);
    }

    /**
     * @return int Context id from the request (used on the create flow).
     */
    private function get_contextid(): int {
        return $this->optional_param('contextid', 0, PARAM_INT);
    }

    /**
     * Submission context: the template's own context when editing, else the requested context.
     *
     * @return \context
     */
    protected function get_context_for_dynamic_submission(): \context {
        $id = $this->get_templateid();
        if ($id > 0 && ($template = template::get_record(['id' => $id]))) {
            return $template->get_context();
        }
        $contextid = $this->get_contextid();
        if ($contextid > 0) {
            try {
                return \context::instance_by_id($contextid);
            } catch (\moodle_exception $e) {
                return \context_system::instance();
            }
        }
        return \context_system::instance();
    }

    /**
     * Only template managers may submit.
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/competency:templatemanage', $this->get_context_for_dynamic_submission());
    }

    /**
     * @return \moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/local/dimensions/central.php');
    }

    /**
     * Form fields: basic info, publication, and the plugin (lp) custom fields.
     */
    public function definition() {
        $mform = $this->_form;
        $context = $this->get_context_for_dynamic_submission();

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $context->id);

        $mform->addElement('text', 'shortname', get_string('shortname', 'tool_lp'), ['maxlength' => 100]);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');
        $mform->addRule('shortname', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('editor', 'description', get_string('description', 'tool_lp'), ['rows' => 4]);
        $mform->setType('description', PARAM_CLEANHTML);

        $mform->addElement('selectyesno', 'visible', get_string('visible', 'tool_lp'));
        $mform->setDefault('visible', 1);
        $mform->addHelpButton('visible', 'visible', 'tool_lp');

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'tool_lp'), ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'tool_lp');

        // Plugin custom fields (renders the "Custom fields" header + the lp area fields).
        lp_handler::create()->instance_form_definition($mform, $this->get_templateid());
    }

    /**
     * Load existing values (and custom field data) when editing.
     */
    public function set_data_for_dynamic_submission(): void {
        $id = $this->get_templateid();

        $data = (object) [
            'id' => $id,
            'contextid' => $this->get_contextid(),
        ];

        if ($id > 0 && ($template = template::get_record(['id' => $id]))) {
            $data->contextid = $template->get('contextid');
            $data->shortname = $template->get('shortname');
            $data->visible = $template->get('visible');
            $data->duedate = (int) $template->get('duedate');
            $data->description = [
                'text' => $template->get('description'),
                'format' => $template->get('descriptionformat'),
            ];
            lp_handler::create()->instance_form_before_set_data_with_image($data);
        }

        $this->set_data($data);
    }

    /**
     * Submitted data: force the custom SCSS field to plain format and run the handler's
     * after-data hook (mirrors template_form).
     *
     * @return object|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data) {
            $editorprop = 'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor';
            $plainprop = 'customfield_' . constants::CFIELD_CUSTOMSCSS;
            if (isset($data->$editorprop) && is_array($data->$editorprop)) {
                $data->{$editorprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$editorprop) && is_object($data->$editorprop)) {
                $data->$editorprop->format = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_array($data->$plainprop)) {
                $data->{$plainprop}['format'] = FORMAT_PLAIN;
            } else if (isset($data->$plainprop) && is_object($data->$plainprop)) {
                $data->$plainprop->format = FORMAT_PLAIN;
            }
            lp_handler::create()->instance_form_definition_after_data($this->_form, $data->id ?? 0);
        }
        return $data;
    }

    /**
     * Create or update the template and persist its custom fields.
     *
     * @return array{templateid: int}
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $id = (int) ($data->id ?? 0);

        $record = new \stdClass();
        $record->shortname = $data->shortname;
        $record->description = $data->description['text'] ?? '';
        $record->descriptionformat = $data->description['format'] ?? FORMAT_HTML;
        $record->visible = (int) ($data->visible ?? 1);
        $record->duedate = (int) ($data->duedate ?? 0);
        $record->contextid = (int) $data->contextid;

        if ($id > 0) {
            $record->id = $id;
            api::update_template($record);
            $templateid = $id;
        } else {
            $templateid = (int) api::create_template($record)->get('id');
        }

        $data->id = $templateid;
        lp_handler::create()->instance_form_save_with_image($data, $templateid);

        \local_dimensions\template_metadata_cache::invalidate_template($templateid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            \local_dimensions\scss_manager::invalidate_cache($templateid, 'lp');
        }

        return ['templateid' => $templateid];
    }

    /**
     * Validate shortname uniqueness within the context and the custom SCSS.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $shortname = $data['shortname'] ?? '';
        if (!empty($shortname)) {
            $existing = template::get_record([
                'shortname' => $shortname,
                'contextid' => $data['contextid'],
            ]);
            if ($existing && (int) $existing->get('id') !== (int) ($data['id'] ?? 0)) {
                $errors['shortname'] = get_string('shortnametaken', 'tool_lp');
            }
        }

        if (get_config('local_dimensions', 'enablecustomscss')) {
            [$scssvalue, $errorfield] = self::extract_submitted_scss($data);
            if (trim($scssvalue) !== '') {
                $result = \local_dimensions\scss_manager::validate_scss($scssvalue);
                if ($result !== true) {
                    $errors[$errorfield] = $result;
                }
            }
        }

        return $errors;
    }

    /**
     * Extract submitted custom SCSS from the possible field structures.
     *
     * @param array $data Form data.
     * @return array Two-element list: the SCSS value and the field name for error mapping.
     */
    protected static function extract_submitted_scss(array $data): array {
        $fieldcandidates = [
            'customfield_' . constants::CFIELD_CUSTOMSCSS . '_editor',
            'customfield_' . constants::CFIELD_CUSTOMSCSS,
        ];

        foreach ($fieldcandidates as $fieldname) {
            if (!array_key_exists($fieldname, $data)) {
                continue;
            }
            $value = $data[$fieldname];
            if (is_array($value)) {
                if (array_key_exists('text', $value)) {
                    return [(string) $value['text'], $fieldname];
                }
                if (array_key_exists('value', $value)) {
                    return [(string) $value['value'], $fieldname];
                }
                return ['', $fieldname];
            }
            if (is_object($value)) {
                if (property_exists($value, 'text')) {
                    return [(string) $value->text, $fieldname];
                }
                if (property_exists($value, 'value')) {
                    return [(string) $value->value, $fieldname];
                }
                return ['', $fieldname];
            }
            if (is_string($value)) {
                return [$value, $fieldname];
            }
            if (is_scalar($value)) {
                return [(string) $value, $fieldname];
            }
            return ['', $fieldname];
        }

        return ['', $fieldcandidates[0]];
    }
}
```

- [ ] **Step 2: Verify**

Run: `php -l public/local/dimensions/classes/form/template_dynamic_form.php` → "No syntax errors".
Run: `awk 'length>132{print NR": "length}' public/local/dimensions/classes/form/template_dynamic_form.php` → no output.

> Note: this checkout has no DB, so the form cannot be exercised here; the Behat in Task 5 covers it in CI.

- [ ] **Step 3: Commit** (on user approval)

```bash
git add classes/form/template_dynamic_form.php
git commit -m "feat: template_dynamic_form for Competency hub plan CRUD

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Export `contextid` from the Plans tab

**Files:**
- Modify: `classes/output/dynamictabs/plans.php`

- [ ] **Step 1: Add the export key**

In `export_for_template()`, in the `return [ ... ];` array, add `contextid` next to `selectedcategoryid`:

```php
            'contextid' => (int) $context->id,
```

(`$context` is the resolved context already computed earlier in the method.)

- [ ] **Step 2: Verify**

Run: `php -l public/local/dimensions/classes/output/dynamictabs/plans.php` → clean.

- [ ] **Step 3: Commit** (on user approval)

```bash
git add classes/output/dynamictabs/plans.php
git commit -m "feat: expose resolved context id on the Plans tab

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Plans template — CRUD buttons + delete dialog body

**Files:**
- Modify: `templates/central/plans.mustache`
- Create: `templates/central/delete_template_plans.mustache`

- [ ] **Step 1: Add `contextid` to the docblock + Example context**

In `plans.mustache`, add to the Context-variables list: `* contextid (int)`. In `Example context (json)` add after `"selectedcategoryid": 0,`:

```
        "contextid": 1,
```

- [ ] **Step 2: Add the "New plan" button to the search row**

Find the `data-region="plan-search"` opening div. Immediately after its closing competency-search `<div class="flex-grow-1">…</div>` block (i.e., inside the `plan-search` flex container, after the filter/chip), add:

```
        {{#canmanage}}
        <div class="ms-auto align-self-center">
            <button type="button" class="btn btn-primary btn-sm" data-action="new-template">
                <i class="fa fa-plus me-1" aria-hidden="true"></i>{{#str}}managetemplates_addtemplate, local_dimensions{{/str}}
            </button>
        </div>
        {{/canmanage}}
```

- [ ] **Step 3: Add Edit/Delete to the detail pane**

In the `data-region="plan-detail"` card body, after the competencies list/empty-state block and before `</div></div></aside>`, add:

```
                    {{#canmanage}}
                    <div class="mt-3 d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" data-action="edit-template" data-id="{{selectedtemplateid}}">
                            <i class="fa fa-pencil me-1" aria-hidden="true"></i>{{#str}}edit{{/str}}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-action="delete-template"
                                data-id="{{selectedtemplateid}}" data-name="{{selectedtemplatename}}">
                            <i class="fa fa-trash me-1" aria-hidden="true"></i>{{#str}}delete{{/str}}
                        </button>
                    </div>
                    {{/canmanage}}
```

- [ ] **Step 4: Add `data-contextid` to the root region**

Change the root `<div class="local-dimensions-central-plans" data-region="plans" data-contexttype="{{contexttype}}" data-categoryid="{{selectedcategoryid}}">` to also carry:

```
     data-contextid="{{contextid}}"
```

- [ ] **Step 5: Create `delete_template_plans.mustache`**

```
{{!
    This file is part of Moodle - http://moodle.org/

    Moodle is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Moodle is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
}}
{{!
    @template local_dimensions/central/delete_template_plans

    Body of the "this template has learning plans" delete dialogue: a message plus two radio
    options — delete the plans, or unlink them (default). The selected radio's value (1/0) maps to
    the deleteplans argument of core_competency_delete_template.

    Example context (json):
    {
    }
}}
<div data-region="delete-template-plans">
    <p>{{#str}}deletetemplatewithplans, tool_lp{{/str}}</p>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="deleteplans" id="local-dimensions-deleteplans-unlink" value="0" checked>
        <label class="form-check-label" for="local-dimensions-deleteplans-unlink">
            {{#str}}unlinkplanstemplate, tool_lp{{/str}}
        </label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="deleteplans" id="local-dimensions-deleteplans-delete" value="1">
        <label class="form-check-label" for="local-dimensions-deleteplans-delete">
            {{#str}}deleteplans, tool_lp{{/str}}
        </label>
    </div>
</div>
```

- [ ] **Step 6: Verify mustache JSON**

Run: `python3 -c "import re,json,glob; [json.loads(re.search(r'Example context \(json\):\s*(\{.*?\})\s*\}\}', open(f).read(), re.S).group(1)) for f in ['public/local/dimensions/templates/central/plans.mustache','public/local/dimensions/templates/central/delete_template_plans.mustache']]; print('JSON OK')"`
Expected: `JSON OK`.

---

## Task 4: Plans JS — open the modal, delete flow + AMD build

**Files:**
- Modify: `amd/src/central/plans.js`, `version.php`
- Build: `amd/build/central/plans.*`

- [ ] **Step 1: Replace the module body** (extends the existing one with CRUD)

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
 * Learning plans tab: select a template, filter by competency, and create/edit/delete templates
 * in a modal (no page reload). Context arrives via the pane dataset (set by central/context).
 *
 * @module     local_dimensions/central/plans
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import ModalSaveCancel from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {enhance} from 'core/form-autocomplete';
import {getString} from 'core/str';
import {reloadPane} from 'local_dimensions/central/tabs';

const FORM_CLASS = 'local_dimensions\\form\\template_dynamic_form';
const DATASOURCE = 'local_dimensions/central/competency_datasource';

const SELECTORS = {
    region: '[data-region="plans"]',
    selectTemplate: '[data-action="select-template"]',
    competencySearch: '[data-region="competency-search"]',
    clearCompetency: '[data-action="clear-competency"]',
    newTemplate: '[data-action="new-template"]',
    editTemplate: '[data-action="edit-template"]',
    deleteTemplate: '[data-action="delete-template"]',
};

/**
 * Open the template modal form and refresh the tab on success.
 *
 * @param {HTMLElement} pane
 * @param {Object} args
 * @param {String} titlekey
 * @param {String} titlecomponent
 */
const openForm = async(pane, args, titlekey, titlecomponent) => {
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args,
        modalConfig: {title: await getString(titlekey, titlecomponent)},
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => reloadPane(pane).catch(Notification.exception));
    form.show();
};

/**
 * Delete a template, asking how to handle its learning plans when it has any.
 *
 * @param {HTMLElement} pane
 * @param {String|Number} id
 * @param {String} name
 * @return {Promise<void>}
 */
const deleteTemplate = async(pane, id, name) => {
    const templateid = Number(id);
    const hasplans = await Ajax.call([{
        methodname: 'core_competency_template_has_related_data',
        args: {id: templateid},
    }])[0];

    const remove = (deleteplans) => Ajax.call([{
        methodname: 'core_competency_delete_template',
        args: {id: templateid, deleteplans: deleteplans},
    }])[0].then(() => reloadPane(pane)).catch(Notification.exception);

    const title = await getString('deletetemplate', 'tool_lp', name);

    if (hasplans) {
        const {html} = await Templates.renderForPromise('local_dimensions/central/delete_template_plans', {});
        const modal = await ModalSaveCancel.create({title, body: html});
        modal.setSaveButtonText(await getString('delete'));
        modal.getRoot().on(ModalEvents.save, () => {
            const checked = modal.getRoot()[0].querySelector('input[name="deleteplans"]:checked');
            remove(!!checked && checked.value === '1');
        });
        modal.show();
        return;
    }

    try {
        await Notification.deleteCancelPromise(await getString('delete'), title);
    } catch (e) {
        return;
    }
    remove(false);
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
            return;
        }
        if (event.target.closest(SELECTORS.newTemplate) && pane) {
            openForm(pane, {id: 0, contextid: region.dataset.contextid || 0}, 'managetemplates_addtemplate', 'local_dimensions');
            return;
        }
        const edit = event.target.closest(SELECTORS.editTemplate);
        if (edit && pane) {
            openForm(pane, {id: edit.dataset.id}, 'edittemplate', 'tool_lp');
            return;
        }
        const del = event.target.closest(SELECTORS.deleteTemplate);
        if (del && pane) {
            deleteTemplate(pane, del.dataset.id, del.dataset.name || '').catch(Notification.exception);
        }
    });
};
```

- [ ] **Step 2: Lint**

Run: `npx eslint public/local/dimensions/amd/src/central/plans.js` → exit 0, no output.

- [ ] **Step 3: Bump version**

`version.php`: `$plugin->version = 2026062701;` → `$plugin->version = 2026062702;`.

- [ ] **Step 4: Rebuild AMD**

```bash
cd /Volumes/N1TB/dev/github/moodle
npx grunt amd --root=public/local/dimensions
```
Expected: "Done."; then confirm `grep -q "local_dimensions/central/plans" public/local/dimensions/amd/build/central/plans.min.js && echo OK`.

- [ ] **Step 5: Commit** (on user approval)

```bash
git add amd/src/central/plans.js amd/build/central/plans.min.js amd/build/central/plans.min.js.map templates/central/plans.mustache templates/central/delete_template_plans.mustache version.php
git commit -m "feat: create/edit/delete plans from the Competency hub Plans tab

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Behat regression (CI-only)

**Files:**
- Create: `tests/behat/manage_plans.feature`

> Cannot run locally (no installed Moodle). Uses core_competency generators + built-in modal/form steps.

- [ ] **Step 1: Create the feature**

```gherkin
@local @local_dimensions @javascript
Feature: Manage learning plans from the Competency hub
  In order to maintain learning plan templates without leaving the hub
  As an administrator
  I need to create, edit and delete templates in a modal

  Background:
    Given the following config values are set as admin:
      | enabled | 1 | core_competency |
    And I log in as "admin"

  Scenario: Create a new learning plan template
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "New template" "button"
    And I set the field "Short name" to "Induction plan"
    And I click on "Save changes" "button"
    Then I should see "Induction plan"

  Scenario: Edit a learning plan template
    Given the following "core_competency > templates" exist:
      | shortname |
      | Old name  |
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Old name" "button"
    And I click on "Edit" "button"
    And I set the field "Short name" to "New name"
    And I click on "Save changes" "button"
    Then I should see "New name"
    And I should not see "Old name"

  Scenario: Delete a template that has no plans
    Given the following "core_competency > templates" exist:
      | shortname  |
      | Disposable |
    When I visit "/local/dimensions/central.php"
    And I click on "Learning plans" "link"
    And I click on "Disposable" "button"
    And I click on "Delete" "button"
    And I click on "Delete" "button" in the "Delete" "dialogue"
    Then I should not see "Disposable"
```

> The template above has no learning plans, so delete takes the simple-confirm path
> (`Notification.deleteCancelPromise`, dialogue titled "Delete"). The radio dialog (delete vs
> **unlink** plans) only appears when the template has plans; covering that path needs a
> `core_competency > plans` row generated from the template (`templateid` + `user`) — add as a
> follow-up scenario once the simple paths pass in CI. Field-label step text (e.g. "Short name")
> comes from `tool_lp` strings; adjust in CI if a label differs.

- [ ] **Step 2: Verify (CI)**

```bash
php admin/tool/behat/cli/init.php
vendor/bin/behat --config <behat_dataroot>/behat/behat.yml --tags @local_dimensions
```

- [ ] **Step 3: Commit** (on user approval)

```bash
git add tests/behat/manage_plans.feature
git commit -m "test: Behat for Competency hub plan CRUD

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: Update status doc

**Files:**
- Modify: `docs/implementation-status.md`

- [ ] **Step 1:** Move "Plans: CRUD" from "Next slices" into "Done" (note: `template_dynamic_form` modal reusing `api::{create,update}_template` + `lp_handler`; delete via `core_competency_delete_template` with the unlink/delete radio dialog; New/Edit/Delete buttons on the Plans tab). Renumber remaining slices (cross-framework picker + cohorts becomes #1).

- [ ] **Step 2: Commit** (on user approval)

```bash
git add docs/implementation-status.md docs/2026-06-27-plans-crud-*.md
git commit -m "docs: mark plan CRUD done; add spec + plan

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-review notes (controller)

- **Custom-field parity:** `get_data()` override + `instance_form_definition_after_data` + SCSS handling copied from `template_form` (authoritative for templates). `lp_handler::instance_form_save_with_image` is **2-arg** (`$data, $instanceid`) — unlike competency_handler's 3-arg version. Cache: `template_metadata_cache::invalidate_template`.
- **Deferred (deliberate):** the client-side `local_dimensions/scss_validation` JS from `template_form` is **not** wired in the modal (its hard-coded field selector would hit the `data-random-ids` issue); server-side `scss_manager::validate_scss` in `validation()` still guards it. Follow-up only if needed.
- **No new WS / no new lang string.**
