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
 * Keeps site-wide style recalculation off the plugin's stylesheets.
 *
 * Moodle bundles every plugin's styles.css into the theme sheet, so each rule here is matched on
 * every page of the site. A :has() whose argument reads the class or style attribute
 * (`:has(.modal-body [class*='local-dimensions-'])`) makes Chrome recheck that :has() on every
 * class or style change of any element in the document, including classes no rule names. The
 * modals' close-button chip therefore keys on the plugin pages' body classes and on a marker class
 * every modal factory adds to the modal root, and this suite holds both halves: no such :has() in a
 * shipped sheet, and every modal the plugin creates carries its marker.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class selector_cost_test extends \basic_testcase {
    /**
     * Absolute path to the plugin root.
     *
     * @return string Plugin directory without a trailing separator.
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * The arguments of every :has() in a stylesheet that read the class or style attribute.
     *
     * The argument is cut at its balancing parenthesis, so an attribute selector nested in a
     * :not() or :is() inside the :has() is found too.
     *
     * @param string $css Stylesheet source.
     * @return array List of offending :has() arguments, whitespace collapsed.
     */
    private function offending_has_arguments(string $css): array {
        $css = preg_replace('~/\*.*?\*/~s', '', $css);
        $offending = [];
        $offset = 0;
        while (($start = stripos($css, ':has(', $offset)) !== false) {
            $open = $start + strlen(':has(');
            $depth = 1;
            $i = $open;
            $length = strlen($css);
            while ($i < $length && $depth > 0) {
                if ($css[$i] === '(') {
                    $depth++;
                } else if ($css[$i] === ')') {
                    $depth--;
                }
                $i++;
            }
            $argument = substr($css, $open, $i - $open - 1);
            if (preg_match('/\[\s*(class|style)\s*([~|^$*]?=|\])/i', $argument)) {
                $offending[] = preg_replace('/\s+/', ' ', trim($argument));
            }
            $offset = $open;
        }
        return $offending;
    }

    /**
     * The scan finds an attribute selector at any depth of the argument and nothing else.
     *
     * Without this arm a scan that matched nothing would keep the stylesheet test green.
     *
     * @return void
     */
    public function test_the_scan_finds_class_and_style_attributes_at_any_depth(): void {
        $css = ".a:has(.b [class*='x-']) .c {}\n"
            . ".a:has(:not([ STYLE ])) .c {}\n"
            . "/* .a:has([class]) .c {} */\n"
            . ".a:has(.b, [data-class='x']) .c {}\n"
            . ".a:has([classname]) .c {}\n"
            . ".a:not([class*='x']) .c {}\n";
        $this->assertSame([".b [class*='x-']", ':not([ STYLE ])'], $this->offending_has_arguments($css));
    }

    /**
     * No shipped stylesheet has a :has() whose argument reads the class or style attribute.
     *
     * Core loads every styles*.css at the plugin root (styles_<theme>.css for each theme in the
     * active chain), so all of them count.
     *
     * @return void
     */
    public function test_no_has_argument_reads_the_class_or_style_attribute(): void {
        $sheets = glob($this->plugin_root() . '/*.css');
        $this->assertContains($this->plugin_root() . '/styles.css', $sheets);
        foreach ($sheets as $sheet) {
            $this->assertSame(
                [],
                $this->offending_has_arguments(file_get_contents($sheet)),
                basename($sheet) . ': key the rule on a class the plugin adds instead of a :has() over the attribute'
            );
        }
    }

    /**
     * Every modal the plugin creates gets a marker class on its root, and the chip keys on it.
     *
     * The marker is added right after the factory resolves: .local-dimensions-modal takes the
     * close-button chip, .local-dimensions-related-modal (the referenced-competency modal, whose
     * header is hidden) keeps its own button. A new modal without either loses the chip outside the
     * plugin's pages; on them, every modal wears it through the page's body class, core's confirm
     * dialogues included.
     *
     * @return void
     */
    public function test_every_plugin_modal_carries_a_marker_class(): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->plugin_root() . '/amd/src')
        );
        $sites = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'js') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            preg_match_all(
                '/\b(Modal|ModalSaveCancel|ModalDeleteCancel|ModalCancel)\.create\(/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE
            );
            foreach ($matches[0] as [$call, $offset]) {
                // Step past the factory call's arguments to the statement that follows it.
                $depth = 1;
                $i = $offset + strlen($call);
                while ($i < strlen($source) && $depth > 0) {
                    if ($source[$i] === '(') {
                        $depth++;
                    } else if ($source[$i] === ')') {
                        $depth--;
                    }
                    $i++;
                }
                $line = substr_count($source, "\n", 0, $offset) + 1;
                $sites[basename($file->getPathname()) . ':' . $line] = substr($source, $i, 200);
            }
        }
        $this->assertGreaterThanOrEqual(15, count($sites));
        foreach ($sites as $site => $after) {
            $this->assertMatchesRegularExpression(
                "/addClass\('local-dimensions-(related-)?modal'\)|classList\.add\('local-dimensions-modal'\)/",
                $after,
                "$site: add .local-dimensions-modal to the modal root right after it is created"
            );
        }

        $css = preg_replace('~/\*.*?\*/~s', '', file_get_contents($this->plugin_root() . '/styles.css'));
        $this->assertStringContainsString(".modal.local-dimensions-modal .modal-header .btn-close,\n", $css);
        $this->assertStringContainsString(".modal.local-dimensions-modal .modal-header .btn-close::before,\n", $css);
        foreach (['central-page', 'viewplan', 'viewcompetency'] as $page) {
            $this->assertStringContainsString(
                ".local-dimensions-$page .modal:not(.local-dimensions-related-modal) .modal-header .btn-close::before",
                $css
            );
        }
    }
}
