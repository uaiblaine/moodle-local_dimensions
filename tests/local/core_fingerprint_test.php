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

namespace local_dimensions\local;

/**
 * The core code the progress rule mirrors is the code it was audited against.
 *
 * When core changes one of the mirrored functions, or which condition types filter user lists, this
 * test names what moved; docs/core-parity.md (in the repository) is what to do then. It reddens on
 * purpose on the commit a release tag goes on and on a stack moved to a new core.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\core_fingerprint
 */
final class core_fingerprint_test extends \advanced_testcase {
    /**
     * This site's core matches the manifest.
     *
     * @return void
     */
    public function test_core_matches_the_audited_fingerprint(): void {
        global $CFG;
        $manifest = core_fingerprint::manifest();

        $differences = core_fingerprint::differences($CFG->dirroot, $manifest);

        $this->assertSame([], $differences, "Core moved code the progress rule of local_dimensions mirrors, since the audit for "
            . $manifest['audited'] . ":\n  " . implode("\n  ", $differences) . "\nRun the audit in docs/core-parity.md "
            . "(git -C <core> diff " . $manifest['audited'] . " -- <file>), then record it with "
            . "php local/dimensions/cli/core_parity.php --rebuild=<release>.");
    }

    /**
     * Every mirrored function is found, and core's list-filtering condition types are the known three.
     *
     * The control for the test above: a manifest whose functions were all missing would compare nothing.
     *
     * @return void
     */
    public function test_every_mirrored_function_is_found(): void {
        global $CFG;
        $manifest = core_fingerprint::manifest();
        $this->assertGreaterThanOrEqual(20, count($manifest['symbols']));
        foreach ($manifest['symbols'] as $symbol) {
            $this->assertNotNull(
                core_fingerprint::symbol($CFG->dirroot . '/' . $symbol['file'], $symbol['class'], $symbol['function']),
                core_fingerprint::name($symbol)
            );
            $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', (string) $symbol['hash'], core_fingerprint::name($symbol));
        }
        $this->assertContains('group', core_fingerprint::list_conditions($CFG->dirroot));
        $this->assertNotContains('date', core_fingerprint::list_conditions($CFG->dirroot));
    }

    /**
     * A comment or a reformat leaves a hash alone; a changed token, a removed method and a new list rule do not.
     *
     * @return void
     */
    public function test_the_hash_reads_code_not_comments(): void {
        $root = make_request_directory();
        $write = function (string $body, string $condition = 'false') use ($root): void {
            check_dir_exists($root . '/availability/condition/fake/classes');
            file_put_contents($root . '/lib.php', "<?php\nclass thing {\n$body\n}\nfunction loose() { return 1; }\n");
            file_put_contents($root . '/availability/condition/fake/classes/condition.php', "<?php\nnamespace availability_fake;"
                . "\nclass condition {\n    public function is_applied_to_user_lists() {\n        return $condition;\n    }\n}\n");
        };
        $manifest = ['audited' => 'test', 'listconditions' => [], 'symbols' => [
            ['file' => 'lib.php', 'class' => 'thing', 'function' => 'count', 'hash' => null],
            ['file' => 'lib.php', 'class' => null, 'function' => 'loose', 'hash' => null],
        ]];

        $write("    public function count(\$a) {\n        return \$a + 1;\n    }");
        $manifest = core_fingerprint::rebuild($root, $manifest, 'test');
        $this->assertSame([], core_fingerprint::differences($root, $manifest));

        $write("    /** Counts. */\n    public function count(\$a) { // One more.\n  return   \$a + 1;   }");
        $this->assertSame([], core_fingerprint::differences($root, $manifest));

        $write("    public function count(\$a) {\n        return \$a + 2;\n    }");
        $this->assertSame(['thing::count() changed in lib.php'], core_fingerprint::differences($root, $manifest));

        $write("    public function other(\$a) {\n        return \$a + 1;\n    }");
        $this->assertSame(['thing::count() is gone from lib.php'], core_fingerprint::differences($root, $manifest));

        $write("    public function count(\$a) {\n        return \$a + 1;\n    }", 'true');
        $expected = ['the condition types that filter user lists are now [fake], were []'];
        $this->assertSame($expected, core_fingerprint::differences($root, $manifest));
    }
}
