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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/dimensions/tests/fixtures/unlisted_access_stub.php');
require_once($CFG->dirroot . '/local/dimensions/tests/fixtures/stubbed_unlisted_provider.php');

/**
 * The unlisted provider translates local_unlistedcourses' answers and copies none of its rule.
 *
 * Three layers. The translation is pure and runs on every site. The delegation runs against a
 * stand-in of the plugin's access class, so it too runs everywhere, and proves which calls are made:
 * one batch for a list, the per-course pair for one card. Where the plugin is installed, the literal
 * values the translation maps are held against the plugin's own constants, and the provider is run
 * against the real plugin.
 *
 * The class-level covers tag stays in docblock form on purpose: moodle-cs on the 4.05 leg cannot
 * see PHP attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\local\enrolment_provider_unlisted
 */
final class enrolment_provider_unlisted_test extends \advanced_testcase {
    /** @var int A course id the translation is asked about. */
    private const COURSE = 42;

    /**
     * Forget the stand-in's answers and calls.
     *
     * @return void
     */
    protected function tearDown(): void {
        unlisted_access_stub::reset();
        parent::tearDown();
    }

    /**
     * Every relationship of the plugin's vocabulary but enrolled and none is the card's state.
     *
     * @return void
     */
    public function test_each_relationship_is_the_state_with_its_date(): void {
        $none = ['type' => 'none', 'routes' => []];
        $relationship = static function (string $type): array {
            return ['type' => $type, 'startsat' => 1000, 'endsat' => 2000];
        };

        $this->assertSame(
            ['scheduled', 1000],
            $this->state_and_date(enrolment_provider_unlisted::translate(self::COURSE, $relationship('scheduled'), $none))
        );
        $this->assertSame(
            ['expired', 2000],
            $this->state_and_date(enrolment_provider_unlisted::translate(self::COURSE, $relationship('expired'), $none))
        );
        foreach (['pending', 'waitlisted', 'suspended'] as $type) {
            $this->assertSame(
                [$type, 0],
                $this->state_and_date(enrolment_provider_unlisted::translate(self::COURSE, $relationship($type), $none))
            );
        }
    }

    /**
     * An application leads to the enrolment page, where its own status is; the other relationships lead nowhere.
     *
     * @return void
     */
    public function test_an_application_leads_to_its_status(): void {
        $none = ['type' => 'none', 'routes' => []];
        $enrol = (new \moodle_url('/enrol/index.php', ['id' => self::COURSE]))->out(false);

        foreach (['pending', 'waitlisted'] as $type) {
            $state = enrolment_provider_unlisted::translate(self::COURSE, ['type' => $type], $none);
            $this->assertSame($enrol, $state['actionurl'], $type);
        }
        foreach (['scheduled', 'suspended', 'expired'] as $type) {
            $state = enrolment_provider_unlisted::translate(self::COURSE, ['type' => $type], $none);
            $this->assertNull($state['actionurl'], $type);
        }
    }

    /**
     * A route beside a relationship is the route line, and only when the plugin reports a route.
     *
     * @return void
     */
    public function test_a_route_beside_a_relationship_is_the_route_line(): void {
        $enrol = (new \moodle_url('/enrol/index.php', ['id' => self::COURSE]))->out(false);
        $routes = ['type' => 'open', 'routes' => [['kind' => 'self', 'instanceid' => 3]]];

        foreach (['scheduled', 'pending', 'waitlisted', 'suspended', 'expired'] as $type) {
            $state = enrolment_provider_unlisted::translate(self::COURSE, ['type' => $type], $routes);
            $this->assertSame($enrol, $state['routeurl'], $type);
            // Control: the same relationship with no route has no route line.
            $blocked = ['type' => 'blocked', 'routes' => []];
            $this->assertNull(enrolment_provider_unlisted::translate(self::COURSE, ['type' => $type], $blocked)['routeurl'], $type);
        }
    }

    /**
     * With no relationship the next action is the state, each with its own destination.
     *
     * @return void
     */
    public function test_with_no_relationship_the_next_action_is_the_state(): void {
        $norow = ['type' => 'none', 'startsat' => 0, 'endsat' => 0];
        $enrol = (new \moodle_url('/enrol/index.php', ['id' => self::COURSE]))->out(false);
        $course = (new \moodle_url('/course/view.php', ['id' => self::COURSE]))->out(false);

        $open = enrolment_provider_unlisted::translate(self::COURSE, $norow, ['type' => 'open', 'routes' => [['kind' => 'self']]]);
        $this->assertSame(['open', $enrol], [$open['state'], $open['actionurl']]);

        $guest = ['type' => 'guest', 'routes' => [], 'guest' => 'free'];
        $free = enrolment_provider_unlisted::translate(self::COURSE, $norow, $guest);
        $this->assertSame(['free', $course], [$free['state'], $free['actionurl']]);

        $guest['guest'] = 'key';
        $key = enrolment_provider_unlisted::translate(self::COURSE, $norow, $guest);
        $this->assertSame(['key', $enrol], [$key['state'], $key['actionurl']]);

        $conditional = enrolment_provider_unlisted::translate(self::COURSE, $norow, [
            'type' => 'conditional',
            'routes' => [],
            'conditional' => ['prerequisiteid' => 77, 'instanceid' => 5],
        ]);
        $this->assertSame('conditional', $conditional['state']);
        $this->assertSame(77, $conditional['prerequisiteid']);
        $this->assertSame((new \moodle_url('/course/view.php', ['id' => 77]))->out(false), $conditional['actionurl']);

        foreach (['blocked', 'none', 'unknown', ''] as $type) {
            $this->assertSame(
                enrolment_provider::none_state(self::COURSE),
                enrolment_provider_unlisted::translate(self::COURSE, $norow, ['type' => $type, 'routes' => []]),
                'Next action ' . var_export($type, true)
            );
        }
    }

    /**
     * A conditional answer that names no prerequisite is none, and so is an enrolled answer under a lock.
     *
     * The lock is core's is_enrolled(), so the provider is asked only about a card the viewer cannot
     * open; an enrolled answer there carries no state, and the next action decides.
     *
     * @return void
     */
    public function test_answers_that_name_nothing_read_as_none(): void {
        $none = enrolment_provider::none_state(self::COURSE);
        $conditional = ['type' => 'conditional', 'conditional' => null];
        $this->assertSame($none, enrolment_provider_unlisted::translate(self::COURSE, ['type' => 'none'], $conditional));
        $nothing = ['type' => 'none', 'routes' => []];
        $this->assertSame($none, enrolment_provider_unlisted::translate(self::COURSE, ['type' => 'enrolled'], $nothing));
        // Control: the same enrolled answer beside an open route reads the route.
        $open = ['type' => 'open', 'routes' => [1]];
        $this->assertSame(
            enrolment_provider::STATE_OPEN,
            enrolment_provider_unlisted::translate(self::COURSE, ['type' => 'enrolled'], $open)['state']
        );
    }

    /**
     * A blocked answer with the plugin's opening date is the none state carrying that date.
     *
     * The date is the plugin's: it fills opens only on a blocked answer, so only there is it read.
     *
     * @return void
     */
    public function test_a_blocked_answer_carries_its_opening_date(): void {
        $norow = ['type' => 'none', 'startsat' => 0, 'endsat' => 0];
        $opens = 1893456000;
        $blocked = ['type' => 'blocked', 'routes' => [], 'blocked' => 'window', 'opens' => $opens];

        $state = enrolment_provider_unlisted::translate(self::COURSE, $norow, $blocked);
        $this->assertSame([enrolment_provider::STATE_NONE, $opens], [$state['state'], $state['opens']]);
        $this->assertNull($state['actionurl']);
        $this->assertNull($state['routeurl']);

        // Controls: no date, a null date, or a date on an answer that is not blocked carry none.
        $none = enrolment_provider::none_state(self::COURSE);
        unset($blocked['opens']);
        $this->assertSame($none, enrolment_provider_unlisted::translate(self::COURSE, $norow, $blocked));
        $blocked['opens'] = null;
        $this->assertSame($none, enrolment_provider_unlisted::translate(self::COURSE, $norow, $blocked));
        $nothing = ['type' => 'none', 'routes' => [], 'opens' => $opens];
        $this->assertSame($none, enrolment_provider_unlisted::translate(self::COURSE, $norow, $nothing));
    }

    /**
     * A relationship is the card's state, and an opening date beside it is not carried.
     *
     * @return void
     */
    public function test_a_relationship_carries_no_opening_date(): void {
        $blocked = ['type' => 'blocked', 'routes' => [], 'blocked' => 'window', 'opens' => 1893456000];

        foreach (['scheduled', 'pending', 'waitlisted', 'suspended', 'expired'] as $type) {
            $state = enrolment_provider_unlisted::translate(self::COURSE, ['type' => $type], $blocked);
            $this->assertSame([$type, 0], [$state['state'], $state['opens']], $type);
        }
    }

    /**
     * A build of the plugin without the opens key reads as no date, through the batch and the single card.
     *
     * The stand-in answers as such a build does: its blocked answer has no opens key.
     *
     * @return void
     */
    public function test_an_answer_without_the_opens_key_gives_no_chip(): void {
        $this->resetAfterTest();
        unlisted_access_stub::$nextactions[5] = ['type' => 'blocked', 'routes' => [], 'blocked' => 'window'];
        unlisted_access_stub::$nextactions[6] = ['type' => 'blocked', 'routes' => [], 'blocked' => 'window', 'opens' => 1893456000];
        $provider = new stubbed_unlisted_provider();

        $states = $provider->locked_states([5 => (object) ['id' => 5], 6 => (object) ['id' => 6]], 0);
        $single = $provider->locked_state((object) ['id' => 5], 0);

        foreach ([$states[5], $single] as $state) {
            $payload = enrolment_state::export($state);
            $this->assertSame(['none', 0, ''], [$payload['key'], $payload['opens'], $payload['openslabel']]);
        }
        // Control: the build that has the key gets the chip through the same batch.
        $payload = enrolment_state::export($states[6]);
        $this->assertSame(1893456000, $payload['opens']);
        $this->assertNotSame('', $payload['openslabel']);
    }

    /**
     * One card asks the plugin's per-course pair, and nothing else.
     *
     * @return void
     */
    public function test_one_card_asks_the_per_course_pair(): void {
        $this->resetAfterTest();
        unlisted_access_stub::$relationships[5] = ['type' => 'suspended', 'startsat' => 0, 'endsat' => 0];

        $state = (new stubbed_unlisted_provider())->locked_state((object) ['id' => 5], 0);

        $this->assertSame('suspended', $state['state']);
        $this->assertSame([['get_enrolment_state', 5], ['get_next_action', 5]], unlisted_access_stub::$calls);
    }

    /**
     * A list asks the next actions in one batch, the relationships course by course.
     *
     * @return void
     */
    public function test_a_list_asks_the_next_actions_in_one_batch(): void {
        $this->resetAfterTest();
        unlisted_access_stub::$relationships[6] = ['type' => 'waitlisted', 'startsat' => 0, 'endsat' => 0];
        unlisted_access_stub::$nextactions[5] = ['type' => 'guest', 'routes' => [], 'guest' => 'key'];

        $states = (new stubbed_unlisted_provider())->locked_states([5 => (object) ['id' => 5], 6 => (object) ['id' => 6]], 0);

        $this->assertSame([5, 6], array_keys($states));
        $this->assertSame('key', $states[5]['state']);
        $this->assertSame('waitlisted', $states[6]['state']);
        $this->assertSame([
            ['get_next_actions', [5, 6]],
            ['get_enrolment_state', 5],
            ['get_enrolment_state', 6],
        ], unlisted_access_stub::$calls);

        // Control: an empty list asks nothing.
        unlisted_access_stub::$calls = [];
        $this->assertSame([], (new stubbed_unlisted_provider())->locked_states([], 0));
        $this->assertSame([], unlisted_access_stub::$calls);
    }

    /**
     * The literal values mapped here are local_unlistedcourses' own constants.
     *
     * Skipped where the plugin is not installed; the mapping is then the documented contract alone.
     *
     * @return void
     */
    public function test_the_mapped_values_are_the_plugins_constants(): void {
        if (!enrolment_provider::unlisted_available()) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $api = enrolment_provider::UNLISTED_API;
        $reflection = new \ReflectionClass($api);
        $relationships = [];
        $nextactions = [];
        foreach ($reflection->getReflectionConstants() as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }
            if (str_starts_with($constant->getName(), 'RELATIONSHIP_')) {
                $relationships[] = $constant->getValue();
            } else if (str_starts_with($constant->getName(), 'NEXT_')) {
                $nextactions[] = $constant->getValue();
            }
        }
        $mapped = array_merge(
            array_keys(enrolment_provider_unlisted::RELATIONSHIPS),
            enrolment_provider_unlisted::NO_RELATIONSHIP
        );
        sort($relationships);
        sort($mapped);
        $this->assertSame($relationships, $mapped);

        $mappednext = array_keys(enrolment_provider_unlisted::NEXT_ACTIONS);
        sort($nextactions);
        sort($mappednext);
        $this->assertSame($nextactions, $mappednext);
        $this->assertSame(constant($api . '::GUEST_KEY'), enrolment_provider_unlisted::GUEST_KEY);
        $this->assertSame(constant($api . '::NEXT_BLOCKED'), enrolment_provider_unlisted::BLOCKED);
    }

    /**
     * Against the real plugin: a suspended enrolment beside an open self enrolment, and a stranger.
     *
     * Skipped where the plugin is not installed.
     *
     * @return void
     */
    public function test_the_real_plugin_answers_through_the_provider(): void {
        global $DB;

        if (!enrolment_provider::unlisted_available()) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }
        $this->resetAfterTest();
        $api = enrolment_provider::UNLISTED_API;
        $course = $this->getDataGenerator()->create_course();
        $self = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'self'], '*', MUST_EXIST);
        enrol_get_plugin('self')->update_status($self, ENROL_INSTANCE_ENABLED);
        $suspended = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user(
            (int) $suspended->id,
            (int) $course->id,
            'student',
            'manual',
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $stranger = $this->getDataGenerator()->create_user();
        $provider = new enrolment_provider_unlisted();
        $enrol = (new \moodle_url('/enrol/index.php', ['id' => $course->id]))->out(false);

        $this->setUser($suspended);
        $api::reset_caches();
        $state = $provider->locked_state($course, (int) $suspended->id);
        $this->assertSame('suspended', $state['state']);
        $this->assertSame($enrol, $state['routeurl']);

        $this->setUser($stranger);
        $api::reset_caches();
        $state = $provider->locked_state($course, (int) $stranger->id);
        $this->assertSame('open', $state['state']);
        $this->assertSame($enrol, $state['actionurl']);
        // The batch agrees with the per-course answer.
        $api::reset_caches();
        $this->assertSame($state, $provider->locked_states([(int) $course->id => $course], (int) $stranger->id)[(int) $course->id]);
    }

    /**
     * The 5.02 CI job must check local_unlistedcourses out, or the real-plugin cases skip on every leg.
     *
     * The control for the cases above that skip without the plugin; the workflow file is the only place
     * that condition can be checked. Only the 5.02 job is held to it, because the plugin declares
     * $plugin->supported = [502, 502].
     *
     * @return void
     */
    public function test_ci_checks_out_unlistedcourses_on_the_502_leg(): void {
        $workflow = dirname(__DIR__, 2) . '/.github/workflows/ci.yml';
        if (!is_readable($workflow)) {
            $this->markTestSkipped('No .github/workflows/ci.yml: a release install export-ignores .github.');
        }
        $jobs = preg_split('/\n  (?=[a-z0-9-]+:\n)/', file_get_contents($workflow));
        $found = false;
        foreach ($jobs as $job) {
            if (!str_contains($job, 'MOODLE_502_STABLE')) {
                continue;
            }
            $found = true;
            $this->assertMatchesRegularExpression(
                '/plugin-dependencies:.*\n(\s+.*\n)*?\s*\S*moodle-local_unlistedcourses,main\b/',
                $job,
                'The 5.02 CI job does not check out moodle-local_unlistedcourses under plugin-dependencies.'
            );
        }
        $this->assertTrue($found, 'No MOODLE_502_STABLE job was found in ci.yml.');
    }

    /**
     * The state and date of a translated answer.
     *
     * @param array $state The state facts.
     * @return array [state, date].
     */
    private function state_and_date(array $state): array {
        return [$state['state'], $state['date']];
    }
}
