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
 * Guards that every method naming completion_info loads lib/completionlib.php itself.
 *
 * Core does not autoload completion_info. A web service request carrying one service alone has
 * only what that service loads, so a method that leans on an earlier call having loaded the file
 * fails there with "Class not found", while every PHPUnit process has the file already: the
 * bootstrap includes each module's lib.php, and mod_glossary's requires completionlib. No
 * behavioural test can go red for this, so the source is scanned instead.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_courses_completion_status
 * @covers     \local_dimensions\calculator
 */
final class completionlib_require_test extends \basic_testcase {
    /**
     * Split a PHP file into its function bodies, comments left out.
     *
     * @param string $path Absolute path of the file.
     * @return array Function name => the code of its body.
     */
    private function function_bodies(string $path): array {
        $tokens = token_get_all(file_get_contents($path));
        $bodies = [];
        $name = null;
        $depth = 0;
        $code = '';
        foreach ($tokens as $i => $token) {
            [$id, $text] = is_array($token) ? $token : [null, $token];
            if ($name === null) {
                if ($id === T_FUNCTION) {
                    for ($j = $i + 1; is_array($tokens[$j]) && $tokens[$j][0] !== T_STRING; $j++) {
                        continue;
                    }
                    $name = is_array($tokens[$j]) ? $tokens[$j][1] : null;
                }
                continue;
            }
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                continue;
            }
            if ($text === ';' && $depth === 0) {
                // An abstract or interface declaration: no body.
                $name = null;
                continue;
            }
            if ($text === '{' || $id === T_CURLY_OPEN || $id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            } else if ($text === '}') {
                $depth--;
                if ($depth === 0) {
                    $bodies[$name] = $code;
                    $name = null;
                    $code = '';
                    continue;
                }
            }
            if ($depth > 0) {
                $code .= $text;
            }
        }
        return $bodies;
    }

    /**
     * Every method under classes/ that names completion_info requires completionlib.php in its own body.
     *
     * @return void
     */
    public function test_methods_naming_completion_info_require_completionlib(): void {
        $root = dirname(__DIR__, 2) . '/classes';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $scanned = 0;
        $missing = [];
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach ($this->function_bodies($file->getPathname()) as $function => $body) {
                if (!preg_match('/\bcompletion_info\b/', $body)) {
                    continue;
                }
                $scanned++;
                if (!preg_match('~require_once\(\$CFG->libdir \. \'/completionlib\.php\'\);~', $body)) {
                    $missing[] = substr($file->getPathname(), strlen($root) + 1) . '::' . $function . '()';
                }
            }
        }

        // The scan must have found the known users, or a broken tokenizer walk would pass vacuously.
        $this->assertGreaterThanOrEqual(5, $scanned);
        $this->assertSame([], $missing, 'These methods use completion_info without requiring lib/completionlib.php');
    }
}
