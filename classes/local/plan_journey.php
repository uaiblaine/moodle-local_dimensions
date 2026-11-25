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
 * The plan overview's Journey: the plan's evidence over time, one page at a time.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

use core_competency\evidence;
use core_competency\plan;

/**
 * Reads a page of the Journey view: what happened in a plan, newest first, for a viewer core lets read it.
 *
 * The decisions, in order, each before anything is read about the owner:
 * - the plan is read through core (plan_access::read_plan(), api::read_plan(): the viewer may read it);
 * - the viewer may read the owner's user competencies (plan_access::require_owner_readable()), the rule the
 *   overview's candetail applies (view_plan_summary_page) and the one core's own competency-in-plan summary
 *   asks before listing a competency's evidence (api::list_evidence());
 * - the competencies are the plan's as the overview lists them (plan::get_competencies(), the source of
 *   api::list_plan_competencies(): the archive for a completed plan, the template's or the plan's own
 *   otherwise), never an id from the request.
 *
 * Every evidence of those competencies is the plan's journey, whenever it was recorded: core's plan page
 * lists a competency's whole evidence inside a plan, before the plan began included. A completed plan stops
 * where core's list stops, at the plan's last modification (api::list_evidence()).
 *
 * The records come from one statement, keyset-paged on (timecreated, id), with the competency names, the
 * rating scales and the acting users looked up in bulk, so a page costs the same number of statements
 * whatever it holds. A log written by another component than core_competency (the evidence hub's validity
 * log, in volume) is not listed, and takes none of the page's places: a second statement counts those logs
 * between the page's edges into one line per month (see folds()).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_journey {
    /** @var int Records a page reads, listed and folded together. */
    public const int PAGE_SIZE = 50;

    /** @var int Calendar months a page's quiet lines are counted by; everything older is one remainder line. */
    public const int FOLD_MONTHS = 24;

    /** @var string The component of the evidence core itself records. */
    public const string CORE_COMPONENT = 'core_competency';

    /**
     * One page of a plan's journey, for the current user.
     *
     * @param int $planid The plan id from the request.
     * @param int $beforetime The cursor's time: the page starts after the record it names; 0 for the first page.
     * @param int $beforeid The cursor's evidence id, with $beforetime; 0 for the first page.
     * @param int $limit Records to read; tests read short pages, the service always the page size.
     * @return array Keys: entries, folds (see entry() and the fold below), hasmore, nexttime, nextid (the cursor
     *     of the next page, 0 when there is none), planstart (the plan's own start, as a marker).
     * @throws \invalid_parameter_exception When the cursor is not two positive parts or two zeros.
     * @throws \moodle_exception 'invalidplan' when no plan has that id; core's own errors otherwise.
     * @throws \required_capability_exception When the viewer may not read the plan or its owner's competencies.
     */
    public static function page(int $planid, int $beforetime, int $beforeid, int $limit = self::PAGE_SIZE): array {
        global $DB;

        self::require_cursor($beforetime, $beforeid);
        $plan = plan_access::read_plan($planid);
        $ownerid = plan_access::require_owner_readable($plan);

        $competencies = [];
        foreach ($plan->get_competencies() as $competency) {
            $competencies[(int) $competency->get('id')] = $competency;
        }

        $result = [
            'entries' => [],
            'folds' => [],
            'hasmore' => false,
            'nexttime' => 0,
            'nextid' => 0,
            'planstart' => self::plan_start($plan),
        ];
        if (!$competencies) {
            return $result;
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($competencies), SQL_PARAMS_NAMED, 'comp');
        $params['userid'] = $ownerid;
        $where = "uc.userid = :userid AND uc.competencyid {$insql}";
        // The page's upper edge in time: the cursor, else where the list begins (core's stop for a completed plan, now).
        $upper = $beforetime;
        if ((int) $plan->get('status') === plan::STATUS_COMPLETE) {
            // Where core's api::list_evidence() stops for a completed plan.
            $where .= ' AND e.timecreated <= :timecompleted';
            $params['timecompleted'] = (int) $plan->get('timemodified');
            $upper = $upper ?: (int) $params['timecompleted'];
        }
        $upper = $upper ?: \core\di::get(\core\clock::class)->time();
        if ($beforetime > 0) {
            // Strictly after the cursor's record in (timecreated DESC, id DESC): each record once, ties split by id.
            $where .= ' AND (e.timecreated < :beforetime OR (e.timecreated = :beforetimeeq AND e.id < :beforeid))';
            $params += ['beforetime' => $beforetime, 'beforetimeeq' => $beforetime, 'beforeid' => $beforeid];
        }

        // Statement one: the records the reader sees. The folded rows (see folds()) are left out here, in SQL,
        // so a page is a full page of listed records however many log rows another component wrote.
        $ctxfields = \context_helper::get_preload_record_columns_sql('ctx');
        $sql = "SELECT e.id, e.contextid, e.action, e.actionuserid, e.descidentifier, e.desccomponent, e.desca,
                       e.url, e.grade, e.note, e.timecreated, uc.competencyid, {$ctxfields}
                  FROM {competency_evidence} e
                  JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid
             LEFT JOIN {context} ctx ON ctx.id = e.contextid
                 WHERE {$where} AND (e.desccomponent = :corecomp OR e.action <> :actionlog)
              ORDER BY e.timecreated DESC, e.id DESC";
        $listparams = $params + ['corecomp' => self::CORE_COMPONENT, 'actionlog' => evidence::ACTION_LOG];
        $listed = array_values($DB->get_records_sql($sql, $listparams, 0, $limit + 1));

        $last = null;
        if (count($listed) > $limit) {
            $listed = array_slice($listed, 0, $limit);
            $last = end($listed);
            $result['hasmore'] = true;
            $result['nexttime'] = (int) $last->timecreated;
            $result['nextid'] = (int) $last->id;
        }
        foreach ($listed as $row) {
            \context_helper::preload_from_record($row);
        }

        $result['folds'] = self::folds($where, $params, $upper, $last);

        $scales = self::scales($listed, $competencies);
        $users = self::acting_users($listed);
        // The framework read behind a competency's context is made once per competency, not once per record.
        $contexts = [];
        foreach ($listed as $row) {
            $id = (int) $row->competencyid;
            $contexts[$id] ??= $competencies[$id]->get_context();
            $result['entries'][] = self::entry($row, $competencies[$id], $contexts[$id], $scales, $users);
        }
        return $result;
    }

    /**
     * Refuse a cursor that is not both parts positive, or both zero for the first page.
     *
     * @param int $beforetime The cursor's time.
     * @param int $beforeid The cursor's evidence id.
     * @return void
     * @throws \invalid_parameter_exception When it is anything else.
     */
    private static function require_cursor(int $beforetime, int $beforeid): void {
        $first = $beforetime === 0 && $beforeid === 0;
        $next = $beforetime > 0 && $beforeid > 0;
        if (!$first && !$next) {
            throw new \invalid_parameter_exception('The cursor is two positive numbers, or none.');
        }
    }

    /**
     * The quiet lines of a page: the other components' logs between the page's edges, counted per month.
     *
     * The folded rows are a log (action ACTION_LOG) written by another component than core: the evidence hub
     * writes its validity log this way, in volume. A record of another component that rates or completes a
     * competency is not folded: it changed the learner's state, and is listed. This is the negation of the
     * condition page() puts in the listing's WHERE.
     *
     * ONE aggregate whose cost does not depend on how many rows it counts: a SUM per month window, at most
     * FOLD_MONTHS windows from the page's upper edge, then one remainder for everything older, so nothing is
     * left uncounted. The page's edges are those of its listed records: below by the cursor (inside $scope),
     * above by the last listed record. A folded row that shares the last listed record's time is counted HERE
     * when its id is the higher (it sorts before that record) and on the next page when it is the lower: the
     * next page's cursor is that record, and it takes what sorts strictly after it, so each row is counted once.
     * On the last page (no $last) there is no lower edge.
     *
     * @param string $scope The WHERE of the listing without its folding rule: the owner, the competencies, the
     *     completed plan's stop and the cursor.
     * @param array $params Its named parameters.
     * @param int $upper The time the windows start from: the cursor's, or the list's first edge.
     * @param \stdClass|null $last The page's last listed record when an older page exists, else null.
     * @return array The folds, newest first: monthkey, monthlabel, count, timecreated (the window's start, 0 for the
     *     remainder), older (whether it is the remainder, one line for everything older than the windows).
     */
    private static function folds(string $scope, array $params, int $upper, ?\stdClass $last): array {
        global $DB;

        $zone = \core_date::get_user_timezone_object();
        $month = (new \DateTimeImmutable('@' . $upper))->setTimezone($zone)->modify('first day of this month')->setTime(0, 0);
        $lowtime = $last ? (int) $last->timecreated : null;

        $starts = [];
        $selects = [];
        $reached = false;
        for ($i = 0; $i < self::FOLD_MONTHS; $i++) {
            $start = $month->getTimestamp();
            $window = "e.timecreated >= :lo{$i}";
            $params["lo{$i}"] = $start;
            if ($i > 0) {
                // The first window is open above: the scope already holds the page's upper edge.
                $window .= " AND e.timecreated < :hi{$i}";
                $params["hi{$i}"] = $starts[$i - 1];
            }
            $selects[] = "SUM(CASE WHEN {$window} THEN 1 ELSE 0 END) AS b{$i}";
            $starts[$i] = $start;
            if ($lowtime !== null && $start <= $lowtime) {
                $reached = true;
                break;
            }
            $month = $month->modify('first day of previous month');
        }
        if (!$reached) {
            $selects[] = 'SUM(CASE WHEN e.timecreated < :lorem THEN 1 ELSE 0 END) AS bolder';
            $params['lorem'] = end($starts);
        }

        $lower = '';
        if ($last) {
            $lower = ' AND (e.timecreated > :lowtime OR (e.timecreated = :lowtimeeq AND e.id > :lowid))';
            $params += ['lowtime' => $lowtime, 'lowtimeeq' => $lowtime, 'lowid' => (int) $last->id];
        }
        $params += ['corecomp' => self::CORE_COMPONENT, 'actionlog' => evidence::ACTION_LOG];
        $columns = implode(', ', $selects);
        $sql = "SELECT e.desccomponent, {$columns}
                  FROM {competency_evidence} e
                  JOIN {competency_usercomp} uc ON uc.id = e.usercompetencyid
                 WHERE {$scope}{$lower} AND e.desccomponent <> :corecomp AND e.action = :actionlog
              GROUP BY e.desccomponent";

        $totals = array_fill(0, count($starts), 0);
        $older = 0;
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            foreach (array_keys($starts) as $i) {
                $totals[$i] += (int) $row->{"b{$i}"};
            }
            $older += (int) ($row->bolder ?? 0);
        }

        $folds = [];
        foreach ($totals as $i => $count) {
            if ($count > 0) {
                $folds[] = [
                    'monthkey' => self::month_key($starts[$i]),
                    'monthlabel' => self::month_label($starts[$i]),
                    'count' => $count,
                    'timecreated' => $starts[$i],
                    'older' => false,
                ];
            }
        }
        if ($older > 0) {
            $folds[] = [
                'monthkey' => 'older',
                'monthlabel' => get_string('journey_older', 'local_dimensions'),
                'count' => $older,
                'timecreated' => 0,
                'older' => true,
            ];
        }
        return $folds;
    }

    /**
     * The rating scales' items of the competencies a page's rated records belong to, by competency id.
     *
     * A competency without a scale of its own takes its framework's, as competency::get_scale() does. Two
     * statements, only when the page holds a rating.
     *
     * @param array $rows The listed evidence rows.
     * @param array $competencies The plan's competencies, by id.
     * @return array Competency id => scale items (trimmed, as grade_scale::load_items() trims them).
     */
    private static function scales(array $rows, array $competencies): array {
        global $DB;

        $rated = [];
        foreach ($rows as $row) {
            if ($row->grade !== null) {
                $rated[(int) $row->competencyid] = $competencies[(int) $row->competencyid];
            }
        }
        if (!$rated) {
            return [];
        }

        $frameworkids = [];
        foreach ($rated as $competency) {
            if ($competency->get('scaleid') === null) {
                $frameworkids[(int) $competency->get('competencyframeworkid')] = true;
            }
        }
        $frameworkscales = $frameworkids
            ? $DB->get_records_list('competency_framework', 'id', array_keys($frameworkids), '', 'id, scaleid')
            : [];

        $scaleids = [];
        foreach ($rated as $id => $competency) {
            $scaleid = $competency->get('scaleid');
            if ($scaleid === null) {
                $framework = $frameworkscales[(int) $competency->get('competencyframeworkid')] ?? null;
                $scaleid = $framework ? $framework->scaleid : null;
            }
            $scaleids[$id] = (int) $scaleid;
        }
        $scales = $DB->get_records_list('scale', 'id', array_unique(array_values($scaleids)), '', 'id, scale');

        $items = [];
        foreach ($scaleids as $id => $scaleid) {
            $items[$id] = isset($scales[$scaleid]) ? array_map('trim', explode(',', (string) $scales[$scaleid]->scale)) : [];
        }
        return $items;
    }

    /**
     * The users who acted on a page's records, in one statement.
     *
     * @param array $rows The listed evidence rows.
     * @return array User id => user record with the name fields.
     */
    private static function acting_users(array $rows): array {
        global $DB;

        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row->actionuserid)) {
                $ids[(int) $row->actionuserid] = true;
            }
        }
        if (!$ids) {
            return [];
        }
        $fields = 'id, ' . implode(', ', \core_user\fields::get_name_fields());
        return $DB->get_records_list('user', 'id', array_keys($ids), '', $fields);
    }

    /**
     * One record as the reader sees it: what core's evidence_exporter shows and nothing else.
     *
     * Core's description of what happened, the competency's name, the rating's name on the competency's scale,
     * the acting user's full name, the note and the address, the last only when it is http(s). Every text is
     * plain (the client writes it as text) and is its own PARAM_TEXT cleaning, so the return's check passes it.
     *
     * @param \stdClass $row The evidence row, its context preloaded.
     * @param \core_competency\competency $competency The plan's competency it belongs to.
     * @param \context $competencycontext The competency's context (its framework's), shared by its records.
     * @param array $scales Competency id => scale items.
     * @param array $users User id => user record.
     * @return array The entry (the service's entry structure).
     */
    private static function entry(
        \stdClass $row,
        \core_competency\competency $competency,
        \context $competencycontext,
        array $scales,
        array $users
    ): array {
        $context = \context::instance_by_id((int) $row->contextid, IGNORE_MISSING) ?: \context_system::instance();
        $time = (int) $row->timecreated;

        $rating = '';
        if ($row->grade !== null) {
            $items = $scales[(int) $row->competencyid] ?? [];
            // The scale's name for the grade, formatted as core's exporter formats gradename.
            $rating = self::plain((string) ($items[(int) $row->grade - 1] ?? ''), \context_system::instance());
        }

        $actor = '';
        if (!empty($row->actionuserid) && isset($users[(int) $row->actionuserid])) {
            $name = self::plain(fullname($users[(int) $row->actionuserid]), \context_system::instance());
            $actor = get_string('journey_by', 'local_dimensions', $name);
        }

        $note = self::plain((string) $row->note, $context);
        $url = self::web_url((string) $row->url);

        return [
            'id' => (int) $row->id,
            'timecreated' => $time,
            'datetime' => self::iso($time),
            'monthkey' => self::month_key($time),
            'monthlabel' => self::month_label($time),
            'daylabel' => userdate($time, get_string('strftimedateshort', 'langconfig')),
            'competencyid' => (int) $row->competencyid,
            'competencyname' => self::plain((string) $competency->get('shortname'), $competencycontext),
            'description' => self::plain(self::description($row), $context),
            'hasrating' => $rating !== '',
            'rating' => $rating,
            'hasactor' => $actor !== '',
            'actor' => $actor,
            'hasnote' => $note !== '',
            'note' => $note,
            'hasurl' => $url !== '',
            'url' => $url,
        ];
    }

    /**
     * Core's description of what happened, from the record's string identifier, component and parameter.
     *
     * evidence::get_description() builds the same lang_string; a string its component no longer has (the
     * component was uninstalled) reads as no description rather than an error over the whole page.
     *
     * @param \stdClass $row The evidence row.
     * @return string
     */
    private static function description(\stdClass $row): string {
        $identifier = (string) $row->descidentifier;
        $component = (string) $row->desccomponent;
        if (!get_string_manager()->string_exists($identifier, $component)) {
            return '';
        }
        $a = $row->desca === null ? null : json_decode((string) $row->desca);
        return get_string($identifier, $component, $a);
    }

    /**
     * A text as the client writes it: filtered and plain, and unchanged by PARAM_TEXT's cleaning.
     *
     * @param string $text The stored text.
     * @param \context $context The context its filters run in.
     * @return string
     */
    private static function plain(string $text, \context $context): string {
        if ($text === '') {
            return '';
        }
        return trim(clean_param(format_string($text, true, ['context' => $context, 'escape' => false]), PARAM_TEXT));
    }

    /**
     * An address the client may link to: http or https, else none.
     *
     * @param string $url The stored address.
     * @return string
     */
    private static function web_url(string $url): string {
        $clean = clean_param($url, PARAM_URL);
        $scheme = strtolower((string) parse_url($clean, PHP_URL_SCHEME));
        return ($clean !== '' && in_array($scheme, ['http', 'https'], true)) ? $clean : '';
    }

    /**
     * The plan's own start, drawn as the oldest marker of the journey once the reader reaches it.
     *
     * Core records no other milestone of a plan outside the log store (status changes, completion), and the log
     * store is not a data source: the plan's creation time is the one milestone its row holds.
     *
     * @param plan $plan The plan.
     * @return array Keys: timecreated, datetime, monthkey, monthlabel, daylabel, label.
     */
    private static function plan_start(plan $plan): array {
        $time = (int) $plan->get('timecreated');
        return [
            'timecreated' => $time,
            'datetime' => self::iso($time),
            'monthkey' => self::month_key($time),
            'monthlabel' => self::month_label($time),
            'daylabel' => userdate($time, get_string('strftimedateshort', 'langconfig')),
            'label' => get_string('journey_planstarted', 'local_dimensions'),
        ];
    }

    /**
     * The month a time falls in for the reader, as YYYY-MM: the key the client groups by.
     *
     * @param int $time A timestamp.
     * @return string
     */
    private static function month_key(int $time): string {
        return (new \DateTimeImmutable('@' . $time))->setTimezone(\core_date::get_user_timezone_object())->format('Y-m');
    }

    /**
     * The month a time falls in, named in the reader's language ("October 2026").
     *
     * @param int $time A timestamp.
     * @return string
     */
    private static function month_label(int $time): string {
        return userdate($time, get_string('strftimemonthyear', 'langconfig'));
    }

    /**
     * A time as the datetime attribute of a time element, in the reader's zone.
     *
     * @param int $time A timestamp.
     * @return string
     */
    private static function iso(int $time): string {
        return (new \DateTimeImmutable('@' . $time))->setTimezone(\core_date::get_user_timezone_object())->format('c');
    }
}
