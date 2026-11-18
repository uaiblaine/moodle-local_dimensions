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
 * Tests for the unaccent status check, the has_unaccent() memo and the administrator's create request.
 *
 * The PostgreSQL arms skip on other databases and the other-database arms skip on PostgreSQL, so
 * each leg of the matrix runs the arms its database can reach. PostgreSQL rolls back DDL with each
 * test's transaction, so the extension's state is set up inside the test and never leaks.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\check;

use advanced_testcase;
use core\check\result;
use local_dimensions\helper;
use local_dimensions\local\unaccent_admin;

/**
 * Unit tests for {@see unaccent}, the memo in {@see helper::has_unaccent()} and {@see unaccent_admin}.
 * @covers     \local_dimensions\check\unaccent
 * @covers     \local_dimensions\local\unaccent_admin
 * @covers     \local_dimensions\helper::has_unaccent
 * @covers     \local_dimensions\helper::ensure_unaccent
 * @covers     \local_dimensions\helper::reset_unaccent_memo
 */
final class unaccent_test extends advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        helper::reset_unaccent_memo();
    }

    protected function tearDown(): void {
        // The rolled back transaction takes the extension with it; the memo must not outlive it.
        helper::reset_unaccent_memo();
        parent::tearDown();
    }

    /**
     * Skip a PostgreSQL-only arm on other databases.
     *
     * @return void
     */
    private function require_postgres(): void {
        global $DB;
        if ($DB->get_dbfamily() !== 'postgres') {
            $this->markTestSkipped('PostgreSQL-only arm: this database relies on the collation.');
        }
    }

    /**
     * Make the extension exist, or skip when this database account may not create it.
     *
     * @return void
     */
    private function require_extension(): void {
        $this->require_postgres();
        if (!helper::ensure_unaccent()) {
            $this->markTestSkipped('The unaccent extension cannot be created in this environment.');
        }
    }

    /**
     * Make the extension absent, or skip when this database account may not drop it.
     *
     * @return void
     */
    private function require_no_extension(): void {
        global $DB;
        $this->require_postgres();
        if (helper::has_unaccent()) {
            try {
                $DB->execute('DROP EXTENSION unaccent');
            } catch (\Throwable $e) {
                $this->markTestSkipped('The unaccent extension cannot be dropped in this environment.');
            }
            helper::reset_unaccent_memo();
        }
        $this->assertFalse(helper::has_unaccent());
    }

    /**
     * Other databases are not applicable: the collation does the work.
     *
     * @return void
     */
    public function test_result_is_na_outside_postgres(): void {
        global $DB;
        $this->resetAfterTest();
        if ($DB->get_dbfamily() === 'postgres') {
            $this->markTestSkipped('Non-PostgreSQL arm: this database is PostgreSQL.');
        }

        $result = (new unaccent())->get_result();

        $this->assertSame(result::NA, $result->get_status());
        $this->assertSame(get_string('checkunaccent_na', 'local_dimensions'), $result->get_summary());
    }

    /**
     * An installed extension is OK.
     *
     * @return void
     */
    public function test_result_is_ok_when_installed(): void {
        $this->resetAfterTest();
        $this->require_extension();

        $result = (new unaccent())->get_result();

        $this->assertSame(result::OK, $result->get_status());
        $this->assertSame(get_string('checkunaccent_ok', 'local_dimensions'), $result->get_summary());
    }

    /**
     * A missing extension is a warning, and the details name the way out.
     *
     * @return void
     */
    public function test_result_is_warning_when_missing(): void {
        $this->resetAfterTest();
        $this->require_no_extension();

        $result = (new unaccent())->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $this->assertSame(get_string('checkunaccent_warning', 'local_dimensions'), $result->get_summary());
        $this->assertStringContainsString('CREATE EXTENSION unaccent', $result->get_details());
    }

    /**
     * The action link opens the plugin settings at the unaccent row.
     *
     * @return void
     */
    public function test_action_link_points_to_the_settings_section(): void {
        $this->resetAfterTest();

        $url = (new unaccent())->get_action_link()->url;

        $this->assertTrue($url->compare(new \moodle_url('/admin/settings.php'), URL_MATCH_BASE));
        $this->assertSame('local_dimensions_settings', $url->get_param('section'));
        $this->assertStringEndsWith('#admin-unaccent', $url->out(false));
    }

    /**
     * The plugin's callback hands core the check.
     *
     * @return void
     */
    public function test_status_checks_callback_returns_the_check(): void {
        $this->resetAfterTest();

        $checks = local_dimensions_status_checks();

        $this->assertCount(1, $checks);
        $this->assertInstanceOf(unaccent::class, $checks[0]);
    }

    /**
     * has_unaccent() answers from the memo until it is reset; the reset makes it read the database again.
     *
     * @return void
     */
    public function test_memo_holds_until_reset(): void {
        global $DB;
        $this->resetAfterTest();
        $this->require_extension();

        $this->assertTrue(helper::has_unaccent());
        try {
            $DB->execute('DROP EXTENSION unaccent');
        } catch (\Throwable $e) {
            $this->markTestSkipped('The unaccent extension cannot be dropped in this environment.');
        }

        $this->assertTrue(helper::has_unaccent(), 'The memo answers, not the database.');
        helper::reset_unaccent_memo();
        $this->assertFalse(helper::has_unaccent(), 'After the reset the database is read again.');
    }

    /**
     * ensure_unaccent() forgets a stale "missing" answer, so the state it created is seen at once.
     *
     * @return void
     */
    public function test_ensure_resets_the_memo(): void {
        $this->resetAfterTest();
        $this->require_no_extension();
        $this->assertFalse(helper::has_unaccent());

        if (!helper::ensure_unaccent()) {
            $this->markTestSkipped('The unaccent extension cannot be created in this environment.');
        }

        $this->assertTrue(helper::has_unaccent());
    }

    /**
     * A user without moodle/site:config cannot ask for the extension.
     *
     * @return void
     */
    public function test_create_requires_site_config(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $_POST['sesskey'] = sesskey();

        $this->expectException(\required_capability_exception::class);
        unaccent_admin::create();
    }

    /**
     * An administrator without a valid session key is refused.
     *
     * @return void
     */
    public function test_create_requires_sesskey(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = 'not-the-session-key';

        $this->expectException(\moodle_exception::class);
        unaccent_admin::create();
    }

    /**
     * An administrator with a session key gets the extension, or false where it cannot exist.
     *
     * @return void
     */
    public function test_create_reports_the_outcome(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $_POST['sesskey'] = sesskey();

        if ($DB->get_dbfamily() !== 'postgres') {
            $this->assertFalse(unaccent_admin::create());
            return;
        }
        $this->require_no_extension();

        $created = unaccent_admin::create();

        $this->assertSame(helper::has_unaccent(), $created);
    }
}
