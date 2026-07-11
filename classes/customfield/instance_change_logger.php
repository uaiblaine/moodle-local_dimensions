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
 * Change-diff logging shared by the plugin's custom field handlers.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\customfield;

use local_dimensions\picture_manager;

/**
 * Snapshot/diff helpers so handler saves can fire *_customfields_updated events.
 *
 * core_customfield fires no event for data (value) changes; the handlers
 * snapshot values around the save and fire one event per save with the diff,
 * covering every write path that goes through the handler (hub modals, web
 * services, the legacy-form observer repost and the CSV importer).
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait instance_change_logger {
    /**
     * Drop the cached category/field list so the next read hits the DB.
     *
     * Public wrapper over core's protected clear_configuration_cache():
     * both plugin handlers are singletons, so a list cached before waiting on
     * the provisioning lock would go stale once the lock winner creates the
     * fields — the loser must re-read before its own existence checks.
     */
    public function reset_configuration_cache(): void {
        $this->clear_configuration_cache();
    }

    /**
     * Snapshot the EFFECTIVE custom field values of an instance.
     *
     * Every field is captured through get_value(), which falls back to the
     * field default when no data row exists yet — so a form save that merely
     * materialises a row at its default value (the modal submits every field)
     * does not read as a change, and the diff lists only real edits.
     *
     * @param int $instanceid Instance (competency or template) id.
     * @return array Map of shortname to [field type, effective value].
     */
    private function snapshot_instance_values(int $instanceid): array {
        if (!$instanceid) {
            return [];
        }
        $values = [];
        $fieldsdata = \core_customfield\api::get_instance_fields_data($this->get_fields(), $instanceid);
        foreach ($fieldsdata as $data) {
            $field = $data->get_field();
            $values[$field->get('shortname')] = [$field->get('type'), $data->get_value()];
        }
        return $values;
    }

    /**
     * Diff two snapshots into the event payload.
     *
     * Scalar fields carry old/new verbatim; textarea bodies (custom SCSS) are
     * redacted to the literal '(updated)' marker so multi-KB text stays out of
     * the log store (same convention core uses for the course summary).
     *
     * @param array $before Snapshot before the save.
     * @param array $after Snapshot after the save.
     * @return array Map of shortname to ['old' => x, 'new' => y] or '(updated)'.
     */
    private function diff_instance_values(array $before, array $after): array {
        $changed = [];
        foreach ($after as $shortname => [$type, $value]) {
            $old = $before[$shortname][1] ?? null;
            if ($old === $value) {
                continue;
            }
            $changed[$shortname] = $type === 'textarea' ? '(updated)' : ['old' => $old, 'new' => $value];
        }
        foreach ($before as $shortname => [$type, $value]) {
            if (!array_key_exists($shortname, $after)) {
                $changed[$shortname] = $type === 'textarea' ? '(updated)' : ['old' => $value, 'new' => null];
            }
        }
        return $changed;
    }

    /**
     * Snapshot the content hashes of the built-in image areas of an instance.
     *
     * @param string $area Custom field area ('competency' or 'lp').
     * @param int $instanceid Instance id.
     * @return array Map of pseudo-key (bgimage/cardimage) to content hash list.
     */
    private function snapshot_image_hashes(string $area, int $instanceid): array {
        $fs = get_file_storage();
        $contextid = \core\context\system::instance()->id;
        $fileareas = $area === 'competency'
            ? [
                'bgimage' => picture_manager::FILEAREA_COMPETENCY,
                'cardimage' => picture_manager::FILEAREA_COMPETENCY_CARD,
            ]
            : [
                'bgimage' => picture_manager::FILEAREA_TEMPLATE,
                'cardimage' => picture_manager::FILEAREA_TEMPLATE_CARD,
            ];
        $hashes = [];
        foreach ($fileareas as $key => $filearea) {
            $files = $fs->get_area_files($contextid, picture_manager::COMPONENT, $filearea, $instanceid, 'id', false);
            $hashes[$key] = array_values(array_map(static function ($file) {
                return $file->get_contenthash();
            }, $files));
        }
        return $hashes;
    }

    /**
     * Diff two image snapshots into changed pseudo-keys.
     *
     * @param array $before Hashes before the save.
     * @param array $after Hashes after the save.
     * @return array Map of pseudo-key to the '(updated)' marker.
     */
    private function diff_image_hashes(array $before, array $after): array {
        $changed = [];
        foreach ($after as $key => $hashes) {
            if ($hashes !== ($before[$key] ?? [])) {
                $changed[$key] = '(updated)';
            }
        }
        return $changed;
    }

    /**
     * Fire the handler's customfields-updated event when the diff is non-empty.
     *
     * @param string $eventclass Fully qualified event class to fire.
     * @param int $instanceid Instance id.
     * @param bool $isnew Whether the save created the instance.
     * @param array $changed Diff produced by the diff helpers.
     */
    private function trigger_customfields_updated(string $eventclass, int $instanceid, bool $isnew, array $changed): void {
        if (!$changed || !$instanceid) {
            return;
        }
        $eventclass::create([
            'context' => $this->get_instance_context($instanceid),
            'objectid' => $instanceid,
            'other' => [
                'area' => $this->get_area(),
                'isnew' => $isnew,
                'changed' => $changed,
            ],
        ])->trigger();
    }
}
