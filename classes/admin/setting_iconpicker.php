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
 * Uses the Moodle autocomplete form element with AJAX to search and select
 * FontAwesome icons, similar to the Boost Union Smart Menus icon picker.
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
        global $OUTPUT, $PAGE;

        $default = $this->get_defaultsetting();
        $context = (object) [
            'id' => $this->get_id(),
            'name' => $this->get_full_name(),
            'size' => $this->size ?? 30,
            'value' => s($data),
            'forceltr' => $this->get_force_ltr(),
        ];

        // Build the icon map for the valuehtmlcallback equivalent.
        $currenthtml = '';
        if (!empty($data)) {
            $currenthtml = $this->format_icon_display($data);
        }

        // Generate a unique element ID.
        $elementid = $this->get_id() . '_iconpicker';

        // Build the autocomplete HTML manually.
        $noselection = get_string('cardicon_noicon', 'local_dimensions');
        $placeholder = get_string('cardicon_placeholder', 'local_dimensions');

        // Render using a combination of standard admin setting wrapper + autocomplete widget.
        $html = '<div class="form-autocomplete-container" id="' . $elementid . '-container">';

        // Hidden input to store the actual value.
        $html .= '<input type="hidden" name="' . $this->get_full_name()
            . '" id="' . $this->get_id() . '" value="' . s($data) . '" />';

        // Visible text input for the autocomplete.
        $html .= '<div class="d-flex align-items-center">';
        if (!empty($data) && !empty($currenthtml)) {
            $html .= '<span class="mr-2 me-2" id="' . $elementid . '-preview">' . $currenthtml . '</span>';
        } else {
            $html .= '<span class="mr-2 me-2" id="' . $elementid . '-preview"></span>';
        }
        $html .= '<div class="d-inline-flex align-items-center position-relative" style="max-width: 400px; flex: 1;">';
        $html .= '<input type="text" class="form-control" id="' . $elementid . '-search"';
        $html .= ' placeholder="' . s($placeholder) . '"';
        $html .= ' value="' . s($data) . '"';
        $html .= ' autocomplete="off"';
        $html .= ' style="padding-right: 32px;" />';
        $html .= '<button type="button" class="btn p-0 border-0 position-absolute" id="' . $elementid . '-downarrow"';
        $html .= ' style="right: 8px; top: 50%; transform: translateY(-50%); background: none; line-height: 1;"';
        $html .= ' title="' . s(get_string('cardicon_browseall', 'local_dimensions')) . '">';
        $html .= '<i class="fa fa-chevron-down text-muted" aria-hidden="true"></i>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= ' <button type="button" class="btn btn-sm btn-outline-secondary ml-2 ms-2" id="' . $elementid . '-clear"';
        $html .= ' title="Clear">&times;</button>';
        $html .= '</div>';

        // Dropdown results container.
        $html .= '<div class="dims-icon-dropdown" id="' . $elementid . '-dropdown"';
        $html .= ' style="display:none; position:absolute; z-index:1000; background:#fff; border:1px solid #ccc;';
        $html .= ' border-radius:4px; max-height:300px; overflow-y:auto; width:400px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">';
        $html .= '</div>';

        $html .= '</div>';

        // Inline JavaScript for the icon picker behavior.
        $html .= $this->get_inline_js($elementid);

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

    /**
     * Generates the inline JavaScript for the icon picker widget.
     *
     * @param string $elementid The base element ID
     * @return string Script tag with the JavaScript
     */
    private function get_inline_js($elementid) {
        $hiddenid = $this->get_id();

        $js = <<<JS
<script>
(function() {
    var searchInput = document.getElementById('{$elementid}-search');
    var hiddenInput = document.getElementById('{$hiddenid}');
    var dropdown = document.getElementById('{$elementid}-dropdown');
    var preview = document.getElementById('{$elementid}-preview');
    var clearBtn = document.getElementById('{$elementid}-clear');
    var downArrow = document.getElementById('{$elementid}-downarrow');
    var debounceTimer = null;
    var cache = {};

    if (!searchInput || !hiddenInput || !dropdown) return;

    searchInput.addEventListener('input', function() {
        var query = this.value.trim();
        clearTimeout(debounceTimer);
        if (query.length < 1) {
            dropdown.style.display = 'none';
            return;
        }
        debounceTimer = setTimeout(function() {
            searchIcons(query);
        }, 300);
    });

    searchInput.addEventListener('focus', function() {
        var query = this.value.trim();
        if (query.length >= 1 && dropdown.innerHTML) {
            dropdown.style.display = 'block';
        }
    });

    // Down arrow: toggle dropdown with all icons (empty query).
    if (downArrow) {
        downArrow.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            } else {
                searchIcons('');
            }
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#' + '{$elementid}-container')) {
            dropdown.style.display = 'none';
        }
    });

    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        hiddenInput.value = '';
        preview.innerHTML = '';
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
    });

    function searchIcons(query) {
        if (cache[query]) {
            renderResults(cache[query]);
            return;
        }

        require(['core/ajax'], function(Ajax) {
            Ajax.call([{
                methodname: 'local_dimensions_get_fontawesome_icons',
                args: { query: query }
            }])[0].done(function(response) {
                cache[query] = response;
                renderResults(response);
            }).fail(function(err) {
                console.error('Icon search failed:', err);
                dropdown.innerHTML = '<div class="p-2 text-danger">Error loading icons</div>';
                dropdown.style.display = 'block';
            });
        });
    }

    function renderResults(response) {
        dropdown.innerHTML = '';

        if (response.overflow) {
            dropdown.innerHTML = '<div class="p-2 text-muted">Too many results. Please refine your search.</div>';
            dropdown.style.display = 'block';
            return;
        }

        if (!response.icons || response.icons.length === 0) {
            dropdown.innerHTML = '<div class="p-2 text-muted">No icons found.</div>';
            dropdown.style.display = 'block';
            return;
        }

        response.icons.forEach(function(icon) {
            var item = document.createElement('div');
            item.style.cssText = 'padding:6px 10px; cursor:pointer; display:flex; align-items:center; gap:8px;';
            item.addEventListener('mouseenter', function() { this.style.background = '#f0f0f0'; });
            item.addEventListener('mouseleave', function() { this.style.background = ''; });

            var iconClass = resolveClass(icon);
            var sourceLabel = icon.source === 'core' ? 'Core'
                : (icon.source === 'fasolid' ? 'FA'
                    : (icon.source === 'fabrand' ? 'Brand' : 'FA'));
            var sourceColor = icon.source === 'core' ? 'bg-warning text-dark' : 'bg-success';

            item.innerHTML = '<i class="' + iconClass + ' fa-fw" aria-hidden="true"></i>' +
                '<small style="flex:1; word-break:break-all;">' + escapeHtml(icon.name) + '</small>' +
                '<span class="badge ' + sourceColor + '">' + sourceLabel + '</span>';

            item.addEventListener('click', function() {
                // Core icons: store FA class (e.g. "fa-wand-magic-sparkles") instead of
                // Moodle identifier ("core:i/grading") so resolveIconClass in ui.js works.
                var storedValue = (icon.source === 'core') ? icon.class : icon.name;
                hiddenInput.value = storedValue;
                searchInput.value = storedValue;
                preview.innerHTML = '<i class="' + iconClass + ' fa-lg" aria-hidden="true"></i>';
                dropdown.style.display = 'none';
            });

            dropdown.appendChild(item);
        });

        dropdown.style.display = 'block';
    }

    function resolveClass(icon) {
        if (icon.source === 'core') return 'fa ' + icon.class;
        if (icon.source === 'fasolid') return 'fas ' + icon.class;
        if (icon.source === 'fabrand') return 'fab ' + icon.class;
        return 'fa ' + icon.class;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
})();
</script>
JS;

        return $js;
    }
}
