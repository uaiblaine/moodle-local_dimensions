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
 * External API to get FontAwesome icons for the icon picker.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core\context\system as context_system;

/**
 * External API to get FontAwesome icons.
 *
 * Reuses theme_boost_union_build_fa_icon_map() if available,
 * otherwise falls back to Moodle core icon map + FontAwesome SCSS parsing.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_fontawesome_icons extends external_api {
    /** @var int Maximum number of results to return. */
    private const MAX_RESULTS = 3000;

    /**
     * Define input parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_RAW, 'The search query', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get FontAwesome icons matching the given query.
     *
     * @param string $query The search query
     * @return array
     */
    public static function execute($query) {
        global $CFG, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['query' => $query]);
        $query = clean_param($params['query'], PARAM_TEXT);

        $systemcontext = context_system::instance();
        self::validate_context($systemcontext);
        $PAGE->set_context($systemcontext);

        // Build the icon map.
        $iconmap = self::build_icon_map();

        // Filter icons based on search query.
        $results = [];
        $count = 0;
        $overflow = false;

        if (!empty($query)) {
            foreach ($iconmap as $key => $icon) {
                if (empty($key)) {
                    continue;
                }
                if (
                    stripos($key, $query) !== false ||
                    (isset($icon['class']) && stripos($icon['class'], $query) !== false)
                ) {
                    if ($count <= self::MAX_RESULTS) {
                        $results[$key] = $icon;
                        $count++;
                    } else {
                        $overflow = true;
                        break;
                    }
                }
            }
        } else {
            $results = array_slice($iconmap, 0, self::MAX_RESULTS, true);
            $overflow = count($iconmap) > self::MAX_RESULTS;
        }

        // Format results.
        $formattedresults = [];
        foreach ($results as $name => $icon) {
            $formattedresults[] = [
                'name' => $name,
                'class' => $icon['class'],
                'source' => $icon['source'],
            ];
        }

        return [
            'icons' => $formattedresults,
            'maxicons' => self::MAX_RESULTS,
            'overflow' => $overflow,
        ];
    }

    /**
     * Define return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'icons' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'The icon name/identifier'),
                    'class' => new external_value(PARAM_TEXT, 'The FontAwesome CSS class'),
                    'source' => new external_value(PARAM_ALPHA, 'The icon source (core, fasolid, fabrand)'),
                ])
            ),
            'maxicons' => new external_value(PARAM_INT, 'Maximum icons limit'),
            'overflow' => new external_value(PARAM_BOOL, 'Whether results exceeded the limit'),
        ]);
    }

    /**
     * Builds the icon map using Boost Union if available, otherwise from core.
     *
     * Boost Union stores both solid and brand icons with the same key pattern
     * (theme_boost_union:fa-xxx), making it impossible to distinguish them
     * from the stored identifier alone. We post-process brand icon keys
     * to use :fab- prefix so the stored value preserves the brand/solid distinction.
     *
     * @return array The icon map [name => ['class' => '...', 'source' => '...']]
     */
    private static function build_icon_map() {
        global $CFG;

        // Try to use Boost Union's comprehensive icon map builder.
        $boostunionlib = $CFG->dirroot . '/theme/boost_union/lib.php';
        $boostunionlocallib = $CFG->dirroot . '/theme/boost_union/locallib.php';

        if (file_exists($boostunionlib) && file_exists($boostunionlocallib)) {
            require_once($boostunionlib);
            require_once($boostunionlocallib);
            if (function_exists('theme_boost_union_build_fa_icon_map')) {
                $rawmap = theme_boost_union_build_fa_icon_map();
                return self::normalize_brand_keys($rawmap);
            }
        }

        // Fallback: build from Moodle core + FontAwesome SCSS.
        return self::build_icon_map_fallback();
    }

    /**
     * Normalize brand icon keys to use :fab- prefix instead of :fa-.
     *
     * Boost Union stores brand icons as theme_boost_union:fa-xxx (same prefix
     * as solid icons), but the source field is 'fabrand'. We rename the key
     * to :fab- so the stored identifier encodes the brand/solid distinction,
     * enabling correct CSS class resolution at render time.
     *
     * @param array $iconmap The raw icon map.
     * @return array The normalized icon map.
     */
    private static function normalize_brand_keys(array $iconmap): array {
        $normalized = [];
        foreach ($iconmap as $key => $icon) {
            if ($icon['source'] === 'fabrand' && strpos($key, ':fa-') !== false && strpos($key, ':fab-') === false) {
                $newkey = str_replace(':fa-', ':fab-', $key);
                $normalized[$newkey] = $icon;
            } else {
                $normalized[$key] = $icon;
            }
        }
        return $normalized;
    }

    /**
     * Fallback icon map builder using Moodle core icon system and FontAwesome SCSS.
     *
     * @return array The icon map
     */
    private static function build_icon_map_fallback() {
        global $CFG;

        $iconmap = [];

        // Step 1: Get Moodle core icon mappings.
        try {
            $theme = \core\output\theme_config::load('boost');
            $faiconsystem = \core\output\icon_system_fontawesome::instance($theme->get_icon_system());
            $iconmapraw = $faiconsystem->get_core_icon_map();

            foreach ($iconmapraw as $iconname => $faname) {
                $iconmap[$iconname] = [
                    'class' => $faname,
                    'source' => 'core',
                ];
            }
        } catch (\Exception $e) {
            // If we can't load the theme, continue with just FA icons.
            debugging('Failed to load core icon map: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Step 2: Parse FontAwesome icons from SCSS variables file.
        $variablesfile = $CFG->dirroot . '/theme/boost/scss/fontawesome/_variables.scss';
        if (file_exists($variablesfile)) {
            $content = file_get_contents($variablesfile);

            // Extract $fa-icons (solid icons).
            $faiconsstart = strpos($content, '$fa-icons:');
            if ($faiconsstart !== false) {
                $fabrandstart = strpos($content, '$fa-brand-icons:', $faiconsstart);
                if ($fabrandstart !== false) {
                    $faiconsection = substr($content, $faiconsstart, $fabrandstart - $faiconsstart);
                    preg_match_all('/"([a-z0-9\-]+)"/', $faiconsection, $solidmatches);
                    if (!empty($solidmatches[1])) {
                        foreach ($solidmatches[1] as $iconname) {
                            $iconmap['local_dimensions:fa-' . $iconname] = [
                                'class' => 'fa-' . $iconname,
                                'source' => 'fasolid',
                            ];
                        }
                    }
                }
            }

            // Extract $fa-brand-icons.
            $fabrandstart = strpos($content, '$fa-brand-icons:');
            if ($fabrandstart !== false) {
                $fabrandsection = substr($content, $fabrandstart);
                preg_match_all('/"([a-z0-9\-]+)"/', $fabrandsection, $brandmatches);
                if (!empty($brandmatches[1])) {
                    foreach ($brandmatches[1] as $iconname) {
                        $iconmap['local_dimensions:fab-' . $iconname] = [
                            'class' => 'fa-' . $iconname,
                            'source' => 'fabrand',
                        ];
                    }
                }
            }
        }

        return $iconmap;
    }
}
