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
 * The names the cache administration page shows for this plugin's cache definitions.
 *
 * Core names each definition with the cachedef_ string of its key, and nothing in the pipeline
 * reads db/caches.php against the language files: a missing string is a debugging notice on the
 * cache page, and a name describing the wrong mode misleads the administrator choosing a store.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class cache_definition_names_test extends \basic_testcase {
    /**
     * Every definition has its cachedef_ string in both languages.
     *
     * Change that must make it fail: delete cachedef_fontawesome_iconmap from either language file.
     *
     * @return void
     */
    public function test_every_definition_is_named_in_both_languages(): void {
        $definitions = $this->definitions();
        $this->assertArrayHasKey('fontawesome_iconmap', $definitions);
        $missing = [];
        foreach (['en', 'pt_br'] as $lang) {
            $strings = $this->strings($lang);
            foreach (array_keys($definitions) as $name) {
                if (!isset($strings['cachedef_' . $name])) {
                    $missing[] = $lang . ': cachedef_' . $name;
                }
            }
        }
        $this->assertSame([], $missing, 'Cache definitions without a name: ' . implode('; ', $missing));
    }

    /**
     * Only a session-mode definition is named as a session cache.
     *
     * The returncontext definition is the control: it is session-mode and says so in both
     * languages, so the match below is shown to find the word where it belongs.
     *
     * Change that must make it fail: put "Session cache for" back into cachedef_plan_trail, which
     * is an application cache, in either language.
     *
     * @return void
     */
    public function test_only_a_session_definition_is_named_as_one(): void {
        $patterns = ['en' => '/\bsession\b/i', 'pt_br' => '/\bsess(?:ão|ao)\b/iu'];
        $definitions = $this->definitions();
        $this->assertSame(\cache_store::MODE_SESSION, $definitions['returncontext']['mode']);
        $offenders = [];
        foreach ($patterns as $lang => $pattern) {
            $strings = $this->strings($lang);
            $this->assertMatchesRegularExpression($pattern, $strings['cachedef_returncontext'], "Control ({$lang}).");
            foreach ($definitions as $name => $definition) {
                $named = (bool) preg_match($pattern, $strings['cachedef_' . $name] ?? '');
                if ($named && $definition['mode'] !== \cache_store::MODE_SESSION) {
                    $offenders[] = $lang . ': cachedef_' . $name;
                }
            }
        }
        $this->assertSame(
            [],
            $offenders,
            'These name a session cache, but the definition is not one: ' . implode('; ', $offenders)
        );
    }

    /**
     * The cache definitions the plugin declares.
     *
     * @return array Definition name => definition.
     */
    private function definitions(): array {
        $definitions = [];
        include(dirname(__DIR__) . '/db/caches.php');
        return $definitions;
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
