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

/**
 * The enrol_apply integration must stay optional for anybody installing this plugin.
 *
 * calculator::current_user_can_enrol() knows how to read an enrol_apply instance, and
 * .github/workflows/ci.yml now checks that plugin out on the 5.01 and 5.02 jobs so those tests
 * run against the real thing rather than skipping. Both of those make it easy for the
 * integration to become a requirement by accident, and the accident would be invisible: with
 * enrol_apply present on every 5.x leg and on the local m501/m502 stacks, a hard reference to
 * it would pass everything here and only fail on a site that never installed it.
 *
 * This file is the observer for that. It pins the two halves of the promise - nothing declared
 * in version.php, and nothing loaded unconditionally in the source - plus the behaviour a site
 * without the plugin actually sees.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\calculator::current_user_can_enrol
 * @covers     \local_dimensions\calculator::current_user_has_pending_application
 */
final class optional_enrol_apply_test extends \advanced_testcase {
    /**
     * version.php declares no dependency on any enrol plugin.
     *
     * A declared dependency is what would make Moodle refuse to install this plugin without
     * enrol_apply, and what would pull enrol_apply into every stack that mounts this one.
     *
     * @return void
     */
    public function test_version_php_declares_no_enrol_dependency(): void {
        $info = \core_plugin_manager::instance()->get_plugin_info('local_dimensions');
        $required = $info->get_other_required_plugins();

        $this->assertArrayNotHasKey('enrol_apply', $required);
        $this->assertSame([], array_filter(
            array_keys($required),
            static function ($component) {
                return strpos($component, 'enrol_') === 0;
            }
        ));
    }

    /**
     * Production source loads no enrol_apply file and names no enrol_apply class.
     *
     * The plugin may only reach that plugin through the runtime is_callable() guard in
     * calculator. A require of its lib.php, or a namespaced class reference the autoloader has
     * to resolve, would turn "optional" into a fatal error on a site without it.
     *
     * @return void
     */
    public function test_no_source_file_hard_requires_enrol_apply(): void {
        /* The use-statement pattern is not redundant with the namespace one. An import is
           spelled WITHOUT a leading backslash - `use enrol_apply\local\queue;` - and the code
           then calls the short alias, so neither of the qualified patterns below would ever
           see it. That is the likeliest shape this defect would actually take, and it became
           likelier still when current_user_has_pending_application()'s docblock started
           naming that very class as the authority for its SQL. */
        $patterns = [
            '/(require|include)(_once)?\s*\(?[^;]*enrol\/apply/i' => 'loads a file from enrol/apply',
            '/^\s*use\s+\\\\?enrol_apply\\\\/i' => 'imports a class from the enrol_apply namespace',
            '/new\s+\\\\?enrol_apply_plugin\b/' => 'constructs enrol_apply_plugin directly',
            '/\\\\?enrol_apply_plugin\s*::/' => 'calls enrol_apply_plugin statically',
            '/\\\\enrol_apply\\\\/' => 'names a class in the enrol_apply namespace',
        ];

        $offences = [];
        foreach ($this->production_php_files() as $path) {
            foreach (file($path) as $number => $line) {
                if ($this->is_comment_line($line)) {
                    continue;
                }
                foreach ($patterns as $pattern => $label) {
                    if (preg_match($pattern, $line)) {
                        $offences[] = basename($path) . ':' . ($number + 1) . ' ' . $label;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "Production code must reach enrol_apply only through the is_callable() guard:\n"
                . implode("\n", $offences)
        );
    }

    /**
     * With enrol_apply switched off site-wide, the predicate answers no and does not fall over.
     *
     * This is the closest observable stand-in for "the plugin was never installed": both leave
     * enrol_get_instances($courseid, true) with nothing of that type to return. The control
     * below is what stops the test passing vacuously - the same course, the same instance, with
     * the plugin switched back on.
     *
     * @return void
     */
    public function test_the_predicate_is_inert_when_enrol_apply_is_disabled(): void {
        $plugin = enrol_get_plugin('apply');
        if (!$plugin || !is_callable([$plugin, 'allow_apply'])) {
            $this->markTestSkipped('enrol_apply is not installed on this site.');
        }
        $this->resetAfterTest();

        $enabled = enrol_get_plugins(true);
        $enabled['apply'] = true;
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'customint3' => 0,
            'customint5' => 0,
            'customint6' => 1,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);

        $applicant = $this->getDataGenerator()->create_user();
        $plugin->enrol_user($instance, (int) $applicant->id, null, 0, 0, ENROL_USER_SUSPENDED);

        // Control: with the plugin enabled, both questions see the instance.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(calculator::current_user_can_enrol((int) $course->id));
        $this->setUser($applicant);
        $this->assertTrue(calculator::current_user_has_pending_application((int) $course->id));

        // Now take the plugin away, which is what a site that never installed it looks like.
        unset($enabled['apply']);
        set_config('enrol_plugins_enabled', implode(',', array_keys($enabled)));

        $this->assertFalse(calculator::current_user_has_pending_application((int) $course->id));
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(calculator::current_user_can_enrol((int) $course->id));
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
            if (!is_dir($root . $subdir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . $subdir)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Whether a source line is prose rather than code.
     *
     * The docblocks in calculator name enrol_apply and its methods on purpose - explaining the
     * dispatch is not the same as depending on it.
     *
     * @param string $line One raw source line.
     * @return bool True when the line opens with a PHP comment marker.
     */
    private function is_comment_line(string $line): bool {
        return (bool) preg_match('~^\s*(//|/\*|\*|#)~', $line);
    }
}
