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
 * SCSS manager for compiling, caching and validating template SCSS.
 *
 * Handles reading SCSS from the custom field, validating it using the
 * same approach as Moodle core's admin_setting_scsscode, compiling it
 * with core_scss, and caching the compiled CSS via MUC.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions;

/**
 * SCSS manager for compiling, caching and validating template SCSS.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine (anderson@blaine.com.br)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scss_manager {
    /**
     * Get the raw SCSS code stored in the custom field for a given instance.
     *
     * @param int $instanceid The instance ID (template or competency ID).
     * @param string $area The custom field area ('lp' or 'competency').
     * @return string|null The raw SCSS code or null if not found.
     */
    public static function get_scss(int $instanceid, string $area = 'lp'): ?string {
        global $DB;

        $sql = "SELECT d.id, d.value
                  FROM {customfield_data} d
                  JOIN {customfield_field} f ON f.id = d.fieldid
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE f.shortname = :shortname
                   AND c.component = :component
                   AND c.area = :area
                   AND d.instanceid = :instanceid";

        $record = $DB->get_record_sql($sql, [
            'shortname' => constants::CFIELD_CUSTOMSCSS,
            'component' => 'local_dimensions',
            'area' => $area,
            'instanceid' => $instanceid,
        ]);

        if (!$record || empty(trim($record->value))) {
            return null;
        }

        return trim($record->value);
    }

    /**
     * Validate SCSS code for syntax errors.
     *
     * Uses the same logic as Moodle core's admin_setting_scsscode:
     * - ParserException = real syntax error, return error message.
     * - CompilerException = may reference external variables, tolerated.
     *
     * @param string $scss The SCSS code to validate.
     * @return true|string True if valid, or an error message string.
     */
    public static function validate_scss(string $scss) {
        if (empty(trim($scss))) {
            return true;
        }

        $compiler = new \core_scss();
        try {
            $compiler->compile($scss);
        } catch (\ScssPhp\ScssPhp\Exception\ParserException $e) {
            return get_string('customscss_invalid', 'local_dimensions', $e->getMessage());
        } catch (\ScssPhp\ScssPhp\Exception\CompilerException $e) {
            // Silently ignore - could be a variable defined elsewhere.
            return true;
        }

        return true;
    }

    /**
     * Get compiled CSS for an instance, using MUC cache.
     *
     * If the compiled CSS is not in cache, it compiles the raw SCSS
     * and stores the result in the cache for subsequent requests.
     *
     * @param int $instanceid The instance ID (template or competency ID).
     * @param string $area The custom field area ('lp' or 'competency').
     * @return string The compiled CSS, or empty string if no SCSS is set.
     */
    public static function get_compiled_css(int $instanceid, string $area = 'lp'): string {
        $cachename = ($area === 'competency') ? 'competency_scss' : 'template_scss';
        $cache = \cache::make('local_dimensions', $cachename);
        $cachekey = 'css_' . $instanceid;

        $css = $cache->get($cachekey);
        if ($css !== false) {
            return $css;
        }

        // Cache miss - compile from raw SCSS.
        $scss = self::get_scss($instanceid, $area);
        if (empty($scss)) {
            $cache->set($cachekey, '');
            return '';
        }

        $css = self::compile_scss($scss);
        $cache->set($cachekey, $css);

        return $css;
    }

    /**
     * Compile raw SCSS into CSS using Moodle's core_scss compiler.
     *
     * @param string $scss The raw SCSS code.
     * @return string The compiled CSS, or empty string on error.
     */
    public static function compile_scss(string $scss): string {
        if (empty(trim($scss))) {
            return '';
        }

        $compiler = new \core_scss();
        try {
            return $compiler->compile($scss);
        } catch (\Exception $e) {
            debugging('local_dimensions: SCSS compilation error: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Invalidate the cached compiled CSS for an instance.
     *
     * Should be called whenever the SCSS custom field value is updated.
     *
     * @param int $instanceid The instance ID (template or competency ID).
     * @param string $area The custom field area ('lp' or 'competency').
     */
    public static function invalidate_cache(int $instanceid, string $area = 'lp'): void {
        $cachename = ($area === 'competency') ? 'competency_scss' : 'template_scss';
        $cache = \cache::make('local_dimensions', $cachename);
        $cache->delete('css_' . $instanceid);
    }
}
