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
 * The Competency hub's export modals keep their loader's label from one download to the next.
 *
 * An export loader is a role=status span holding a visually hidden label (the markup half is
 * stylesheet_markup_contract_test::test_export_loaders_announce_the_wait_and_can_hide). The
 * download function adds a spinner beside that label while the call runs. Emptying the loader
 * afterwards takes the label with the spinner, so from the second export in the same modal the
 * status region has nothing to announce. The plugin has no JavaScript test runner, so the
 * functions are read out of amd/src/central with their comments dropped.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class export_loader_script_test extends \basic_testcase {
    /** @var array Module => the function that shows and hides its export loader, and the template it fills. */
    private const DOWNLOADS = [
        'frameworks' => ['function' => 'downloadFramework', 'template' => 'frameworks_export'],
        'plans_transfer' => ['function' => 'downloadTemplates', 'template' => 'plans_export'],
    ];

    /**
     * Every module that selects an export loader is one this test reads.
     *
     * Change that must make it fail: select the export loader in another amd/src/central module
     * without adding it to DOWNLOADS.
     *
     * @return void
     */
    public function test_every_export_loader_module_is_read(): void {
        $found = [];
        foreach (glob($this->plugin_root() . '/amd/src/central/*.js') as $path) {
            if (str_contains($this->code((string) file_get_contents($path)), 'data-region="export-loader"')) {
                $found[] = basename($path, '.js');
            }
        }
        sort($found);
        $expected = array_keys(self::DOWNLOADS);
        sort($expected);
        $this->assertSame($expected, $found, 'A module selecting the export loader is missing from DOWNLOADS.');
    }

    /**
     * The loader each download fills carries a label, which is what an emptied loader loses.
     *
     * The precondition of the test below: without a label inside the loader, emptying it would
     * delete nothing that matters.
     *
     * @return void
     */
    public function test_each_loader_holds_a_label(): void {
        foreach (self::DOWNLOADS as $module => $download) {
            $path = $this->plugin_root() . '/templates/central/' . $download['template'] . '.mustache';
            $this->assertFileExists($path);
            $this->assertMatchesRegularExpression(
                '#<span data-region="export-loader"[^>]*>\s*<span class="visually-hidden">\{\{\#str\}\}[^{]+\{\{/str\}\}</span>#',
                (string) file_get_contents($path),
                $download['template'] . ' (filled by ' . $module . ') has no label inside its export loader.'
            );
        }
    }

    /**
     * A download removes the spinner it added, and nothing empties the loader.
     *
     * Changes that must make it fail: put loader.replaceChildren() back in downloadFramework's
     * finally block; drop spinner.remove() from either function.
     *
     * @return void
     */
    public function test_a_download_removes_only_its_own_spinner(): void {
        foreach (self::DOWNLOADS as $module => $download) {
            $source = $this->source($module);
            $body = $this->body($source, $download['function']);
            $this->assertMatchesRegularExpression(
                '/const loader = body\.querySelector\((?:\'\[data-region="export-loader"\]\'|SELECTORS\.loader)\);/',
                $body,
                $module . ': the loader is not held in the variable this test reads.'
            );
            $this->assert_in_order(
                $body,
                ['const spinner = makeSpinner();', 'loader.append(spinner);', '} finally {', 'spinner.remove();'],
                $module
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\bloader\.(?:replaceChildren\(|innerHTML\s*=|textContent\s*=|innerText\s*=|removeChild\()/',
                $source,
                $module . ' empties the export loader, which deletes its visually hidden label.'
            );
        }
    }

    /**
     * Absolute path to the plugin root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * An amd/src/central module's code, comments dropped.
     *
     * @param string $module The module name, e.g. frameworks.
     * @return string
     */
    private function source(string $module): string {
        $path = $this->plugin_root() . '/amd/src/central/' . $module . '.js';
        $this->assertFileExists($path);
        return $this->code((string) file_get_contents($path));
    }

    /**
     * The body of a top-level const-declared arrow function, from its opening brace to the closing one.
     *
     * The modules close a top-level function with a brace in column one followed by a semicolon,
     * so the first such line after the declaration ends it.
     *
     * @param string $source The module source.
     * @param string $name The function name.
     * @return string
     */
    private function body(string $source, string $name): string {
        $pattern = '/\n(?:export )?const ' . preg_quote($name, '/') . ' = (?:async)?\(?[^\n]*?\)? => \{\n(.*?)\n\};/s';
        $this->assertSame(1, preg_match($pattern, $source, $match), "Function {$name} was not found.");
        return $match[1];
    }

    /**
     * The code without its comments, so an assertion reads what runs rather than what is said about it.
     *
     * @param string $js JavaScript source.
     * @return string
     */
    private function code(string $js): string {
        $js = (string) preg_replace('#/\*.*?\*/#s', '', $js);
        return (string) preg_replace('#(^|[^:])//[^\n]*#m', '$1', $js);
    }

    /**
     * Assert that the fragments occur in the text, each after the previous one.
     *
     * @param string $text The text to search.
     * @param array $fragments Literal fragments, in the order they must appear.
     * @param string $where Label for the failure message.
     * @return void
     */
    private function assert_in_order(string $text, array $fragments, string $where): void {
        $offset = 0;
        foreach ($fragments as $fragment) {
            $position = strpos($text, $fragment, $offset);
            $this->assertNotFalse($position, "{$where}: '{$fragment}' was not found in order.");
            $offset = $position + strlen($fragment);
        }
    }
}
