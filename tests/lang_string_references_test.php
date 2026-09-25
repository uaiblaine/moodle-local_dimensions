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
 * Every string key the shipped code names literally exists in both language files.
 *
 * A missing key surfaces only when the page asking for it renders: get_string() then raises a
 * developer debugging notice, and string_for_js() defers that lookup to the page footer, which no
 * test stopping short of the output reaches. phpcs, validate and the mustache lint never compare a
 * key with the language files. Keys built at run time are out of reach here, which is one more
 * reason to write each key as a literal.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class lang_string_references_test extends \basic_testcase {
    /** @var array File extension => patterns whose first group is a key of this plugin. */
    private const PATTERNS = [
        'php' => [
            '/\b(?:get_string|string_for_js|lang_string)\(\s*[\'"]([\w:\/.-]+)[\'"]\s*,\s*[\'"]local_dimensions[\'"]/',
        ],
        'mustache' => [
            '/\{\{#(?:str|cleanstr)\}\}\s*([\w:\/.-]+)\s*,\s*local_dimensions\b/',
        ],
        'js' => [
            '/\bkey:\s*\'([\w:\/.-]+)\'\s*,\s*component:\s*\'local_dimensions\'/',
            '/\b(?:getString|get_string)\(\s*\'([\w:\/.-]+)\'\s*,\s*\'local_dimensions\'/',
        ],
    ];

    /** @var array Paths below the plugin root that ship no code of their own. */
    private const SKIPPED = ['.git', 'amd/build', 'docs', 'lang', 'node_modules', 'tests', 'vendor'];

    /**
     * No literal key is missing from either language file.
     *
     * The controls come first: one known reference for each form the patterns read, so a pattern or
     * a directory that stopped being read fails here instead of passing with nothing to check.
     *
     * Change that must make it fail: delete $string['evidence_type_file'], which view-plan.php
     * passes to string_for_js(), from either language file.
     *
     * @return void
     */
    public function test_every_literal_key_is_defined_in_both_languages(): void {
        $references = $this->references();
        $controls = [
            'view-plan.php' => 'rating_label',
            'classes/local/category_lifecycle.php' => 'central_categorydelete_contents',
            'classes/reportbuilder/local/entities/plan.php' => 'entityname_plan',
            'templates/central/rule_config.mustache' => 'central_rule_invalidpoints',
            'amd/src/accordion.js' => 'evidence_type_manual',
            'amd/src/central/frameworks.js' => 'central_frameworks_export_done',
        ];
        foreach ($controls as $file => $key) {
            $this->assertContains($file, $references[$key] ?? [], "Control: {$key} was not read from {$file}.");
        }

        foreach (['en', 'pt_br'] as $lang) {
            $strings = $this->strings($lang);
            $missing = [];
            foreach ($references as $key => $files) {
                if (!array_key_exists($key, $strings)) {
                    $missing[] = $key . ' (' . implode(', ', array_unique($files)) . ')';
                }
            }
            $this->assertSame([], $missing, "Keys missing from lang/{$lang}: " . implode('; ', $missing));
        }
    }

    /**
     * The literal keys the shipped code names, with the files naming each.
     *
     * @return array Key => relative paths of the files that name it.
     */
    private function references(): array {
        $root = dirname(__DIR__);
        $references = [];
        foreach ($this->shipped_files($root) as $relative) {
            $source = (string) file_get_contents($root . '/' . $relative);
            foreach (self::PATTERNS[pathinfo($relative, PATHINFO_EXTENSION)] as $pattern) {
                preg_match_all($pattern, $source, $matches);
                foreach ($matches[1] as $key) {
                    $references[$key][] = $relative;
                }
            }
        }
        return $references;
    }

    /**
     * The files the patterns read, relative to the plugin root.
     *
     * @param string $root Absolute path to the plugin root.
     * @return array Relative paths.
     */
    private function shipped_files(string $root): array {
        $filter = static function (\SplFileInfo $file) use ($root): bool {
            return !in_array(substr($file->getPathname(), strlen($root) + 1), self::SKIPPED, true);
        };
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                $filter
            )
        );
        $files = [];
        foreach ($iterator as $file) {
            if (isset(self::PATTERNS[$file->getExtension()])) {
                $files[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($files);
        return $files;
    }

    /**
     * The strings a language file of the plugin declares.
     *
     * @param string $lang The language directory name.
     * @return array String key => text.
     */
    private function strings(string $lang): array {
        $string = [];
        include(dirname(__DIR__) . '/lang/' . $lang . '/local_dimensions.php');
        return $string;
    }
}
