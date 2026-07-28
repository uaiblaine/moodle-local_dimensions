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
 * Renderable for the learning plan CSV import preview.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output\central;

use local_dimensions\local\template_import_plan;
use local_dimensions\local\template_import_verdict;
use renderable;
use renderer_base;
use templatable;

/**
 * Turn a projection into the preview body, grouped by verdict.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_import_preview implements renderable, templatable {
    /**
     * The most rows the preview renders.
     *
     * A multi-thousand-row file would otherwise become a multi-megabyte payload injected into a
     * modal DOM. What was left out is stated rather than silently dropped.
     */
    const MAX_ROWS = 500;

    /** @var template_import_plan The projection being rendered. */
    protected $plan;

    /** @var array The draft handle and parse settings the apply step will resend. */
    protected $settings;

    /**
     * Build the renderable.
     *
     * @param template_import_plan $plan The projection.
     * @param array $settings draftitemid, contextid, delimiter, encoding and updateexisting.
     */
    public function __construct(template_import_plan $plan, array $settings) {
        $this->plan = $plan;
        $this->settings = $settings;
    }

    /**
     * Whether anything in the projection can actually be applied.
     *
     * @return bool
     */
    public function can_apply(): bool {
        return $this->plan->get_counts()['selectable'] > 0;
    }

    /**
     * Export the preview body.
     *
     * @param renderer_base $output The renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $counts = $this->plan->get_counts();
        $items = $this->plan->get_items();

        $groups = [];
        $shown = 0;
        foreach (template_import_verdict::verdicts() as $verdict) {
            $rows = [];
            foreach ($items as $item) {
                if ($item['verdict'] !== $verdict) {
                    continue;
                }
                if ($shown >= self::MAX_ROWS) {
                    break;
                }
                $shown++;
                $rows[] = $this->export_row($item);
            }
            if (empty($rows)) {
                continue;
            }
            $groups[] = [
                'verdict' => $verdict,
                'label' => template_import_verdict::verdict_label($verdict),
                'badge' => template_import_verdict::verdict_badge($verdict),
                'help' => template_import_verdict::verdict_help($verdict),
                'count' => $counts[$verdict] ?? count($rows),
                'rows' => $rows,
                // Blocked and conflicting rows are the ones that need a decision, so they open.
                'expanded' => in_array($verdict, [
                    template_import_verdict::VERDICT_BLOCKED,
                    template_import_verdict::VERDICT_CONFLICT,
                ], true),
            ];
        }

        $missing = [];
        foreach ($this->plan->get_missing_structures() as $structure) {
            $missing[] = $structure['idnumber'] !== ''
                ? $structure['idnumber']
                : $structure['shortname'];
        }

        return [
            'draftitemid' => (int) ($this->settings['draftitemid'] ?? 0),
            'contextid' => (int) ($this->settings['contextid'] ?? 0),
            'delimiter' => (string) ($this->settings['delimiter'] ?? 'comma'),
            'encoding' => (string) ($this->settings['encoding'] ?? 'UTF-8'),
            'updateexisting' => empty($this->settings['updateexisting']) ? 0 : 1,
            'counts' => $this->export_counts($counts),
            'total' => $counts['total'],
            'selectable' => $counts['selectable'],
            'canapply' => $this->can_apply(),
            'hasrows' => $counts['total'] > 0,
            'groups' => $groups,
            'notices' => $this->plan->get_notices(),
            'hasnotices' => !empty($this->plan->get_notices()),
            'hasmissingstructures' => !empty($missing),
            'missingstructures' => implode(', ', $missing),
            'truncated' => $shown < $counts['total'],
            'truncatedlabel' => get_string('central_plans_import_truncated', 'local_dimensions', (object) [
                'shown' => $shown,
                'total' => $counts['total'],
            ]),
        ];
    }

    /**
     * The summary pills: the total, then every verdict that occurs at least once.
     *
     * @param array $counts The plan counts.
     * @return array
     */
    protected function export_counts(array $counts): array {
        $pills = [[
            'label' => get_string('central_plans_import_summary_total', 'local_dimensions'),
            'count' => $counts['total'],
            'badge' => 'bg-secondary',
        ]];
        foreach (template_import_verdict::verdicts() as $verdict) {
            if (empty($counts[$verdict])) {
                continue;
            }
            $pills[] = [
                'label' => template_import_verdict::verdict_label($verdict),
                'count' => $counts[$verdict],
                'badge' => template_import_verdict::verdict_badge($verdict),
            ];
        }
        return $pills;
    }

    /**
     * One item, as the row partial reads it.
     *
     * @param array $item The projected item.
     * @return array
     */
    protected function export_row(array $item): array {
        $links = [];
        foreach ($item['links'] as $link) {
            $links[] = [
                'itemkey' => $link['itemkey'],
                'name' => $this->describe_competency($link),
                'status' => $link['status'],
                'statuslabel' => $link['statuslabel'],
                'statusbadge' => $link['statusbadge'],
                'confidencelabel' => $link['confidencelabel'],
                'detail' => $link['detail'],
                'hasdetail' => $link['detail'] !== '',
                'selectable' => (bool) $link['selectable'],
                'preselected' => (bool) $link['preselected'],
                'checkboxid' => 'ld-tplimp-' . $link['itemkey'],
            ];
        }

        $blast = $item['blast'];
        return [
            'itemkey' => $item['itemkey'],
            'fingerprint' => $item['fingerprint'] ?? '',
            'verdict' => $item['verdict'],
            'verdictlabel' => $item['verdictlabel'],
            'verdictbadge' => $item['verdictbadge'],
            'rownumber' => $item['rownumber'],
            'rownumberlabel' => get_string('central_plans_import_row_number', 'local_dimensions', $item['rownumber']),
            'shortname' => $item['shortname'],
            'templateidnumber' => $item['templateidnumber'],
            'reasonlabel' => $item['reasonlabel'],
            'hasreason' => $item['reasonlabel'] !== '',
            'detail' => $item['detail'],
            'hasdetail' => $item['detail'] !== '',
            'selectable' => (bool) $item['selectable'],
            'preselected' => (bool) $item['preselected'],
            'checkboxid' => 'ld-tplimp-' . $item['itemkey'],
            'selectlabel' => get_string('central_plans_import_select', 'local_dimensions', $item['shortname']),
            'diff' => $item['diff'],
            'hasdiff' => !empty($item['diff']),
            'remedies' => $this->export_remedies($item),
            'hasremedies' => !empty($item['remedies']),
            'links' => $links,
            'haslinks' => !empty($links),
            'hasblast' => $blast['openplans'] > 0 || $blast['cohorts'] > 0
                || $blast['linksadded'] > 0 || $blast['linkskept'] > 0,
            'blastlabel' => get_string('central_plans_import_row_blast', 'local_dimensions', (object) [
                'openplans' => $blast['openplans'],
                'frozenplans' => $blast['frozenplans'],
                'cohorts' => $blast['cohorts'],
                'linksadded' => $blast['linksadded'],
                'linkskept' => $blast['linkskept'],
            ]),
        ];
    }

    /**
     * The remedy radios of one item, each carrying the input name the browser posts back.
     *
     * @param array $item The projected item.
     * @return array
     */
    protected function export_remedies(array $item): array {
        $remedies = [];
        foreach ($item['remedies'] as $index => $remedy) {
            $remedies[] = [
                'remedy' => $remedy['remedy'],
                'label' => $remedy['label'],
                'selected' => (bool) $remedy['selected'],
                'name' => 'ld-tplimp-remedy-' . $item['itemkey'],
                'id' => 'ld-tplimp-remedy-' . $item['itemkey'] . '-' . $index,
            ];
        }
        return $remedies;
    }

    /**
     * A competency link as one readable line.
     *
     * @param array $link The projected link.
     * @return string
     */
    protected function describe_competency(array $link): string {
        $name = $link['competencyshortname'] !== '' ? $link['competencyshortname'] : $link['competencyidnumber'];
        if ($name === '') {
            $name = $link['frameworkidnumber'];
        }
        if ($link['competencyidnumber'] !== '' && $link['competencyshortname'] !== '') {
            $name .= ' (' . $link['competencyidnumber'] . ')';
        }
        $framework = $link['frameworkidnumber'] !== '' ? $link['frameworkidnumber'] : $link['frameworkshortname'];
        return $framework !== '' ? $name . ' · ' . $framework : $name;
    }
}
