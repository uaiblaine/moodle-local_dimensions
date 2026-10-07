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

namespace local_dimensions;

use local_dimensions\local\enrolment_provider;

/**
 * local_unlistedcourses stays optional: no dependency, one guarded way in, a setting only where it applies.
 *
 * The plugin supports Moodle 4.5 to 5.2 and local_unlistedcourses installs on 5.2 and later only, so
 * any reference that has to resolve on every site would make this plugin uninstallable where that
 * one cannot go. The enrolment provider reaches it through a class name held as a string, behind
 * enrolment_provider::unlisted_available(), and that is the only way in.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\enrolment_provider
 */
final class optional_unlistedcourses_test extends \advanced_testcase {
    /**
     * version.php declares no dependency on local_unlistedcourses.
     *
     * @return void
     */
    public function test_version_php_declares_no_unlisted_dependency(): void {
        $required = \core_plugin_manager::instance()->get_plugin_info('local_dimensions')->get_other_required_plugins();

        $this->assertArrayNotHasKey('local_unlistedcourses', $required);
    }

    /**
     * Production source names local_unlistedcourses only as the provider's guarded string.
     *
     * @return void
     */
    public function test_no_source_file_hard_requires_local_unlistedcourses(): void {
        $patterns = [
            '/(require|include)(_once)?\s*\(?[^;]*local\/unlistedcourses/i' => 'loads a file from local/unlistedcourses',
            '/^\s*use\s+\\\\?local_unlistedcourses\\\\/i' => 'imports a class from the local_unlistedcourses namespace',
            '/\\\\local_unlistedcourses\\\\/' => 'names a class in the local_unlistedcourses namespace',
            '/[\'"]local_unlistedcourses\\\\/' => 'spells a class of local_unlistedcourses in a string',
        ];
        $allowed = 'enrolment_provider.php';

        $offences = [];
        $spelled = 0;
        foreach ($this->production_php_files() as $path) {
            foreach (file($path) as $number => $line) {
                if (preg_match('~^\s*(//|/\*|\*|#)~', $line)) {
                    continue;
                }
                foreach ($patterns as $pattern => $label) {
                    if (!preg_match($pattern, $line)) {
                        continue;
                    }
                    if (basename($path) === $allowed && str_contains($label, 'in a string')) {
                        $spelled++;
                        continue;
                    }
                    $offences[] = basename($path) . ':' . ($number + 1) . ' ' . $label;
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "local_unlistedcourses is reached only through the provider:\n" . implode("\n", $offences)
        );
        // Control: the one allowed spelling is there, so the scan reads what it means to.
        $this->assertSame(1, $spelled);
    }

    /**
     * The setting is offered only where the plugin is available.
     *
     * @return void
     */
    public function test_the_setting_is_offered_only_where_the_plugin_is(): void {
        global $CFG;

        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();
        require_once($CFG->libdir . '/adminlib.php');

        $page = admin_get_root(true, true)->locate('local_dimensions_settings', true);
        $this->assertNotNull($page, 'The settings page was not found, so nothing would be checked.');
        $names = array_map(static function ($setting) {
            return $setting->plugin . '/' . $setting->name;
        }, $page->settings ? array_values((array) $page->settings) : []);

        // Control: a neighbouring setting of the same page is there.
        $this->assertContains('local_dimensions/lockedcardmode', $names);
        $expected = enrolment_provider::unlisted_available();
        $this->assertSame($expected, in_array('local_dimensions/' . enrolment_provider::SETTING, $names, true));
    }

    /**
     * Every PHP file the plugin ships as production code, tests excluded.
     *
     * @return array Absolute file paths.
     */
    private function production_php_files(): array {
        $root = dirname(__DIR__);
        $files = glob($root . '/*.php') ?: [];
        foreach (['/classes', '/db'] as $subdir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . $subdir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }
}
