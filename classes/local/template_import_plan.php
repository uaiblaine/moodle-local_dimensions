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
 * The projected result of a learning plan CSV import: what would happen, item by item.
 *
 * An immutable value object with no behaviour beyond addressing and tallying. The analyser
 * builds it, the preview renders it, and the importer rebuilds it at write time and compares.
 * It holds no database handle and no state that could drift between the two.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\local;

/**
 * The immutable projection of an import, addressed by stable item keys.
 *
 * An item is an array carrying at least: itemkey (t<n>), verdict, reason, selectable,
 * preselected, fingerprint and links. A link is an array carrying at least: itemkey (t<n>l<m>),
 * status, confidence, selectable and preselected. Item keys are PARAM_ALPHANUM-safe and stable
 * across a re-parse of the same immutable draft file, which is what lets the apply step address
 * a selection made against an earlier request.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_import_plan {
    /** @var array Items keyed by item key, in file order. */
    protected $items;

    /** @var array File-level notices, each an array of key and message. */
    protected $notices;

    /** @var array Structures the file references but this site does not have. */
    protected $missingstructures;

    /** @var \context The context the import would write to. */
    protected $target;

    /** @var array|null Memoised counts. */
    protected $counts = null;

    /**
     * Build the projection.
     *
     * @param array $items Items keyed by item key, in file order.
     * @param array $notices File-level notices, each an array of a key and a resolved message.
     * @param array $missingstructures Each an array of idnumber and shortname.
     * @param \context $target The context the import would write to.
     */
    public function __construct(array $items, array $notices, array $missingstructures, \context $target) {
        $this->items = $items;
        $this->notices = $notices;
        $this->missingstructures = $missingstructures;
        $this->target = $target;
    }

    /**
     * Every item, keyed by item key, in file order.
     *
     * @return array
     */
    public function get_items(): array {
        return $this->items;
    }

    /**
     * One item by its key, or null when the file no longer carries it.
     *
     * Null rather than an exception: the apply step looks up every selection the browser sends
     * against a freshly built plan, and a selection that has vanished is an outcome (gone), not
     * an error.
     *
     * @param string $itemkey The item key.
     * @return array|null
     */
    public function get_item(string $itemkey): ?array {
        return $this->items[$itemkey] ?? null;
    }

    /**
     * The flat scalar tallies the preview summary strip and the web service declare.
     *
     * Every value is an int and the key set is fixed: the preview web service's
     * execute_returns() mirrors it key for key, and clean_returnvalue() silently strips
     * anything it does not declare.
     *
     * @return array
     */
    public function get_counts(): array {
        if ($this->counts !== null) {
            return $this->counts;
        }

        $counts = [
            'total' => count($this->items),
            'create' => 0,
            'update' => 0,
            'insync' => 0,
            'skip' => 0,
            'conflict' => 0,
            'blocked' => 0,
            'orphanlink' => 0,
            'selectable' => 0,
            'preselected' => 0,
            'links' => 0,
            'linksmatched' => 0,
            'linksunresolved' => 0,
        ];

        // Tallied against the declared verdict list rather than against the count keys, so a
        // bogus verdict can never land on 'total' or 'links'.
        $verdicts = template_import_verdict::verdicts();
        foreach ($this->items as $item) {
            $verdict = (string) ($item['verdict'] ?? '');
            if (in_array($verdict, $verdicts, true)) {
                $counts[$verdict]++;
            }
            if (!empty($item['selectable'])) {
                $counts['selectable']++;
            }
            if (!empty($item['preselected'])) {
                $counts['preselected']++;
            }
            foreach (($item['links'] ?? []) as $link) {
                $counts['links']++;
                $status = (string) ($link['status'] ?? '');
                if (template_import_verdict::link_is_resolved($status)) {
                    $counts['linksmatched']++;
                } else if (template_import_verdict::link_is_unresolved($status)) {
                    $counts['linksunresolved']++;
                }
            }
        }

        $this->counts = $counts;
        return $counts;
    }

    /**
     * The file-level notices, each an array of a key and a resolved message.
     *
     * @return array
     */
    public function get_notices(): array {
        return $this->notices;
    }

    /**
     * The structures the file references but this site does not have, each an array of
     * idnumber and shortname — the "import these first" list.
     *
     * @return array
     */
    public function get_missing_structures(): array {
        return $this->missingstructures;
    }

    /**
     * The context the import would write to.
     *
     * @return \context
     */
    public function get_target_context(): \context {
        return $this->target;
    }
}
