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
 * Applies the ticked part of a learning plan CSV import, re-validating at write time.
 *
 * The only write path of the feature. Every selection is checked against a projection built from
 * the file and the database AS THEY ARE NOW, not as they were when the preview was drawn: an item
 * whose verdict or fingerprint moved is refused and repainted rather than written.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\template;
use core_competency\template_competency;
use local_dimensions\customfield\lp_handler;
use local_dimensions\event\template_imported;
use local_dimensions\helper;
use local_dimensions\scss_manager;
use local_dimensions\template_course_cache;
use local_dimensions\template_metadata_cache;

/**
 * Write the selected part of a projected import, one template per transaction.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_csv_importer {
    /**
     * The CSV columns whose value the operator may replace from the preview.
     *
     * Only the three admin-editable option lists, as an isset() map: a remap is a choice among
     * options the server computed, so an unlisted token from a crafted payload is ignored rather
     * than allowed to set an arbitrary custom field.
     *
     * @var array
     */
    const REMAPPABLE = ['cf_tag1' => true, 'cf_tag2' => true, 'cf_type' => true];

    /** @var array The parse() output being applied. */
    protected $parsed;

    /** @var \context The context being written to. */
    protected $target;

    /** @var bool Whether a matched template is updated rather than skipped. */
    protected $updateexisting;

    /** @var template_import_plan|null The projection this run is being checked against. */
    protected $plan = null;

    /**
     * Build an importer for one parsed file against one target context.
     *
     * @param array $parsed The template_csv_serializer::parse() output.
     * @param \context $target The context to write to.
     * @param bool $updateexisting Whether a matched template is updated rather than skipped.
     */
    public function __construct(array $parsed, \context $target, bool $updateexisting) {
        $this->parsed = $parsed;
        $this->target = $target;
        $this->updateexisting = $updateexisting;
    }

    /**
     * The projection this run was checked against, for the caller's repaint.
     *
     * @return template_import_plan|null
     */
    public function get_plan(): ?template_import_plan {
        return $this->plan;
    }

    /**
     * Apply the selected items and report what happened to each.
     *
     * The file is re-analysed ONCE per call, not once per selection: the projection is a whole
     * view of the file against the site, and building it per item would both cost N times as much
     * and let two selections be checked against two different states.
     *
     * @param array $selections Each an array of itemkey, verdict, fingerprint, remedy and links.
     * @return array Each an array of itemkey, outcome, message and templateid.
     */
    public function apply(array $selections): array {
        $analyser = new template_import_analyser($this->parsed, $this->target, $this->updateexisting);
        $this->plan = $analyser->analyse();

        $results = [];
        foreach ($selections as $selection) {
            $itemkey = (string) ($selection['itemkey'] ?? '');
            $item = $this->plan->get_item($itemkey);
            $refusal = $this->refuse($item, $selection);
            if ($refusal !== null) {
                $results[] = $refusal;
                continue;
            }
            $results[] = $this->write_item($item, $selection);
        }
        return $results;
    }

    /**
     * Whether a selection must be refused, and why.
     *
     * @param array|null $item The freshly projected item, or null when the file no longer has it.
     * @param array $selection The selection as the browser sent it.
     * @return array|null A result entry, or null when the selection may proceed.
     */
    protected function refuse(?array $item, array $selection): ?array {
        $itemkey = (string) ($selection['itemkey'] ?? '');
        if ($item === null) {
            return $this->result($itemkey, template_import_verdict::OUTCOME_GONE, 0);
        }
        if (
            (string) $item['verdict'] !== (string) ($selection['verdict'] ?? '')
            || (string) ($item['fingerprint'] ?? '') !== (string) ($selection['fingerprint'] ?? '')
        ) {
            // Either the file moved under the operator or the site did. Nothing is written and the
            // fresh item goes back so the row repaints and the decision is made again.
            return $this->result($itemkey, template_import_verdict::OUTCOME_CHANGED, 0);
        }
        if (empty($item['selectable'])) {
            return $this->result($itemkey, template_import_verdict::OUTCOME_SKIPPED, 0);
        }

        $remedy = (string) ($selection['remedy'] ?? '');
        if (
            $remedy !== '' && $remedy !== template_import_verdict::REMEDY_NONE
            && !in_array($remedy, array_column($item['remedies'], 'remedy'), true)
        ) {
            return $this->result($itemkey, template_import_verdict::OUTCOME_CHANGED, 0);
        }
        if (!empty($item['remedies']) && ($remedy === '' || $remedy === template_import_verdict::REMEDY_NONE)) {
            // A row that needs a decision cannot be applied without one.
            return $this->result($itemkey, template_import_verdict::OUTCOME_SKIPPED, 0);
        }

        /* Re-run the structure roll-up over the SELECTION, not just the projection: unticking
           every resolvable competency of a row whose remaining competencies do not exist here
           would otherwise write the empty template the roll-up exists to prevent. */
        if ((int) $item['linksunresolved'] > 0 && empty($this->selected_links($item, $selection))) {
            return $this->result($itemkey, template_import_verdict::OUTCOME_SKIPPED, 0);
        }
        return null;
    }

    /**
     * The link items the selection ticked, in file order, that actually resolved.
     *
     * @param array $item The freshly projected item.
     * @param array $selection The selection as the browser sent it.
     * @return array Link items keyed by their item key.
     */
    protected function selected_links(array $item, array $selection): array {
        $ticked = [];
        foreach ((array) ($selection['links'] ?? []) as $linkkey) {
            $ticked[(string) $linkkey] = true;
        }
        $selected = [];
        foreach ($item['links'] as $linkkey => $link) {
            if (!isset($ticked[$linkkey]) || empty($link['selectable']) || (int) $link['competencyid'] <= 0) {
                continue;
            }
            $selected[$linkkey] = $link;
        }
        return $selected;
    }

    /**
     * Write one accepted item inside its own transaction.
     *
     * Per template rather than per run: a run-wide transaction cannot express partial apply,
     * because a mid-loop bail rolls back everything already written. This shape gives atomicity
     * where it matters — the core row, its custom fields and its links commit or vanish together —
     * and partiality at the level of the run.
     *
     * @param array $item The freshly projected item.
     * @param array $selection The selection as the browser sent it.
     * @return array The result entry.
     */
    protected function write_item(array $item, array $selection): array {
        global $DB;

        $itemkey = (string) $item['itemkey'];
        $remedy = (string) ($selection['remedy'] ?? template_import_verdict::REMEDY_NONE);
        $row = $this->parsed['templates'][$item['rowindex']] ?? null;
        if ($row === null) {
            return $this->result($itemkey, template_import_verdict::OUTCOME_GONE, 0);
        }

        $isnew = (int) $item['matchedid'] <= 0 || $remedy === template_import_verdict::REMEDY_CREATEHERE;
        $links = $this->selected_links($item, $selection);
        $templateid = 0;
        $added = 0;

        $transaction = $DB->start_delegated_transaction();
        try {
            $templateid = $this->write_core_row($item, $row, $remedy, $isnew);
            $this->write_customfields($item, $row, $templateid, $isnew, $selection);
            $added = $this->write_links($templateid, $links);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            /* Catch Throwable, not moodle_exception: core's own per-item idiom misses \Error and
               \TypeError, which leave the delegated transaction open and moodle_database's
               force_rollback stuck true — every later write in the request then dies with
               dml_transaction_exception and the run ends half-written with no report. */
            try {
                $transaction->rollback($e);
            } catch (\Throwable $ignored) {
                $ignored = null;
            }
            if ($DB->is_transaction_started()) {
                $DB->force_transaction_rollback();
            }
            return $this->result($itemkey, template_import_verdict::OUTCOME_FAILED, 0, $e->getMessage());
        }

        $this->invalidate_caches($templateid, $added > 0);
        $outcome = $isnew ? template_import_verdict::OUTCOME_CREATED : template_import_verdict::OUTCOME_UPDATED;
        $this->log_template($templateid, $outcome, $itemkey, $added);
        return $this->result($itemkey, $outcome, $templateid);
    }

    /**
     * Create or update the core template row.
     *
     * @param array $item The freshly projected item.
     * @param \stdClass $row The parsed source row.
     * @param string $remedy The chosen remedy.
     * @param bool $isnew Whether a template is being created.
     * @return int The template id.
     */
    protected function write_core_row(array $item, \stdClass $row, string $remedy, bool $isnew): int {
        $record = new \stdClass();
        $record->shortname = $remedy === template_import_verdict::REMEDY_TRUNCATE
            ? \core_text::substr((string) $row->shortname, 0, template_import_analyser::SHORTNAME_MAXLENGTH)
            : (string) $row->shortname;
        if ($row->description !== null) {
            $record->description = (string) $row->description;
        }
        if ($row->descriptionformat !== null && trim((string) $row->descriptionformat) !== '') {
            $record->descriptionformat = (int) $row->descriptionformat;
        }
        if ($row->visible !== null && trim((string) $row->visible) !== '') {
            $record->visible = (int) (bool) (int) $row->visible;
        }
        $duedate = $this->resolve_duedate($row, $remedy);
        if ($duedate !== null) {
            $record->duedate = $duedate;
        }

        if ($isnew) {
            $record->contextid = (int) $this->target->id;
            return (int) api::create_template($record)->get('id');
        }
        /* contextid is deliberately NOT sent on update: core throws a coding_exception when a
           submitted contextid differs from the stored one, and sending the stored value would be
           a pointless way to find that out. */
        $record->id = (int) $item['matchedid'];
        api::update_template($record);
        return (int) $item['matchedid'];
    }

    /**
     * The due date to write, honouring the remedy, or null to leave it alone.
     *
     * @param \stdClass $row The parsed source row.
     * @param string $remedy The chosen remedy.
     * @return int|null
     */
    protected function resolve_duedate(\stdClass $row, string $remedy): ?int {
        if ($remedy === template_import_verdict::REMEDY_KEEPDUEDATE || $row->duedate === null) {
            return null;
        }
        if ($remedy === template_import_verdict::REMEDY_CLEARDUEDATE) {
            return 0;
        }
        $duedate = template_csv_serializer::parse_duedate((string) $row->duedate);
        if ($duedate === null) {
            return null;
        }
        if ($remedy === template_import_verdict::REMEDY_SHIFTDUEDATE && $duedate > 0) {
            return $this->shift_forward($duedate);
        }
        return $duedate;
    }

    /**
     * Move a past date forward in whole years until it is in the future, keeping day and month.
     *
     * @param int $duedate The stored timestamp.
     * @return int
     */
    protected function shift_forward(int $duedate): int {
        $date = (new \DateTimeImmutable('@' . $duedate))->setTimezone(new \DateTimeZone('UTC'));
        $now = time();
        $guard = 0;
        while ($date->getTimestamp() <= $now && $guard < 200) {
            $date = $date->modify('+1 year');
            $guard++;
        }
        return $date->getTimestamp();
    }

    /**
     * Write the custom fields through the handler, back-filling the identity column on create.
     *
     * Never by SQL and never through instance_form_save_with_image(), which hardcodes
     * $isnew = true — so the audit flag would be wrong — and wraps the call in a
     * dml_write_exception retry that cannot recover inside a PostgreSQL transaction a failed
     * statement has already poisoned.
     *
     * @param array $item The freshly projected item.
     * @param \stdClass $row The parsed source row.
     * @param int $templateid The template that was created or updated.
     * @param bool $isnew Whether a template was created.
     * @param array $selection The selection as the browser sent it, carrying any option remaps.
     * @return void
     */
    protected function write_customfields(
        array $item,
        \stdClass $row,
        int $templateid,
        bool $isnew,
        array $selection
    ): void {
        $cf = (array) $row->cf;
        /* An option label this site does not have would otherwise resolve to index 0 - cleared -
           which is a silent change. The preview offered the target's own options, so what lands
           is the operator's choice. */
        foreach ((array) ($selection['remaps'] ?? []) as $remap) {
            $token = (string) ($remap['token'] ?? '');
            if (isset(self::REMAPPABLE[$token]) && array_key_exists($token, $cf)) {
                $cf[$token] = (string) ($remap['value'] ?? '');
            }
        }
        /* The identity column is back-filled on create so the second import of the same file is
           an update rather than a duplicate: competency_template has no ID number of its own. */
        if ($isnew && (string) $row->templateidnumber !== '') {
            $cf['template_idnumber'] = (string) $row->templateidnumber;
        }
        if (empty($cf)) {
            return;
        }
        $formdata = (object) (['id' => $templateid] + helper::template_customfields_to_formdata($cf));
        lp_handler::create()->instance_form_save($formdata, $isnew || empty($item['hascustomfielddata']));
    }

    /**
     * Add the selected competencies, then renumber the whole final set.
     *
     * Nothing is ever removed — a link that produced user_competency and evidence rows must not
     * vanish with them — so the renumbering covers the file's links first and then the kept
     * extras, which is also what stops the file's own order from colliding with the retained
     * rows' existing sortorder and silently falling back to id order.
     *
     * @param int $templateid The template being written.
     * @param array $links The selected, resolved link items.
     * @return int How many competencies were actually added.
     */
    protected function write_links(int $templateid, array $links): int {
        $ordered = array_values($links);
        usort($ordered, static function (array $first, array $second): int {
            return (int) $first['sortorder'] <=> (int) $second['sortorder'];
        });

        $added = 0;
        $fileids = [];
        foreach ($ordered as $link) {
            $competencyid = (int) $link['competencyid'];
            if (in_array($competencyid, $fileids, true)) {
                continue;
            }
            $fileids[] = $competencyid;
            // A false return is the committed-duplicate path, never an error, and must not reach
            // an event trigger's ->get().
            if (api::add_competency_to_template($templateid, $competencyid)) {
                $added++;
            }
        }

        $kept = [];
        foreach (template_competency::list_competencies($templateid) as $competency) {
            $competencyid = (int) $competency->get('id');
            if (!in_array($competencyid, $fileids, true)) {
                $kept[] = $competencyid;
            }
        }

        foreach (array_merge($fileids, $kept) as $sortorder => $competencyid) {
            $link = template_competency::get_record([
                'templateid' => $templateid,
                'competencyid' => $competencyid,
            ]);
            /* The lookup is guarded because get_record() returns literal false, and ->set() on
               false raises an \Error that is not a moodle_exception. This is reachable: the add
               above returns false without creating a row when the link already exists. */
            if (!$link) {
                continue;
            }
            if ((int) $link->get('sortorder') !== (int) $sortorder) {
                $link->set('sortorder', (int) $sortorder);
                $link->update();
            }
        }
        return $added;
    }

    /**
     * Invalidate everything the write made stale. After the commit, and mandatory.
     *
     * db/events.php registers only core competency events, so the plugin's own
     * template_customfields_updated has no observer: writing custom-field values invalidates
     * nothing by itself. The SCSS cache has no TTL and stores the empty string on a miss, so a
     * single learner render between "template created" and "SCSS written" would poison its entry
     * permanently.
     *
     * @param int $templateid The template that was written.
     * @param bool $linkschanged Whether its competency links changed.
     * @return void
     */
    protected function invalidate_caches(int $templateid, bool $linkschanged): void {
        template_metadata_cache::invalidate_template($templateid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            scss_manager::invalidate_cache($templateid, helper::AREA_LP);
        }
        if ($linkschanged) {
            template_course_cache::invalidate_template($templateid);
        }
    }

    /**
     * Trigger the per-template audit event. After the commit, never inside the transaction.
     *
     * @param int $templateid The template that was written.
     * @param string $outcome The outcome constant.
     * @param string $itemkey The item key, so a run can be traced back to its row.
     * @param int $added How many competencies were added.
     * @return void
     */
    protected function log_template(int $templateid, string $outcome, string $itemkey, int $added): void {
        $stored = template::get_record(['id' => $templateid]);
        if (!$stored) {
            return;
        }
        template_imported::create([
            'objectid' => $templateid,
            'context' => $stored->get_context(),
            'other' => [
                'outcome' => $outcome,
                'itemkey' => $itemkey,
                'linksadded' => $added,
                'shortname' => (string) $stored->get('shortname'),
            ],
        ])->trigger();
    }

    /**
     * One result entry.
     *
     * @param string $itemkey The item key.
     * @param string $outcome The outcome constant.
     * @param int $templateid The template written, or 0.
     * @param string $message A failure message, when there is one.
     * @return array
     */
    protected function result(string $itemkey, string $outcome, int $templateid, string $message = ''): array {
        return [
            'itemkey' => $itemkey,
            'outcome' => $outcome,
            'templateid' => $templateid,
            'message' => $message,
        ];
    }
}
