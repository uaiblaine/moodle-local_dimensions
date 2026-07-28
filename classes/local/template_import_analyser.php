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
 * Projects a parsed learning plan CSV against this site without writing anything.
 *
 * This class is the reason the feature can promise "nothing has been written yet". It contains no
 * core_competency write call, no DML write of any kind, no create or update call on a persistent,
 * and no custom-field provisioning call — provisioning writes a category row and up to fourteen
 * field rows, which would make the preview's own reassurance false. Provisioning already runs once
 * per session from the footer hook; a field that still resolves to null here is reported, not
 * created. The class is deliberately written so that grepping it for the write verbs finds nothing.
 *
 * Validity is asked of core rather than reimplemented: persistent::validate() returns
 * true|lang_string[] and writes nothing.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\api;
use core_competency\competency;
use core_competency\competency_framework;
use core_competency\template;
use core_competency\template_competency;
use local_dimensions\constants;
use local_dimensions\customfield\lp_handler;
use local_dimensions\helper;
use local_dimensions\scss_manager;

/**
 * Resolve a parsed template CSV against the target site and project the result.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_import_analyser {
    /** @var int The length competency_template.shortname accepts. */
    const SHORTNAME_MAXLENGTH = 100;

    /**
     * CSV column token => the custom-field shortname that backs it.
     *
     * Used for the provisioning, writability and length checks; the value encoding itself is
     * owned by helper::template_customfields_to_formdata().
     *
     * @var array
     */
    const CF_FIELDS = [
        'template_idnumber' => constants::CFIELD_TEMPLATE_IDNUMBER,
        'cf_displaymode' => constants::CFIELD_DISPLAYMODE,
        'cf_subline_source' => constants::CFIELD_SUBLINE_SOURCE,
        'cf_showrelated' => constants::CFIELD_SHOWRELATED,
        'cf_showrelatedlink' => constants::CFIELD_SHOWRELATEDLINK,
        'cf_bgcolor' => constants::CFIELD_CUSTOMBGCOLOR,
        'cf_textcolor' => constants::CFIELD_CUSTOMTEXTCOLOR,
        'cf_tag1' => constants::CFIELD_TAG1,
        'cf_tag2' => constants::CFIELD_TAG2,
        'cf_type' => constants::CFIELD_TYPE,
        'cf_enrollmentfilter' => constants::CFIELD_ENROLLMENTFILTER,
        'cf_singlecourseredirect' => constants::CFIELD_SINGLECOURSEREDIRECT,
        'cf_lockedcardmode' => constants::CFIELD_LOCKEDCARDMODE,
        'cf_showlockeddate' => constants::CFIELD_SHOWLOCKEDDATE,
        'cf_customscss' => constants::CFIELD_CUSTOMSCSS,
    ];

    /** @var array The parse() output being analysed. */
    protected $parsed;

    /** @var \context The context the import would write to. */
    protected $target;

    /** @var bool Whether a matched template would be updated rather than skipped. */
    protected $updateexisting;

    /** @var bool Whether the user may manage templates in the target context. */
    protected $canmanage = false;

    /** @var bool Whether the user may write the custom SCSS field. */
    protected $caneditscss = false;

    /** @var array Custom-field shortname => field_controller|null, memoised. */
    protected $fields = [];

    /** @var array Custom-field shortnames the current user may write. */
    protected $editable = [];

    /** @var array Frameworks readable from the target, keyed by id. */
    protected $readableframeworks = [];

    /** @var bool Whether the readable-framework lookup was refused outright. */
    protected $frameworksunreadable = false;

    /** @var array Framework resolution memo, keyed by 'i:<idnumber>' or 's:<shortname>'. */
    protected $frameworkmemo = [];

    /** @var array Competencies prefetched per framework id. */
    protected $competencymemo = [];

    /** @var array File-level notices, keyed by notice key so each is reported once. */
    protected $notices = [];

    /** @var array Missing structures, keyed by their idnumber or shortname. */
    protected $missingstructures = [];

    /** @var array Free-text values collected for the listing notices, keyed by notice key. */
    protected $noticevalues = [];

    /**
     * Build an analyser for one parsed file against one target context.
     *
     * @param array $parsed The template_csv_serializer::parse() output.
     * @param \context $target The context the import would write to.
     * @param bool $updateexisting Whether a matched template is updated rather than skipped.
     */
    public function __construct(array $parsed, \context $target, bool $updateexisting) {
        $this->parsed = $parsed;
        $this->target = $target;
        $this->updateexisting = $updateexisting;
    }

    /**
     * Project the file against the target, writing nothing.
     *
     * @return template_import_plan
     */
    public function analyse(): template_import_plan {
        $this->prepare_target_state();

        $items = [];
        $grouped = $this->group_links();

        foreach (($this->parsed['templates'] ?? []) as $index => $row) {
            $itemkey = 't' . $index;
            $items[$itemkey] = $this->label_item(
                $this->analyse_template($itemkey, (int) $index, $row, $grouped['bykey'][$index] ?? [])
            );
        }
        foreach ($grouped['orphans'] as $offset => $link) {
            $itemkey = 'o' . $offset;
            $items[$itemkey] = $this->label_item($this->orphan_item($itemkey, $link));
        }

        $this->collect_static_notices();

        return new template_import_plan(
            $items,
            array_values($this->notices),
            array_values($this->missingstructures),
            $this->target
        );
    }

    /**
     * Resolve everything that is true of the target site rather than of one row.
     *
     * @return void
     */
    protected function prepare_target_state(): void {
        $this->canmanage = has_capability('moodle/competency:templatemanage', $this->target);
        $this->caneditscss = has_capability('local/dimensions:editcustomscss', \context_system::instance());

        foreach (self::CF_FIELDS as $shortname) {
            $this->fields[$shortname] = helper::find_field_by_shortname($shortname, helper::AREA_LP);
        }

        foreach (lp_handler::create()->get_editable_fields(0) as $field) {
            $this->editable[(string) $field->get('shortname')] = true;
        }

        /* 'parents' and 'children' are mutually exclusive in api::get_related_contexts(), so the
           union is assembled from two calls: 'children' alone cannot see a system-context
           structure from a category, and 'parents' alone cannot see one in a subcategory. A
           structure in a SIBLING category stays invisible and is reported as missing. */
        foreach (['parents', 'children'] as $includes) {
            try {
                foreach (api::list_frameworks('shortname', 'ASC', 0, 0, $this->target, $includes, false) as $framework) {
                    $this->readableframeworks[(int) $framework->get('id')] = $framework;
                }
            } catch (\required_capability_exception $e) {
                $this->frameworksunreadable = true;
            }
        }
    }

    /**
     * Attach each link row to the template row that claims it.
     *
     * @return array Two entries: bykey (template index => link rows) and orphans (the rest).
     */
    protected function group_links(): array {
        $byidnumber = [];
        $byshortname = [];
        foreach (($this->parsed['templates'] ?? []) as $index => $row) {
            $idnumber = (string) $row->templateidnumber;
            if ($idnumber !== '' && !isset($byidnumber[$idnumber])) {
                $byidnumber[$idnumber] = $index;
            }
            $shortname = (string) $row->shortname;
            if ($shortname !== '' && !isset($byshortname[$shortname])) {
                $byshortname[$shortname] = $index;
            }
        }

        $bykey = [];
        $orphans = [];
        $unknown = 0;
        foreach (($this->parsed['links'] ?? []) as $link) {
            if (isset($link->unknownrowtype)) {
                $unknown++;
                continue;
            }
            $parentidnumber = (string) $link->parentidnumber;
            $parentshortname = (string) $link->parentshortname;
            if ($parentidnumber !== '' && isset($byidnumber[$parentidnumber])) {
                $bykey[$byidnumber[$parentidnumber]][] = $link;
            } else if ($parentshortname !== '' && isset($byshortname[$parentshortname])) {
                $bykey[$byshortname[$parentshortname]][] = $link;
            } else {
                $orphans[] = $link;
            }
        }
        if ($unknown > 0) {
            $this->add_notice('unknownrowtype', $unknown);
        }

        return ['bykey' => $bykey, 'orphans' => $orphans];
    }

    /**
     * Project one template row and its links.
     *
     * @param string $itemkey The item key (t<n>).
     * @param int $index The row's index in the parsed template list, which the importer reads
     *     back to find the source row without having to parse the item key.
     * @param \stdClass $row The parsed template row.
     * @param array $linkrows The parsed link rows claimed by this template.
     * @return array
     */
    protected function analyse_template(string $itemkey, int $index, \stdClass $row, array $linkrows): array {
        $shortname = (string) $row->shortname;
        $duedate = $row->duedate === null ? null : template_csv_serializer::parse_duedate((string) $row->duedate);
        $cf = (array) $row->cf;

        $match = $this->resolve_identity($row);
        $links = $this->resolve_links($itemkey, $linkrows, (int) $match['id']);

        $item = [
            'itemkey' => $itemkey,
            'rowindex' => $index,
            'rownumber' => (int) $row->rownumber,
            'shortname' => $shortname,
            'templateidnumber' => (string) $row->templateidnumber,
            'sourcecontext' => (string) $row->sourcecontext,
            'matchedid' => (int) $match['id'],
            'hascustomfielddata' => $this->has_customfield_data((int) $match['id']),
            'matchconfidence' => (string) $match['confidence'],
            'verdict' => template_import_verdict::VERDICT_CREATE,
            'reason' => template_import_verdict::REASON_NONE,
            'detail' => '',
            'remedies' => [],
            'diff' => [],
            'links' => $links,
            'linkstotal' => count($links),
            'linksmatched' => 0,
            'linksunresolved' => 0,
            'blast' => $this->empty_blast(),
            'selectable' => true,
            'preselected' => true,
        ];
        foreach ($links as $link) {
            if (template_import_verdict::link_is_resolved((string) $link['status'])) {
                $item['linksmatched']++;
            } else if (template_import_verdict::link_is_unresolved((string) $link['status'])) {
                $item['linksunresolved']++;
            }
        }

        // The row's own defects come first: they are true whatever it matches.
        $blocked = $this->first_block($row, $shortname, $duedate, $cf, (int) $match['id']);
        if ($blocked !== null) {
            return $this->apply_block($item, $blocked);
        }
        if ($match['conflict'] !== '') {
            return $this->apply_conflict($item, $match);
        }

        if ((int) $match['id'] > 0) {
            $item['diff'] = $this->build_diff((int) $match['id'], $row, $duedate, $cf);
            $item['blast'] = $this->build_blast((int) $match['id'], $links);
            if ($item['blast']['cohorts'] > 0) {
                $this->add_notice('syncqueued');
            }
            $changes = !empty($item['diff']) || $item['blast']['linksadded'] > 0 || $item['blast']['reordered'];
            if (!$changes) {
                $item['verdict'] = template_import_verdict::VERDICT_INSYNC;
                $item['preselected'] = false;
            } else if ($this->updateexisting) {
                $item['verdict'] = template_import_verdict::VERDICT_UPDATE;
            } else {
                $item['verdict'] = template_import_verdict::VERDICT_SKIP;
                $item['reason'] = template_import_verdict::REASON_UPDATEEXISTINGOFF;
                $item['preselected'] = false;
            }
        } else {
            $item['blast']['linksadded'] = $item['linksmatched'];
        }

        // The structure roll-up, last: it can only demote a row the rest of the pass accepted.
        if ($item['linksmatched'] === 0 && $item['linksunresolved'] > 0) {
            return $this->apply_block($item, [
                'reason' => template_import_verdict::REASON_STRUCTUREMISSING,
                'detail' => '',
                'remedies' => [],
            ]);
        }
        if ($item['linksunresolved'] > 0) {
            $item['remedies'] = [$this->remedy(template_import_verdict::REMEDY_PARTIAL, true)];
        }
        if ($duedate !== null && $duedate > 0 && $duedate <= time() - 600) {
            return $this->apply_duedate_block($item, (int) $match['id'], $duedate);
        }

        $item['fingerprint'] = $this->fingerprint($item);
        return $item;
    }

    /**
     * The first defect of the row itself, or null when it has none.
     *
     * @param \stdClass $row The parsed template row.
     * @param string $shortname The parsed short name.
     * @param int|null $duedate The parsed due date: null when absent or unparseable.
     * @param array $cf The row's custom-field cells.
     * @param int $matchedid The matched template id, or 0.
     * @return array|null An array of reason, detail and remedies, or null.
     */
    protected function first_block(\stdClass $row, string $shortname, ?int $duedate, array $cf, int $matchedid): ?array {
        if ($shortname === '') {
            return ['reason' => template_import_verdict::REASON_SHORTNAMEMISSING, 'detail' => '', 'remedies' => []];
        }
        if (\core_text::strlen($shortname) > self::SHORTNAME_MAXLENGTH) {
            return [
                'reason' => template_import_verdict::REASON_SHORTNAMETOOLONG,
                'detail' => '',
                'remedies' => [$this->remedy(template_import_verdict::REMEDY_TRUNCATE, true)],
            ];
        }
        if ($row->duedate !== null && (string) $row->duedate !== '' && $duedate === null) {
            return ['reason' => template_import_verdict::REASON_DUEDATEUNPARSEABLE, 'detail' => '', 'remedies' => []];
        }
        $format = $this->parse_format($row);
        if ($format !== null && !in_array($format, [FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN], true)) {
            return ['reason' => template_import_verdict::REASON_INVALIDFORMAT, 'detail' => '', 'remedies' => []];
        }
        if (!$this->canmanage) {
            return ['reason' => template_import_verdict::REASON_NOCAPABILITY, 'detail' => '', 'remedies' => []];
        }

        $unprovisioned = [];
        $unwritable = [];
        $toolong = [];
        foreach ($cf as $token => $value) {
            if ((string) $value === '' || !isset(self::CF_FIELDS[$token])) {
                continue;
            }
            $shortnameoffield = self::CF_FIELDS[$token];
            $field = $this->fields[$shortnameoffield] ?? null;
            if (!$field) {
                // The SCSS column against a site with the feature off is a notice, not a block:
                // the column is simply dropped, exactly as the export gates it.
                if ($token === 'cf_customscss') {
                    $this->add_notice('scssunavailable');
                    continue;
                }
                $unprovisioned[] = $token;
                continue;
            }
            if (!isset($this->editable[$shortnameoffield])) {
                if ($token === 'cf_customscss') {
                    $this->add_notice('scssdropped');
                    continue;
                }
                $unwritable[] = $token;
                continue;
            }
            $maxlength = (int) $field->get_configdata_property('maxlength');
            if ($maxlength > 0 && \core_text::strlen((string) $value) > $maxlength) {
                $toolong[] = $token;
            }
        }
        if (!empty($unprovisioned)) {
            return [
                'reason' => template_import_verdict::REASON_FIELDNOTPROVISIONED,
                'detail' => implode(', ', $unprovisioned),
                'remedies' => [],
            ];
        }
        if (!empty($unwritable)) {
            return [
                'reason' => template_import_verdict::REASON_FIELDNOTWRITABLE,
                'detail' => implode(', ', $unwritable),
                'remedies' => [],
            ];
        }
        if (!empty($toolong)) {
            return [
                'reason' => template_import_verdict::REASON_CFVALUETOOLONG,
                'detail' => implode(', ', $toolong),
                'remedies' => [],
            ];
        }

        $this->inspect_values($shortname, $row, $cf);

        $errors = $this->validate_with_core($row, $shortname, $matchedid);
        if ($errors !== '') {
            return [
                'reason' => template_import_verdict::REASON_VALIDATIONFAILED,
                'detail' => $errors,
                'remedies' => [],
            ];
        }
        return null;
    }

    /**
     * Ask core whether the projected record is valid, without writing it.
     *
     * persistent::validate() writes nothing. The update path builds the persistent from the
     * stored row FIRST and then applies the new values, because template::before_validate()
     * re-reads its own snapshot from the database and validate_duedate() short-circuits when
     * the new due date equals the stored one.
     *
     * The due date is deliberately left out of this call: a past due date has its own verdict
     * and its own remedies, and would otherwise be reported here as an opaque failure.
     *
     * @param \stdClass $row The parsed template row.
     * @param string $shortname The parsed short name.
     * @param int $matchedid The matched template id, or 0 for a create.
     * @return string The joined error messages, or an empty string when the record is valid.
     */
    protected function validate_with_core(\stdClass $row, string $shortname, int $matchedid): string {
        $values = ['shortname' => $shortname];
        if ($row->description !== null) {
            $values['description'] = (string) $row->description;
        }
        $format = $this->parse_format($row);
        if ($format !== null) {
            $values['descriptionformat'] = $format;
        }
        $visible = $this->parse_visible($row);
        if ($visible !== null) {
            $values['visible'] = $visible;
        }

        try {
            if ($matchedid > 0) {
                $persistent = new template($matchedid);
                $persistent->from_record((object) $values);
            } else {
                $values['contextid'] = (int) $this->target->id;
                $persistent = new template(0, (object) $values);
            }
            $errors = $persistent->get_errors();
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
        unset($errors['duedate']);
        if (empty($errors)) {
            return '';
        }
        return implode(' ', array_map('strval', $errors));
    }

    /**
     * Resolve which template on this site the row is about.
     *
     * @param \stdClass $row The parsed template row.
     * @return array id, confidence, conflict (a REASON_* constant or ''), detail and remedies.
     */
    protected function resolve_identity(\stdClass $row): array {
        $none = [
            'id' => 0,
            'confidence' => template_import_verdict::CONFIDENCE_NONE,
            'conflict' => '',
            'detail' => '',
            'remedies' => [],
        ];
        $idnumber = (string) $row->templateidnumber;

        /* Tier 1 is skipped for an empty ID number rather than run with '': the unique indexes
           permit one empty value per scope, so the lookup would match some other
           empty-ID-number template and report an exact-confidence match for it. */
        if ($idnumber !== '') {
            $here = $this->templates_by_idnumber($idnumber, true);
            if (count($here) === 1) {
                return array_merge($none, [
                    'id' => (int) reset($here),
                    'confidence' => template_import_verdict::CONFIDENCE_EXACT,
                ]);
            }
            if (count($here) > 1) {
                return array_merge($none, ['conflict' => template_import_verdict::REASON_AMBIGUOUS]);
            }
            $elsewhere = $this->templates_by_idnumber($idnumber, false);
            if (!empty($elsewhere)) {
                return array_merge($none, [
                    'conflict' => template_import_verdict::REASON_CONTEXTMISMATCH,
                    'detail' => $this->describe_contexts(array_keys($elsewhere)),
                    'remedies' => [$this->remedy(template_import_verdict::REMEDY_CREATEHERE, true)],
                ]);
            }
        }

        $shortname = (string) $row->shortname;
        if ($shortname === '') {
            return $none;
        }
        /* get_recordS, plural: get_record() with the default IGNORE_MISSING silently returns the
           first of N and emits a debugging() notice, which hides the ambiguity and fails PHPUnit.
           It does not throw dml_multiple_records. */
        $byname = template::get_records(['shortname' => $shortname, 'contextid' => (int) $this->target->id]);
        if (count($byname) > 1) {
            return array_merge($none, ['conflict' => template_import_verdict::REASON_AMBIGUOUS]);
        }
        if (count($byname) === 1) {
            $found = reset($byname);
            if ($idnumber === '') {
                return array_merge($none, [
                    'id' => (int) $found->get('id'),
                    'confidence' => template_import_verdict::CONFIDENCE_TEMPLATESHORTNAME,
                ]);
            }
            /* The file carries an ID number that matched nothing here, yet a template already
               uses the name. Core permits the duplicate (only the hub's own form forbids it),
               so both ways out are offered rather than calling the row unimportable. */
            return array_merge($none, [
                'id' => (int) $found->get('id'),
                'confidence' => template_import_verdict::CONFIDENCE_TEMPLATESHORTNAME,
                'conflict' => template_import_verdict::REASON_SHORTNAMETAKEN,
                'remedies' => [
                    $this->remedy(template_import_verdict::REMEDY_ADOPT, true),
                    $this->remedy(template_import_verdict::REMEDY_CREATEHERE, false),
                ],
            ]);
        }
        return $none;
    }

    /**
     * Template ids carrying an lp-area template ID number, inside or outside the target context.
     *
     * The category join is mandatory: the both-areas custom fields reuse the same shortname in
     * the lp and competency areas, so a bare shortname filter would cross them. d.component,
     * d.area and d.itemid are never named — the plugin's own cross-version queries avoid them.
     *
     * @param string $idnumber The template ID number to look for.
     * @param bool $inside True for templates in the target context, false for those outside it.
     * @return array Template id => context id.
     */
    protected function templates_by_idnumber(string $idnumber, bool $inside): array {
        global $DB;

        $comparison = $DB->sql_equal('d.charvalue', ':idnumber', true, true);
        $operator = $inside ? '=' : '<>';
        $sql = "SELECT t.id, t.contextid
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                  JOIN {competency_template} t ON t.id = d.instanceid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND f.shortname = :shortname
                   AND $comparison
                   AND t.contextid $operator :contextid";
        $params = [
            'component' => 'local_dimensions',
            'area' => helper::AREA_LP,
            'shortname' => constants::CFIELD_TEMPLATE_IDNUMBER,
            'idnumber' => $idnumber,
            'contextid' => (int) $this->target->id,
        ];

        $result = [];
        foreach ($DB->get_records_sql($sql, $params) as $record) {
            $result[(int) $record->id] = (int) $record->contextid;
        }
        return $result;
    }

    /**
     * Whether a template already carries any of this plugin's lp-area custom-field values.
     *
     * The importer passes this to the handler as its "is new instance" flag, so the audit event
     * says which it was. Same category join as the identity lookup, for the same reason.
     *
     * @param int $templateid The template id, or 0 for a row that matches nothing.
     * @return bool
     */
    protected function has_customfield_data(int $templateid): bool {
        global $DB;

        if ($templateid <= 0) {
            return false;
        }
        $sql = "SELECT COUNT(d.id)
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component
                   AND c.area = :area
                   AND d.instanceid = :instanceid";
        return $DB->count_records_sql($sql, [
            'component' => 'local_dimensions',
            'area' => helper::AREA_LP,
            'instanceid' => $templateid,
        ]) > 0;
    }

    /**
     * Name the contexts a set of templates lives in, for the conflict detail line.
     *
     * @param array $templateids Template ids.
     * @return string
     */
    protected function describe_contexts(array $templateids): string {
        $names = [];
        foreach ($templateids as $templateid) {
            $found = template::get_record(['id' => (int) $templateid]);
            if (!$found) {
                continue;
            }
            $names[] = $found->get_context()->get_context_name(false);
        }
        $names = array_unique($names);
        return empty($names) ? '' : get_string(
            'central_plans_import_detail_othercontext',
            'local_dimensions',
            implode(', ', $names)
        );
    }

    /**
     * Resolve every competency link of one template row.
     *
     * @param string $itemkey The parent item key.
     * @param array $linkrows The parsed link rows.
     * @param int $matchedid The matched template id, or 0.
     * @return array Link items keyed by their own item key.
     */
    protected function resolve_links(string $itemkey, array $linkrows, int $matchedid): array {
        $existing = [];
        if ($matchedid > 0) {
            foreach (template_competency::list_competencies($matchedid) as $competency) {
                $existing[(int) $competency->get('id')] = true;
            }
        }

        $links = [];
        foreach (array_values($linkrows) as $offset => $row) {
            $linkkey = $itemkey . 'l' . $offset;
            $links[$linkkey] = $this->resolve_link($linkkey, $row, $existing);
        }
        return $links;
    }

    /**
     * Resolve one competency link row.
     *
     * @param string $linkkey The link item key.
     * @param \stdClass $row The parsed link row.
     * @param array $existing Competency ids already on the matched template, as an isset() map.
     * @return array
     */
    protected function resolve_link(string $linkkey, \stdClass $row, array $existing): array {
        $link = [
            'itemkey' => $linkkey,
            'rownumber' => (int) $row->rownumber,
            'frameworkidnumber' => (string) $row->frameworkidnumber,
            'frameworkshortname' => (string) $row->frameworkshortname,
            'competencyidnumber' => (string) $row->competencyidnumber,
            'competencyshortname' => (string) $row->competencyshortname,
            'sortorder' => (int) $row->sortorder,
            'competencyid' => 0,
            'status' => template_import_verdict::LINK_EMPTYREFERENCE,
            'confidence' => template_import_verdict::CONFIDENCE_NONE,
            'detail' => '',
            'selectable' => false,
            'preselected' => false,
        ];

        if ($link['competencyidnumber'] === '' && $link['competencyshortname'] === '') {
            return $this->label_link($link);
        }

        $framework = $this->resolve_framework($link['frameworkidnumber'], $link['frameworkshortname']);
        if (!$framework) {
            $link['status'] = template_import_verdict::LINK_MISSINGFRAMEWORK;
            $this->record_missing_structure($link['frameworkidnumber'], $link['frameworkshortname']);
            return $this->label_link($link);
        }
        if (!$framework->get('visible')) {
            // Pre-checked because core's add-competency-to-template call raises a coding_exception
            // for a competency whose structure is hidden.
            $link['status'] = template_import_verdict::LINK_HIDDENFRAMEWORK;
            return $this->label_link($link);
        }

        $frameworkid = (int) $framework->get('id');
        $pool = $this->competencies_of($frameworkid);
        $matches = [];
        $confidence = template_import_verdict::CONFIDENCE_NONE;
        if ($link['competencyidnumber'] !== '') {
            $matches = $pool['idnumber'][$link['competencyidnumber']] ?? [];
            $confidence = template_import_verdict::CONFIDENCE_EXACT;
        }
        if (empty($matches) && $link['competencyshortname'] !== '') {
            $matches = $pool['shortname'][$link['competencyshortname']] ?? [];
            $confidence = template_import_verdict::CONFIDENCE_COMPETENCYSHORTNAME;
        }

        if (count($matches) > 1) {
            $link['status'] = template_import_verdict::LINK_AMBIGUOUS;
            $link['detail'] = (string) count($matches);
            return $this->label_link($link);
        }
        if (empty($matches)) {
            $link['status'] = template_import_verdict::LINK_MISSINGCOMPETENCY;
            $link['detail'] = $this->hint_other_frameworks($link['competencyidnumber'], $frameworkid);
            return $this->label_link($link);
        }

        $link['competencyid'] = (int) reset($matches);
        $link['confidence'] = $confidence;
        if (isset($existing[$link['competencyid']])) {
            $link['status'] = template_import_verdict::LINK_ALREADYLINKED;
            return $this->label_link($link);
        }
        $link['status'] = $confidence === template_import_verdict::CONFIDENCE_EXACT
            ? template_import_verdict::LINK_MATCHED
            : template_import_verdict::LINK_MATCHEDFALLBACK;
        $link['selectable'] = true;
        $link['preselected'] = true;
        return $this->label_link($link);
    }

    /**
     * Add the rendered labels a link row carries into the preview.
     *
     * @param array $link The link item.
     * @return array
     */
    protected function label_link(array $link): array {
        $link['statuslabel'] = template_import_verdict::link_status_label((string) $link['status']);
        $link['statusbadge'] = template_import_verdict::link_status_badge((string) $link['status']);
        $link['confidencelabel'] = template_import_verdict::confidence_label((string) $link['confidence']);
        return $link;
    }

    /**
     * Resolve a structure by ID number, then by name among those readable from the target.
     *
     * No cross-framework fallback exists by design: the same ID number in another structure is a
     * different competency, and a silent cross-structure match is exactly the plausible-looking
     * corruption this feature exists to prevent.
     *
     * @param string $idnumber The structure ID number from the row.
     * @param string $shortname The structure name from the row.
     * @return competency_framework|null
     */
    protected function resolve_framework(string $idnumber, string $shortname): ?competency_framework {
        $memokey = $idnumber !== '' ? 'i:' . $idnumber : 's:' . $shortname;
        if (array_key_exists($memokey, $this->frameworkmemo)) {
            return $this->frameworkmemo[$memokey];
        }

        $resolved = null;
        if ($idnumber !== '') {
            $found = competency_framework::get_records(['idnumber' => $idnumber]);
            if (count($found) === 1) {
                $framework = reset($found);
                if (competency_framework::can_read_context($framework->get_context())) {
                    $resolved = $framework;
                } else {
                    $this->add_notice('frameworkunreadable');
                }
            }
        }
        if (!$resolved && $shortname !== '') {
            $named = [];
            foreach ($this->readableframeworks as $framework) {
                if ((string) $framework->get('shortname') === $shortname) {
                    $named[] = $framework;
                }
            }
            if (count($named) === 1) {
                $resolved = $named[0];
            }
        }
        if (!$resolved && $this->frameworksunreadable) {
            $this->add_notice('frameworkunreadable');
        }

        $this->frameworkmemo[$memokey] = $resolved;
        return $resolved;
    }

    /**
     * The competencies of one structure, indexed by ID number and by name.
     *
     * Prefetched per structure rather than queried per link.
     *
     * @param int $frameworkid The structure id.
     * @return array Two maps: idnumber and shortname, each key holding a list of competency ids.
     */
    protected function competencies_of(int $frameworkid): array {
        if (isset($this->competencymemo[$frameworkid])) {
            return $this->competencymemo[$frameworkid];
        }
        $pool = ['idnumber' => [], 'shortname' => []];
        foreach (competency::get_records(['competencyframeworkid' => $frameworkid]) as $competency) {
            $competencyid = (int) $competency->get('id');
            $idnumber = (string) $competency->get('idnumber');
            if ($idnumber !== '') {
                $pool['idnumber'][$idnumber][] = $competencyid;
            }
            $shortname = (string) $competency->get('shortname');
            if ($shortname !== '') {
                $pool['shortname'][$shortname][] = $competencyid;
            }
        }
        $this->competencymemo[$frameworkid] = $pool;
        return $pool;
    }

    /**
     * Report, never act on, other structures where a missing competency's ID number now exists.
     *
     * @param string $idnumber The competency ID number that did not resolve.
     * @param int $frameworkid The structure that was searched.
     * @return string The hint, or an empty string.
     */
    protected function hint_other_frameworks(string $idnumber, int $frameworkid): string {
        if ($idnumber === '') {
            return '';
        }
        $names = [];
        foreach (competency::get_records(['idnumber' => $idnumber]) as $competency) {
            $otherid = (int) $competency->get('competencyframeworkid');
            if ($otherid === $frameworkid) {
                continue;
            }
            $framework = competency_framework::get_record(['id' => $otherid]);
            if ($framework) {
                $names[(string) $framework->get('shortname')] = true;
            }
        }
        if (empty($names)) {
            return '';
        }
        return get_string(
            'central_plans_import_detail_otherframework',
            'local_dimensions',
            implode(', ', array_keys($names))
        );
    }

    /**
     * Remember a structure the file needs and this site does not have.
     *
     * @param string $idnumber The structure ID number from the row.
     * @param string $shortname The structure name from the row.
     * @return void
     */
    protected function record_missing_structure(string $idnumber, string $shortname): void {
        $key = $idnumber !== '' ? 'i:' . $idnumber : 's:' . $shortname;
        if ($key === 's:') {
            return;
        }
        $this->missingstructures[$key] = ['idnumber' => $idnumber, 'shortname' => $shortname];
    }

    /**
     * What would change on a matched template, in the file's own column vocabulary.
     *
     * Three normalisations are mandatory or the diff lies: description is compared through
     * PARAM_CLEANHTML on BOTH sides, because persistent::validate() rewrites it silently and a
     * naive export-import-diff would report a change nobody made; custom-field selects are
     * compared as resolved indexes rather than labels, so a label with no option on this site
     * compares equal to "none" instead of showing a change that will not happen; and the due
     * date is compared as the UTC-reconstructed integer.
     *
     * @param int $templateid The matched template id.
     * @param \stdClass $row The parsed template row.
     * @param int|null $duedate The parsed due date, or null when the column is absent.
     * @param array $cf The row's custom-field cells.
     * @return array Each entry has field, from and to.
     */
    protected function build_diff(int $templateid, \stdClass $row, ?int $duedate, array $cf): array {
        $stored = template::get_record(['id' => $templateid]);
        if (!$stored) {
            return [];
        }

        $diff = [];
        if ((string) $row->shortname !== (string) $stored->get('shortname')) {
            $diff[] = ['field' => 'shortname', 'from' => (string) $stored->get('shortname'), 'to' => (string) $row->shortname];
        }
        if ($row->description !== null) {
            $to = \clean_param((string) $row->description, PARAM_CLEANHTML);
            $from = \clean_param((string) $stored->get('description'), PARAM_CLEANHTML);
            if ($to !== $from) {
                $diff[] = ['field' => 'description', 'from' => $from, 'to' => $to];
            }
        }
        $format = $this->parse_format($row);
        if ($format !== null && $format !== (int) $stored->get('descriptionformat')) {
            $diff[] = [
                'field' => 'descriptionformat',
                'from' => (string) (int) $stored->get('descriptionformat'),
                'to' => (string) $format,
            ];
        }
        $visible = $this->parse_visible($row);
        if ($visible !== null && $visible !== (bool) $stored->get('visible')) {
            $diff[] = [
                'field' => 'visible',
                'from' => $stored->get('visible') ? '1' : '0',
                'to' => $visible ? '1' : '0',
            ];
        }
        if ($duedate !== null && $duedate !== (int) $stored->get('duedate')) {
            $diff[] = [
                'field' => 'duedate',
                'from' => $this->describe_duedate((int) $stored->get('duedate')),
                'to' => $this->describe_duedate($duedate),
            ];
        }

        /* Both sides go through the same encoder, so both are in the stored representation:
           an index for every select, a string for every text field. */
        $storedtokens = helper::export_template_customfields($templateid);
        $storeddata = helper::template_customfields_to_formdata($storedtokens);
        $filedata = helper::template_customfields_to_formdata($cf);
        foreach ($cf as $token => $value) {
            if (!isset(self::CF_FIELDS[$token])) {
                continue;
            }
            $key = 'customfield_' . self::CF_FIELDS[$token];
            $editorkey = $key . '_editor';
            $formkey = array_key_exists($editorkey, $filedata) ? $editorkey : $key;
            if (!array_key_exists($formkey, $filedata)) {
                continue;
            }
            $to = $this->flatten_formvalue($filedata[$formkey]);
            $from = $this->flatten_formvalue($storeddata[$formkey] ?? '');
            if ($to !== $from) {
                $diff[] = [
                    'field' => $token,
                    'from' => (string) ($storedtokens[$token] ?? ''),
                    'to' => (string) $value,
                ];
            }
        }
        return $diff;
    }

    /**
     * Reduce one custom-field form value to a comparable scalar.
     *
     * @param mixed $value An index, a string, or an editor array.
     * @return string
     */
    protected function flatten_formvalue($value): string {
        if (is_array($value)) {
            return (string) ($value['text'] ?? '');
        }
        return (string) $value;
    }

    /**
     * What applying an update would do to the learner plans already built from the template.
     *
     * Draft and active plans read the template live, while complete plans are frozen against
     * user_competency_plan, so the two are counted separately: only the open ones are renamed
     * by the raw bulk UPDATE core's template update runs, and only they gain the new competencies.
     *
     * @param int $templateid The matched template id.
     * @param array $links The resolved link items.
     * @return array
     */
    protected function build_blast(int $templateid, array $links): array {
        $blast = $this->empty_blast();
        $open = helper::count_open_plans_by_template([$templateid])[$templateid] ?? 0;
        $all = helper::count_plans_by_template([$templateid])[$templateid] ?? 0;
        $blast['openplans'] = (int) $open;
        $blast['frozenplans'] = max(0, (int) $all - (int) $open);
        $blast['cohorts'] = (int) (helper::count_cohorts_by_template([$templateid])[$templateid] ?? 0);

        $fileids = [];
        foreach ($links as $link) {
            if ((int) $link['competencyid'] > 0 && template_import_verdict::link_is_resolved((string) $link['status'])) {
                $fileids[] = (int) $link['competencyid'];
            }
            if ($link['status'] === template_import_verdict::LINK_MATCHED
                    || $link['status'] === template_import_verdict::LINK_MATCHEDFALLBACK) {
                $blast['linksadded']++;
            }
        }

        $current = [];
        foreach (template_competency::list_competencies($templateid) as $competency) {
            $current[] = (int) $competency->get('id');
        }
        $kept = array_values(array_diff($current, $fileids));
        $blast['linkskept'] = count($kept);
        // Nothing is ever removed, so the apply renumbers the whole final set: the file's links
        // first, then the kept extras. Any difference from the current order is a reorder.
        $blast['reordered'] = array_merge($fileids, $kept) !== $current;
        return $blast;
    }

    /**
     * The zeroed blast-radius structure.
     *
     * @return array
     */
    protected function empty_blast(): array {
        return [
            'openplans' => 0,
            'frozenplans' => 0,
            'cohorts' => 0,
            'linksadded' => 0,
            'linkskept' => 0,
            'reordered' => false,
        ];
    }

    /**
     * Turn an item into a blocked one, keeping everything already resolved on it.
     *
     * @param array $item The item so far.
     * @param array $block The reason, detail and remedies.
     * @return array
     */
    protected function apply_block(array $item, array $block): array {
        $item['verdict'] = template_import_verdict::VERDICT_BLOCKED;
        $item['reason'] = $block['reason'];
        $item['detail'] = (string) $block['detail'];
        $item['remedies'] = $block['remedies'];
        // A block with a remedy is still applicable: the remedy is what makes it applicable.
        $item['selectable'] = !empty($block['remedies']);
        $item['preselected'] = false;
        $item['fingerprint'] = $this->fingerprint($item);
        return $item;
    }

    /**
     * Turn an item into a conflicting one.
     *
     * @param array $item The item so far.
     * @param array $match The identity resolution.
     * @return array
     */
    protected function apply_conflict(array $item, array $match): array {
        $item['verdict'] = template_import_verdict::VERDICT_CONFLICT;
        $item['reason'] = $match['conflict'];
        $item['detail'] = (string) $match['detail'];
        $item['remedies'] = $match['remedies'];
        $item['selectable'] = !empty($match['remedies']);
        $item['preselected'] = false;
        $item['fingerprint'] = $this->fingerprint($item);
        return $item;
    }

    /**
     * Block a row on its past due date, offering the ways out.
     *
     * keepduedate is offered only against a matched template, where it means "do not send a due
     * date at all": what stays stored is then byte-identical to what is there now, which is the
     * case template::validate_duedate() short-circuits as always valid.
     *
     * @param array $item The item so far.
     * @param int $matchedid The matched template id, or 0.
     * @param int $duedate The parsed, past due date.
     * @return array
     */
    protected function apply_duedate_block(array $item, int $matchedid, int $duedate): array {
        if ($matchedid > 0) {
            $stored = template::get_record(['id' => $matchedid]);
            if ($stored && (int) $stored->get('duedate') === $duedate) {
                // Unchanged: core accepts it, so there is nothing to block on.
                $item['fingerprint'] = $this->fingerprint($item);
                return $item;
            }
        }
        $remedies = [
            $this->remedy(template_import_verdict::REMEDY_CLEARDUEDATE, true),
            $this->remedy(template_import_verdict::REMEDY_SHIFTDUEDATE, false),
        ];
        if ($matchedid > 0) {
            $remedies[] = $this->remedy(template_import_verdict::REMEDY_KEEPDUEDATE, false);
        }
        return $this->apply_block($item, [
            'reason' => template_import_verdict::REASON_DUEDATEPAST,
            'detail' => $this->describe_duedate($duedate),
            'remedies' => $remedies,
        ]);
    }

    /**
     * An item for a competency row whose parent key names no template row in the file.
     *
     * @param string $itemkey The item key.
     * @param \stdClass $row The parsed link row.
     * @return array
     */
    protected function orphan_item(string $itemkey, \stdClass $row): array {
        $item = [
            'itemkey' => $itemkey,
            'rowindex' => -1,
            'rownumber' => (int) $row->rownumber,
            'shortname' => (string) $row->parentshortname,
            'templateidnumber' => (string) $row->parentidnumber,
            'sourcecontext' => '',
            'matchedid' => 0,
            'hascustomfielddata' => false,
            'matchconfidence' => template_import_verdict::CONFIDENCE_NONE,
            'verdict' => template_import_verdict::VERDICT_ORPHANLINK,
            'reason' => template_import_verdict::REASON_NOPARENT,
            'detail' => (string) $row->competencyidnumber,
            'remedies' => [],
            'diff' => [],
            'links' => [],
            'linkstotal' => 0,
            'linksmatched' => 0,
            'linksunresolved' => 0,
            'blast' => $this->empty_blast(),
            'selectable' => false,
            'preselected' => false,
        ];
        $item['fingerprint'] = $this->fingerprint($item);
        return $item;
    }

    /**
     * A remedy entry, as the preview renders it.
     *
     * @param string $remedy One of the REMEDY_* constants.
     * @param bool $selected Whether it is pre-selected.
     * @return array
     */
    protected function remedy(string $remedy, bool $selected): array {
        return [
            'remedy' => $remedy,
            'label' => template_import_verdict::remedy_label($remedy),
            'selected' => $selected,
        ];
    }

    /**
     * Add the rendered labels an item carries into the preview.
     *
     * @param array $item The item.
     * @return array
     */
    protected function label_item(array $item): array {
        $item['verdictlabel'] = template_import_verdict::verdict_label((string) $item['verdict']);
        $item['verdictbadge'] = template_import_verdict::verdict_badge((string) $item['verdict']);
        $item['verdicthelp'] = template_import_verdict::verdict_help((string) $item['verdict']);
        $item['reasonlabel'] = template_import_verdict::reason_label((string) $item['reason']);
        return $item;
    }

    /**
     * The per-item fingerprint the apply step re-checks.
     *
     * The verdict alone is not enough: "the competency I was going to link got deleted" and
     * "another admin edited this template's description" both leave the verdict on update while
     * changing what would happen.
     *
     * @param array $item The item, before its labels are added.
     * @return string
     */
    protected function fingerprint(array $item): string {
        $competencyids = [];
        $existing = [];
        foreach ($item['links'] as $link) {
            if ((int) $link['competencyid'] > 0) {
                $competencyids[] = (int) $link['competencyid'];
                if ($link['status'] === template_import_verdict::LINK_ALREADYLINKED) {
                    $existing[] = (int) $link['competencyid'];
                }
            }
        }
        sort($existing);
        return sha1(json_encode([
            $item['verdict'],
            $item['reason'],
            (int) $item['matchedid'],
            $item['diff'],
            $competencyids,
            $existing,
        ]));
    }

    /**
     * The descriptionformat cell as an int, or null when it is absent or empty.
     *
     * An empty cell is treated as "not specified" rather than as 0: 0 is FORMAT_MOODLE, a real
     * choice, and silently turning a hand-authored file's HTML descriptions into auto-formatted
     * ones is exactly the invisible change this preview exists to prevent.
     *
     * @param \stdClass $row The parsed template row.
     * @return int|null
     */
    protected function parse_format(\stdClass $row): ?int {
        if ($row->descriptionformat === null || trim((string) $row->descriptionformat) === '') {
            return null;
        }
        return (int) $row->descriptionformat;
    }

    /**
     * The visible cell as a bool, or null when it is absent or empty.
     *
     * Empty is "not specified" for the same reason as descriptionformat: an empty cell would
     * otherwise create hidden templates, which generate no learner plans at all.
     *
     * @param \stdClass $row The parsed template row.
     * @return bool|null
     */
    protected function parse_visible(\stdClass $row): ?bool {
        if ($row->visible === null || trim((string) $row->visible) === '') {
            return null;
        }
        return (bool) (int) $row->visible;
    }

    /**
     * A due date rendered for the preview, in the site's own format.
     *
     * @param int $duedate Unix timestamp, or 0.
     * @return string
     */
    protected function describe_duedate(int $duedate): string {
        return $duedate > 0 ? userdate($duedate, get_string('strftimedatetimeshort', 'langconfig')) : '';
    }

    /**
     * Inspect the row's values for everything that is worth reporting but does not block.
     *
     * @param string $shortname The parsed short name.
     * @param \stdClass $row The parsed template row.
     * @param array $cf The row's custom-field cells.
     * @return void
     */
    protected function inspect_values(string $shortname, \stdClass $row, array $cf): void {
        $body = $shortname . ' ' . (string) $row->description . ' ' . implode(' ', array_map('strval', $cf));
        if (strpos($body, "\u{FFFD}") !== false) {
            $this->add_notice('replacementchar');
        }
        if (strpos($body, '@@PLUGINFILE@@') !== false) {
            $this->add_notice('pluginfile');
        }
        if ($this->parse_visible($row) === false) {
            $this->add_notice('hiddentemplates');
        }

        foreach (['cf_bgcolor', 'cf_textcolor'] as $token) {
            $value = (string) ($cf[$token] ?? '');
            if ($value !== '' && helper::normalise_hex_color($value) === '') {
                $this->add_notice('invalidcolor', null, $value);
            }
        }
        foreach (['cf_tag1', 'cf_tag2', 'cf_type'] as $token) {
            $value = (string) ($cf[$token] ?? '');
            $field = $this->fields[self::CF_FIELDS[$token]] ?? null;
            if ($value === '' || !$field) {
                continue;
            }
            if (!in_array($value, helper::select_raw_options($field), true)) {
                $this->add_notice('unknownoption', null, $value);
            }
        }
        foreach (['cf_enrollmentfilter', 'cf_singlecourseredirect', 'cf_lockedcardmode', 'cf_showlockeddate'] as $token) {
            if ((string) ($cf[$token] ?? '') === 'inherit') {
                $this->add_notice('inheritvalues');
            }
        }
        $scss = (string) ($cf['cf_customscss'] ?? '');
        if ($scss !== '' && scss_manager::validate_scss($scss) !== true) {
            $this->add_notice('invalidscss', null, $shortname);
        }
    }

    /**
     * Add the notices that describe the file as a whole rather than one of its rows.
     *
     * @return void
     */
    protected function collect_static_notices(): void {
        if (!empty($this->parsed['legacy'])) {
            $this->add_notice('legacyformat');
        }
        if (!empty($this->parsed['templates'])) {
            $this->add_notice('imagesnotcarried');
            $this->add_notice('cohortsnotcarried');
        }
        ksort($this->notices);
    }

    /**
     * Record a file-level notice once, optionally with a count or a collected value list.
     *
     * @param string $key The notice key, matching a central_plans_import_notice_* string.
     * @param int|null $count A count interpolated into the message.
     * @param string|null $value A value to collect into the message's list.
     * @return void
     */
    protected function add_notice(string $key, ?int $count = null, ?string $value = null): void {
        $argument = $count;
        if ($value !== null) {
            $this->noticevalues[$key][$value] = true;
            $argument = implode(', ', array_keys($this->noticevalues[$key]));
        }
        $this->notices[$key] = ['key' => $key, 'message' => $this->notice_message($key, $argument)];
    }

    /**
     * The message of one notice, resolved through a literal match on its key.
     *
     * @param string $key The notice key.
     * @param mixed $argument The count or value list interpolated into the message.
     * @return string
     */
    protected function notice_message(string $key, $argument): string {
        $stringkey = match ($key) {
            'legacyformat' => 'central_plans_import_notice_legacyformat',
            'unknownrowtype' => 'central_plans_import_notice_unknownrowtype',
            'imagesnotcarried' => 'central_plans_import_notice_imagesnotcarried',
            'cohortsnotcarried' => 'central_plans_import_notice_cohortsnotcarried',
            'inheritvalues' => 'central_plans_import_notice_inheritvalues',
            'scssdropped' => 'central_plans_import_notice_scssdropped',
            'scssunavailable' => 'central_plans_import_notice_scssunavailable',
            'invalidcolor' => 'central_plans_import_notice_invalidcolor',
            'invalidscss' => 'central_plans_import_notice_invalidscss',
            'unknownoption' => 'central_plans_import_notice_unknownoption',
            'replacementchar' => 'central_plans_import_notice_replacementchar',
            'pluginfile' => 'central_plans_import_notice_pluginfile',
            'hiddentemplates' => 'central_plans_import_notice_hiddentemplates',
            'frameworkunreadable' => 'central_plans_import_notice_frameworkunreadable',
            'syncqueued' => 'central_plans_import_notice_syncqueued',
            default => '',
        };
        if ($stringkey === '') {
            return '';
        }
        return get_string($stringkey, 'local_dimensions', $argument);
    }
}
