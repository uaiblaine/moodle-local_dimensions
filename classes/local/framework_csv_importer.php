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
 * Persist a parsed framework CSV into a chosen context, with the plugin custom fields.
 *
 * Ports the core admin/tool/lpimportcsv algorithm (tree build by parentidnumber,
 * exportid→new-id map for rule/relation remapping, global-scale reuse) but takes the target
 * context as a parameter (so it can import into a course category, which the core tool cannot)
 * and writes the competency custom fields per node. The whole run is one DB transaction.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use local_dimensions\competency_metadata_cache;
use local_dimensions\customfield\competency_handler;
use local_dimensions\helper;
use local_dimensions\scss_manager;

/**
 * Import a parsed competency framework CSV into a target context.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class framework_csv_importer {
    /** @var array The parsed CSV (framework record, flat competencies map). */
    private $parsed;

    /** @var \context Target context (system or course category). */
    private $context;

    /** @var bool Whether to update a same-idnumber framework in place instead of creating a new one. */
    private $updateexisting;

    /** @var array Cache of global scales keyed by their compact-item string. */
    private $scalecache = [];

    /** @var array Map of the CSV exportid => created/updated competency (for rule/relation remap). */
    private $exportidmap = [];

    /** @var array Map of idnumber => created/updated competency (for relations). */
    private $idnumbermap = [];

    /** @var array In update mode: idnumber => the framework's existing competency. */
    private $existingbyidnumber = [];

    /** @var int Number of competencies created or updated. */
    private $competencycount = 0;

    /**
     * Constructor.
     *
     * @param array $parsed Output of framework_csv_serializer::parse().
     * @param \context $context Target context (CONTEXT_SYSTEM or CONTEXT_COURSECAT).
     * @param bool $updateexisting Update a same-idnumber framework in the context instead of creating a new one.
     */
    public function __construct(array $parsed, \context $context, bool $updateexisting) {
        $this->parsed = $parsed;
        $this->context = $context;
        $this->updateexisting = $updateexisting;
    }

    /**
     * Import the framework and its competency tree.
     *
     * @return array{frameworkid: int, competencycount: int}
     * @throws \moodle_exception When the CSV carries no framework row, or needs a site scale the user may not create.
     */
    public function import(): array {
        global $DB;

        // The importer checks what it can check itself instead of trusting its one caller, and before it provisions anything.
        require_capability('moodle/competency:competencymanage', $this->context);

        $fw = $this->parsed['framework'] ?? null;
        if (!$fw) {
            throw new \moodle_exception('central_frameworks_import_noframeworkrow', 'local_dimensions');
        }

        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_EXTRA);
        helper::ensure_custom_fields_exist(helper::AREA_COMPETENCY);

        // Build the in-memory tree (framework children matched by parentidnumber).
        $this->add_children($fw, '');

        // Refuse before the first write: a delegated transaction cannot undo rows inside a caller's own.
        $this->assert_scales_available($fw);

        $transaction = $DB->start_delegated_transaction();
        try {
            $framework = $this->import_tree($fw);
        } catch (\Throwable $e) {
            // A refused scale must leave nothing behind.
            $transaction->rollback($e);
        }
        $transaction->allow_commit();

        return ['frameworkid' => (int) $framework->get('id'), 'competencycount' => $this->competencycount];
    }

    /**
     * Refuse the import up front when it needs a site scale its user may not create.
     *
     * Mirrors which scales upsert_framework() and create_competency() will resolve: the
     * framework's unless it is frozen, and each new competency's own.
     *
     * @param \stdClass $fw The parsed framework record with its children attached.
     * @throws \moodle_exception When a needed scale is empty (framework) or missing and not creatable.
     */
    private function assert_scales_available(\stdClass $fw): void {
        $existing = null;
        $existingidnumbers = [];
        if ($this->updateexisting) {
            $existing = competency_framework::get_record([
                'idnumber' => $fw->idnumber,
                'contextid' => $this->context->id,
            ]) ?: null;
            if ($existing) {
                foreach (competency::get_records(['competencyframeworkid' => $existing->get('id')]) as $comp) {
                    $existingidnumbers[(string) $comp->get('idnumber')] = true;
                }
            }
        }

        $needed = [];
        if (!$existing || !$existing->has_user_competencies()) {
            if ($fw->scalevalues === '') {
                throw new \moodle_exception('central_frameworks_import_emptyscale', 'local_dimensions');
            }
            $needed[] = $fw->scalevalues;
        }
        foreach ($this->parsed['competencies'] as $record) {
            if (
                self::is_importable($record)
                && $record->scalevalues !== ''
                && !isset($existingidnumbers[(string) $record->idnumber])
            ) {
                $needed[] = $record->scalevalues;
            }
        }

        if (has_capability('moodle/course:managescales', \context_system::instance())) {
            return;
        }
        $this->load_scale_cache();
        foreach ($needed as $scalevalues) {
            if (!isset($this->scalecache[$scalevalues])) {
                throw new \moodle_exception('central_frameworks_import_scalenotallowed', 'local_dimensions', '', $scalevalues);
            }
        }
    }

    /**
     * Whether create_competency() will write the row (it skips rows without an idnumber or shortname).
     *
     * Shared with assert_scales_available() so a row that is never created never asks for a scale.
     *
     * @param \stdClass $record Parsed competency record.
     * @return bool
     */
    private static function is_importable(\stdClass $record): bool {
        return $record->idnumber !== '' && $record->shortname !== '';
    }

    /**
     * Index the global scales by their compact-item string, once.
     *
     * @return void
     */
    private function load_scale_cache(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        if (!empty($this->scalecache)) {
            return;
        }
        foreach (\grade_scale::fetch_all_global() as $scale) {
            $scale->load_items();
            $this->scalecache[$scale->compact_items()] = $scale;
        }
    }

    /**
     * Write the framework, its competencies, rules and relations (inside the caller's transaction).
     *
     * @param \stdClass $fw The parsed framework record with its children attached.
     * @return competency_framework
     */
    private function import_tree(\stdClass $fw): competency_framework {
        $framework = $this->upsert_framework($fw);

        // In update mode, index the framework's existing competencies by idnumber.
        if ($this->updateexisting) {
            foreach (competency::get_records(['competencyframeworkid' => $framework->get('id')]) as $existing) {
                $this->existingbyidnumber[(string) $existing->get('idnumber')] = $existing;
            }
        }

        foreach ($fw->children as $root) {
            $this->create_competency($root, null, $framework);
        }
        foreach ($fw->children as $root) {
            $this->set_rules($root);
            $this->set_related($root);
        }
        return $framework;
    }

    /**
     * Create the framework, or update a same-idnumber one in the context when in update mode.
     *
     * @param \stdClass $fw The parsed framework record.
     * @return competency_framework
     */
    private function upsert_framework(\stdClass $fw): competency_framework {
        $existing = null;
        if ($this->updateexisting) {
            $existing = competency_framework::get_record([
                'idnumber' => $fw->idnumber,
                'contextid' => $this->context->id,
            ]) ?: null;
        }

        $record = new \stdClass();
        $record->shortname = $fw->shortname;
        $record->idnumber = $fw->idnumber;
        $record->description = $fw->description;
        // Preserve the parsed format (0 = FORMAT_MOODLE is valid); ?: would flip it to HTML.
        $record->descriptionformat = (int) $fw->descriptionformat;
        $record->visible = 1;
        if ($fw->taxonomies !== '') {
            $record->taxonomies = $fw->taxonomies;
        }

        // The scale is frozen once a framework has graded user competencies; only (re)set it
        // when creating or when the existing framework has no user competencies.
        if (!$existing || !$existing->has_user_competencies()) {
            $record->scaleid = $this->get_scale_id($fw->scalevalues, $fw->shortname);
            $record->scaleconfiguration = $this->get_scale_configuration($record->scaleid, $fw->scaleconfiguration);
        }

        if ($existing) {
            $record->id = $existing->get('id');
            api::update_framework($record);
            return competency_framework::get_record(['id' => $existing->get('id')]);
        }
        $record->contextid = $this->context->id;
        return api::create_framework($record);
    }

    /**
     * Attach every competency whose parentidnumber matches to the node, recursively.
     *
     * @param \stdClass $node Framework or competency record (children appended by reference).
     * @param string $parentidnumber The idnumber whose children to collect ('' = framework roots).
     */
    private function add_children(\stdClass $node, string $parentidnumber): void {
        foreach ($this->parsed['competencies'] as $competency) {
            if ((string) $competency->parentidnumber === $parentidnumber) {
                $node->children[] = $competency;
                $this->add_children($competency, (string) $competency->idnumber);
            }
        }
    }

    /**
     * Create (or, in update mode, update) one competency and recurse into its children.
     *
     * @param \stdClass $record The parsed competency record.
     * @param competency|null $parent The created/updated parent, or null for a root.
     * @param competency_framework $framework The target framework.
     * @return competency|null The persisted competency, or null when the row is unusable.
     */
    private function create_competency(\stdClass $record, ?competency $parent, competency_framework $framework): ?competency {
        if (!self::is_importable($record)) {
            return null;
        }
        $parentid = $parent ? (int) $parent->get('id') : 0;
        $existing = $this->updateexisting ? ($this->existingbyidnumber[$record->idnumber] ?? null) : null;

        $comp = new \stdClass();
        $comp->competencyframeworkid = (int) $framework->get('id');
        $comp->shortname = $record->shortname;
        $comp->idnumber = $record->idnumber;
        if ($record->description !== '') {
            $comp->description = $record->description;
            $comp->descriptionformat = $record->descriptionformat;
        }

        if ($existing) {
            // Leave the existing competency's scale untouched (a graded scale is frozen).
            $comp->id = (int) $existing->get('id');
            api::update_competency($comp);
            $created = competency::get_record(['id' => $comp->id]);
            if ((int) $created->get('parentid') !== $parentid) {
                api::set_parent_competency($comp->id, $parentid);
                $created = competency::get_record(['id' => $comp->id]);
            }
            $isnew = false;
        } else {
            if ($record->scalevalues !== '') {
                $comp->scaleid = $this->get_scale_id($record->scalevalues, $record->shortname);
                $comp->scaleconfiguration = $this->get_scale_configuration($comp->scaleid, $record->scaleconfiguration);
            }
            $comp->parentid = $parentid;
            $created = api::create_competency($comp);
            $isnew = true;
        }

        $this->competencycount++;
        if ($record->exportid !== '') {
            $this->exportidmap[$record->exportid] = $created;
        }
        $this->idnumbermap[$record->idnumber] = $created;
        $record->createdcomp = $created;

        $this->write_customfields((int) $created->get('id'), $record, $isnew);

        foreach ($record->children as $child) {
            $this->create_competency($child, $created, $framework);
        }
        return $created;
    }

    /**
     * Persist the plugin custom fields carried by the CSV row for one competency.
     *
     * @param int $competencyid Competency id.
     * @param \stdClass $record The parsed competency record (its cf map is used).
     * @param bool $isnew Whether the competency was just created.
     */
    private function write_customfields(int $competencyid, \stdClass $record, bool $isnew): void {
        if (empty($record->cf) || !is_array($record->cf)) {
            return;
        }
        $formdata = (object) (['id' => $competencyid] + helper::customfields_to_formdata($record->cf));
        competency_handler::create()->instance_form_save($formdata, $isnew);
        competency_metadata_cache::invalidate_competency($competencyid);
        if (get_config('local_dimensions', 'enablecustomscss')) {
            scss_manager::invalidate_cache($competencyid, 'competency');
        }
    }

    /**
     * Apply the completion rule attached to a competency (and its children), remapping the
     * config's embedded competency ids through the exportid map.
     *
     * @param \stdClass $record The parsed competency record.
     */
    private function set_rules(\stdClass $record): void {
        if (!empty($record->createdcomp) && $record->ruletype !== '') {
            $class = $record->ruletype;
            /* The CSV names the class and only competency rules may be called through it; anything else
               is skipped like a missing class. Core's importer checks class_exists() alone
               ({@see \tool_lpimportcsv\framework_importer}). */
            if (class_exists($class) && is_subclass_of($class, \core_competency\competency_rule::class)) {
                $oldconfig = $record->ruleconfig === 'null' ? null : $record->ruleconfig;
                $newconfig = $class::migrate_config($oldconfig, $this->exportidmap);
                $comp = $record->createdcomp;
                $comp->set('ruleconfig', $newconfig);
                $comp->set('ruletype', $class);
                $comp->set('ruleoutcome', (int) $record->ruleoutcome);
                $comp->update();
            }
        }
        foreach ($record->children as $child) {
            $this->set_rules($child);
        }
    }

    /**
     * Recreate the related-competency links (idnumbers in the relatedidnumbers column).
     *
     * @param \stdClass $record The parsed competency record.
     */
    private function set_related(\stdClass $record): void {
        if (!empty($record->createdcomp) && $record->relatedidnumbers !== '') {
            $comp = $record->createdcomp;
            foreach (explode(',', $record->relatedidnumbers) as $raw) {
                $idnumber = str_replace('%2C', ',', $raw);
                if (isset($this->idnumbermap[$idnumber])) {
                    api::add_related_competency((int) $comp->get('id'), (int) $this->idnumbermap[$idnumber]->get('id'));
                }
            }
        }
        foreach ($record->children as $child) {
            $this->set_related($child);
        }
    }

    /**
     * Find a global scale matching these compact scale values, creating one if needed.
     *
     * @param string $scalevalues Compact scale-item string (comma-separated labels).
     * @param string $name Name hint for a newly created scale.
     * @return int Scale id.
     * @throws \moodle_exception When the scale is empty or would need creating by a user who may not.
     */
    private function get_scale_id(string $scalevalues, string $name): int {
        global $USER;

        $this->load_scale_cache();
        if (isset($this->scalecache[$scalevalues])) {
            return (int) $this->scalecache[$scalevalues]->id;
        }
        if ($scalevalues === '') {
            throw new \moodle_exception('central_frameworks_import_emptyscale', 'local_dimensions');
        }
        // A site scale is shown to every user, and core gates creating one on this capability.
        if (!has_capability('moodle/course:managescales', \context_system::instance())) {
            throw new \moodle_exception('central_frameworks_import_scalenotallowed', 'local_dimensions', '', $scalevalues);
        }
        $newscale = new \grade_scale();
        $newscale->name = get_string('central_frameworks_import_scalename', 'local_dimensions', $name);
        $newscale->courseid = 0;
        $newscale->userid = $USER->id;
        $newscale->scale = $scalevalues;
        $newscale->description = '';
        $newscale->insert();
        $this->scalecache[$scalevalues] = $newscale;
        return (int) $newscale->id;
    }

    /**
     * Rewrite the scaleid inside a scaleconfiguration JSON so it points at the resolved scale.
     *
     * @param int $scaleid Resolved scale id.
     * @param string $config The scaleconfiguration JSON from the CSV.
     * @return string
     */
    private function get_scale_configuration(int $scaleid, string $config): string {
        $decoded = json_decode($config);
        if (!is_array($decoded) || empty($decoded) || !is_object($decoded[0])) {
            return $config;
        }
        $decoded[0]->scaleid = $scaleid;
        return json_encode($decoded);
    }
}
