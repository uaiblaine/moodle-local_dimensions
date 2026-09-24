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
 * Custom admin setting for FontAwesome icon picker using autocomplete.
 *
 * A search field with its own result dropdown (template and AMD module
 * local_dimensions/setting_iconpicker, not core's autocomplete element) that searches
 * FontAwesome icons through local_dimensions_get_fontawesome_icons, similar to the
 * Boost Union Smart Menus icon picker.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\admin;

/**
 * Admin setting for selecting a FontAwesome icon via autocomplete.
 *
 * Renders an autocomplete text input that searches FontAwesome icons via AJAX
 * and displays them with their visual preview and source badge.
 *
 * The stored value is the FA class of a core icon ("fa-lock"), or "<component>:fa-<name>" /
 * "<component>:fab-<name>" for a FontAwesome solid or brand icon. Legacy "core:i/<name>"
 * values are still resolved, through the core icon map.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_iconpicker extends \admin_setting_configtext {
    /**
     * Returns the HTML for the setting.
     *
     * @param mixed $data The current value
     * @param string $query Search query (for highlighting)
     * @return string HTML output
     */
    public function output_html($data, $query = '') {
        global $PAGE;

        $default = $this->get_defaultsetting();

        // Preview of the stored icon, shown beside the search field.
        $currenthtml = '';
        if (!empty($data)) {
            $currenthtml = $this->format_icon_display($data);
        }

        $elementid = $this->get_id() . '_iconpicker';

        $templatedata = [
            'elementid' => $elementid,
            'fullname' => $this->get_full_name(),
            'id' => $this->get_id(),
            'value' => s($data),
            'hasvalue' => !empty($data) && !empty($currenthtml),
            'currenthtml' => $currenthtml,
            'placeholder' => get_string('cardicon_placeholder', 'local_dimensions'),
            'browseall_title' => get_string('cardicon_browseall', 'local_dimensions'),
            'clear_title' => get_string('iconpicker_clear', 'local_dimensions'),
        ];

        $renderer = $PAGE->get_renderer('core');
        $html = $renderer->render_from_template('local_dimensions/setting_iconpicker', $templatedata);

        // Initialise the AMD module for the icon picker behaviour. The visible
        // search field carries the canonical setting id so the core-generated
        // <label for="..."> points at a real, focusable control (a hidden input
        // is not a labelable element); the value is stored in a sibling hidden
        // input keyed off the widget id.
        $PAGE->requires->js_call_amd('local_dimensions/setting_iconpicker', 'init', [[
            'elementId' => $elementid,
            'inputId' => $this->get_id(),
        ]]);

        $element = \format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', $default, $query);

        return $element;
    }

    /**
     * Formats the current icon value for display preview.
     *
     * @param string $value The stored icon identifier
     * @return string HTML to display the icon
     */
    private function format_icon_display($value) {
        if (empty($value)) {
            return '';
        }

        $class = $this->resolve_icon_class($value);
        if (!empty($class)) {
            return '<i class="' . s($class) . ' fa-lg" aria-hidden="true"></i>';
        }

        return '<span class="text-muted">' . s($value) . '</span>';
    }

    /**
     * Resolves a stored icon identifier to its CSS class.
     *
     * @param string $identifier The stored icon identifier
     * @return string The CSS class(es)
     */
    private function resolve_icon_class($identifier) {
        if (empty($identifier)) {
            return '';
        }

        if (strpos($identifier, ':') !== false) {
            $parts = explode(':', $identifier, 2);
            $iconname = $parts[1] ?? '';

            // FA solid icons: "xxx:fa-book" -> "fa fa-fw fa-book".
            if (strpos($iconname, 'fa-') === 0) {
                return 'fa fa-fw ' . $iconname;
            }
            // FA brand icons: "xxx:fab-github" -> "fa fa-fw fab fa-github".
            if (strpos($iconname, 'fab-') === 0) {
                return 'fa fa-fw fab fa-' . substr($iconname, 4);
            }
            // Legacy core identifiers ("core:i/grading"): lookup the real FA class.
            $component = $parts[0];
            if ($component === 'core') {
                $faclass = $this->lookup_core_icon_class($identifier);
                if (!empty($faclass)) {
                    return 'fa fa-fw ' . $faclass;
                }
            }
            return 'fa fa-fw ' . str_replace('/', '-', $iconname);
        }

        // Direct FA class (e.g. "fa-wand-magic-sparkles").
        if (strpos($identifier, 'fa-') === 0) {
            return 'fa fa-fw ' . $identifier;
        }

        return $identifier;
    }

    /**
     * Looks up the FA class for a Moodle core icon identifier.
     *
     * Handles legacy stored values like "core:i/grading" by resolving them
     * through the theme icon map to the actual FA class (e.g. "fa-wand-magic-sparkles").
     *
     * @param string $identifier The Moodle icon identifier (e.g. "core:i/grading")
     * @return string The FA class (e.g. "fa-wand-magic-sparkles") or empty string
     */
    private function lookup_core_icon_class($identifier) {
        global $CFG;

        // Try Boost Union first.
        $boostunionlocallib = $CFG->dirroot . '/theme/boost_union/locallib.php';
        if (file_exists($boostunionlocallib)) {
            require_once($boostunionlocallib);
            if (function_exists('theme_boost_union_build_fa_icon_map')) {
                $iconmap = theme_boost_union_build_fa_icon_map();
                if (isset($iconmap[$identifier]['class'])) {
                    return $iconmap[$identifier]['class'];
                }
            }
        }

        // Fallback: Moodle core icon system.
        try {
            $theme = \core\output\theme_config::load('boost');
            $iconsystem = \core\output\icon_system_fontawesome::instance($theme->get_icon_system());
            $coremap = $iconsystem->get_core_icon_map();
            if (isset($coremap[$identifier])) {
                return $coremap[$identifier];
            }
        } catch (\Exception $e) {
            debugging('Failed to resolve core icon: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return '';
    }
}
