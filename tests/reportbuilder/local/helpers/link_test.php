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

declare(strict_types=1);

namespace local_dimensions\reportbuilder\local\helpers;

use moodle_url;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the report-builder link helper.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(link::class)]
final class link_test extends \advanced_testcase {
    /**
     * A page link escapes its text and its address once each.
     *
     * @return void
     */
    public function test_page_link_escapes_once(): void {
        $this->resetAfterTest();

        $html = link::to_page(new moodle_url('/admin/tool/lp/plan.php', ['id' => 3, 'x' => 'a&b']), 'Plan <b> & more');

        $this->assertStringContainsString('plan.php?id=3&amp;x=a%26b"', $html);
        $this->assertStringContainsString('>Plan &lt;b&gt; &amp; more</a>', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    /**
     * A stored address with an unsafe scheme is text, a web address is a link, and nothing stays nothing.
     *
     * @return void
     */
    public function test_stored_url(): void {
        $this->resetAfterTest();

        $this->assertSame('', link::to_stored_url(null));
        $this->assertSame('', link::to_stored_url(''));
        $this->assertSame('javascript:alert(&quot;x&quot;)', link::to_stored_url('javascript:alert("x")'));
        $this->assertStringContainsString('<a href="https://example.com/a?b=1"', link::to_stored_url('https://example.com/a?b=1'));
    }
}
