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

namespace local_dimensions\external;

use core_external\external_api;
use local_dimensions\fixtures\panel_sections_listener;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for local_dimensions_get_competency_panel_sections: its refusals first, each beside a call that answers,
 * then what a listener's sections become and what the call costs without one.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_dimensions\external\get_competency_panel_sections::class)]
final class get_competency_panel_sections_test extends \advanced_testcase {
    /** @var string The web service under test. */
    private const string WSNAME = 'local_dimensions_get_competency_panel_sections';

    /**
     * Two course categories, each with a framework holding one competency.
     *
     * @return array Keys: catcontext (the first category's context), compa (a competency of the first category's
     *     framework), compb (one of the second's), frameworka, contexta.
     */
    private function fixture(): array {
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        require_once(__DIR__ . '/../fixtures/hook/panel_sections_listener.php');
        panel_sections_listener::reset();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $cata = \context_coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $catb = \context_coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $frameworka = $ccg->create_framework(['contextid' => $cata->id]);
        $frameworkb = $ccg->create_framework(['contextid' => $catb->id]);
        return [
            'catcontext' => $cata,
            'frameworka' => (int) $frameworka->get('id'),
            'compa' => (int) $ccg->create_competency(['competencyframeworkid' => $frameworka->get('id')])->get('id'),
            'compb' => (int) $ccg->create_competency(['competencyframeworkid' => $frameworkb->get('id')])->get('id'),
        ];
    }

    /**
     * Put a hook manager with the test listener, or with none, in place of the site's.
     *
     * @param bool $listener Whether the test listener is registered.
     * @return void
     */
    private function listen(bool $listener): void {
        $files = $listener ? ['local_dimensions' => __DIR__ . '/../fixtures/hook/panel_sections_hooks.php'] : [];
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance($files));
    }

    /**
     * Call the service as the current user, the way the browser does.
     *
     * @param int $competencyid The competency.
     * @return array The response: error, and data or exception.
     */
    private function call(int $competencyid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(self::WSNAME, ['competencyid' => $competencyid]);
    }

    /**
     * Assert a call was refused with an error code.
     *
     * @param array $response The response.
     * @param string $errorcode The expected error code.
     * @return void
     */
    private function assert_refused(array $response, string $errorcode): void {
        $this->assertTrue($response['error'], json_encode($response));
        $this->assertSame($errorcode, $response['exception']->errorcode);
    }

    /**
     * Assert a call answered, and return its data.
     *
     * @param array $response The response.
     * @return array The data.
     */
    private function answered(array $response): array {
        $this->assertFalse($response['error'], json_encode($response));
        return $response['data'];
    }

    /**
     * Drop the sesskey the calls plant in $_POST, which resetAfterTest() does not restore.
     *
     * @return void
     */
    #[\Override]
    protected function tearDown(): void {
        unset($_POST['sesskey']);
        parent::tearDown();
    }

    /**
     * A visitor not logged in is refused by the AJAX door (servicerequireslogin), and by the service's own
     * validate_context() when reached directly (requireloginerror); a logged-in user is the control, and no listener
     * runs for the visitor.
     *
     * @return void
     */
    public function test_not_logged_in_is_refused(): void {
        $f = $this->fixture();
        $this->listen(true);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame($f['compb'], $this->answered($this->call($f['compb']))['competencyid'], 'Control: a user');
        $this->assertCount(1, panel_sections_listener::$seen);

        $this->setUser(null);
        $this->assert_refused($this->call($f['compb']), 'servicerequireslogin');
        try {
            get_competency_panel_sections::execute($f['compb']);
            $this->fail('The service answered a visitor who is not logged in.');
        } catch (\require_login_exception $e) {
            $this->assertSame('requireloginerror', $e->errorcode);
        }
        $this->assertCount(1, panel_sections_listener::$seen);
    }

    /**
     * A guest is refused even when the guest role may read competencies; an authenticated user answers, and the
     * listener runs for the authenticated user only.
     *
     * @return void
     */
    public function test_a_guest_is_refused_even_with_the_read_right(): void {
        global $DB;
        $f = $this->fixture();
        $this->listen(true);
        $guestrole = (int) $DB->get_field('role', 'id', ['shortname' => 'guest'], MUST_EXIST);
        assign_capability('moodle/competency:competencyview', CAP_ALLOW, $guestrole, \context_system::instance()->id, true);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setGuestUser();
        $this->assertTrue(has_capability('moodle/competency:competencyview', $f['catcontext']), 'Precondition: the guest reads');
        $this->assert_refused($this->call($f['compb']), 'noguest');
        $this->assertCount(0, panel_sections_listener::$seen);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->answered($this->call($f['compb']));
        $this->assertCount(1, panel_sections_listener::$seen);
    }

    /**
     * A user who may not read competencies in the framework's category is refused, and no listener runs; a
     * competency of the other category answers.
     *
     * @return void
     */
    public function test_no_read_right_in_the_framework_context_is_refused(): void {
        global $DB;
        $f = $this->fixture();
        $this->listen(true);
        $userrole = (int) $DB->get_field('role', 'id', ['shortname' => 'user'], MUST_EXIST);
        assign_capability('moodle/competency:competencyview', CAP_PROHIBIT, $userrole, $f['catcontext']->id, true);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->assert_refused($this->call($f['compa']), 'nopermissions');
        $this->assertCount(0, panel_sections_listener::$seen);
        $this->assertSame($f['compb'], $this->answered($this->call($f['compb']))['competencyid']);
        $this->assertCount(1, panel_sections_listener::$seen);
    }

    /**
     * A missing competency is refused; an existing one answers.
     *
     * @return void
     */
    public function test_a_missing_competency_is_refused(): void {
        $f = $this->fixture();
        $this->listen(true);
        $this->setAdminUser();
        $this->assert_refused($this->call($f['compb'] + 1000), 'invalidrecord');
        $this->assertSame($f['compb'], $this->answered($this->call($f['compb']))['competencyid']);
    }

    /**
     * With competencies disabled on the site the call is refused before any listener runs; enabled, it answers.
     *
     * @return void
     */
    public function test_disabled_competencies_are_refused(): void {
        $f = $this->fixture();
        $this->listen(true);
        $this->setAdminUser();
        set_config('enabled', 0, 'core_competency');
        $this->assert_refused($this->call($f['compa']), 'competenciesarenotenabled');
        $this->assertCount(0, panel_sections_listener::$seen);
        set_config('enabled', 1, 'core_competency');
        $this->answered($this->call($f['compa']));
        $this->assertCount(1, panel_sections_listener::$seen);
    }

    /**
     * The listener receives the competency, the framework and its context as the competency's row says, and whether
     * the viewer manages there; its sections arrive in the order added, through the declared returns.
     *
     * @return void
     */
    public function test_the_listener_sections_arrive_in_order(): void {
        global $CFG;
        $f = $this->fixture();
        $this->listen(true);
        panel_sections_listener::$answers = [
            [
                'local_dimensions',
                'first',
                'First <b>section</b>',
                [['label' => 'Kind', 'value' => 'Regulatory', 'tone' => 'info']],
                'A note',
                '/local/dimensions/central.php',
                'Open',
            ],
            ['tool_lp', 'second', 'Second', [['label' => 'Set by', 'value' => 'Default']]],
        ];
        $this->setAdminUser();
        $data = $this->answered($this->call($f['compa']));

        $this->assertSame($f['compa'], $data['competencyid']);
        $this->assertSame([
            [
                'key' => 'local_dimensions-first',
                'component' => 'local_dimensions',
                'title' => 'First section',
                'rows' => [['label' => 'Kind', 'value' => 'Regulatory', 'tone' => 'info']],
                'note' => 'A note',
                'linklabel' => 'Open',
                'linkurl' => $CFG->wwwroot . '/local/dimensions/central.php',
            ],
            [
                'key' => 'tool_lp-second',
                'component' => 'tool_lp',
                'title' => 'Second',
                'rows' => [['label' => 'Set by', 'value' => 'Default', 'tone' => '']],
                'note' => '',
                'linklabel' => '',
                'linkurl' => '',
            ],
        ], $data['sections']);

        $hook = panel_sections_listener::$seen[0];
        $this->assertSame($f['compa'], $hook->get_competencyid());
        $this->assertSame($f['frameworka'], $hook->get_frameworkid());
        $this->assertSame($f['catcontext']->id, $hook->get_context()->id);
        $this->assertTrue($hook->can_manage());
    }

    /**
     * A reader of the framework who does not manage it is told so: the hook says can_manage() false.
     *
     * @return void
     */
    public function test_a_reader_is_not_told_they_manage(): void {
        $f = $this->fixture();
        $this->listen(true);
        $this->setUser($this->getDataGenerator()->create_user());
        $this->answered($this->call($f['compa']));
        $this->assertFalse(panel_sections_listener::$seen[0]->can_manage());
    }

    /**
     * With no listener the answer is empty, and a listener that adds nothing costs no statement more than none:
     * the dispatch itself reads nothing.
     *
     * @return void
     */
    public function test_no_listener_answers_empty_and_costs_nothing(): void {
        global $DB;
        $f = $this->fixture();
        $this->setAdminUser();

        $this->listen(false);
        // Warm the caches every call shares (contexts, the function's description, the access data).
        $this->answered($this->call($f['compa']));
        $before = $DB->perf_get_reads();
        $data = $this->answered($this->call($f['compa']));
        $withoutlistener = $DB->perf_get_reads() - $before;
        $this->assertSame([], $data['sections']);

        $this->listen(true);
        $this->answered($this->call($f['compa']));
        $before = $DB->perf_get_reads();
        $data = $this->answered($this->call($f['compa']));
        $withlistener = $DB->perf_get_reads() - $before;
        $this->assertSame([], $data['sections']);

        // The control: the listener ran, and both calls read something (the guards).
        $this->assertCount(2, panel_sections_listener::$seen);
        $this->assertGreaterThan(0, $withoutlistener);
        $this->assertSame($withoutlistener, $withlistener);
    }
}
