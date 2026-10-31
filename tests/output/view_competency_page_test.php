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
 * Tests for the competency tracker's filter line state.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_dimensions\output;

use local_dimensions\constants;
use local_dimensions\fixtures\status_badges_listener;
use local_dimensions\local\learner_view;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The tracker's scope opens on "To do" unless the learner chose "All", and it seeds the whole state.
 */
#[CoversClass(\local_dimensions\output\view_competency_page::class)]
#[CoversClass(\local_dimensions\local\learner_view::class)]
final class view_competency_page_test extends \advanced_testcase {
    /** @var \stdClass The learner the tracker is read by. */
    private \stdClass $learner;

    /**
     * Export the tracker for one competency and one course, read by a fresh learner.
     *
     * @return array The Mustache context.
     */
    private function export(): array {
        global $DB, $PAGE;
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $competency = $DB->get_record('competency', ['id' => $competencyid], '*', MUST_EXIST);
        $course = get_course((int) $this->getDataGenerator()->create_course()->id);

        $page = new view_competency_page($competency, [(int) $course->id => $course], (int) $this->learner->id);
        return $page->export_for_template($PAGE->get_renderer('core'));
    }

    /**
     * Log a fresh learner in, with the view preference given.
     *
     * @param string|null $stored The stored preference, or null for none.
     * @return void
     */
    private function learner(?string $stored): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $this->learner = $this->getDataGenerator()->create_user();
        $this->setUser($this->learner);
        if ($stored !== null) {
            set_user_preference(constants::PREF_LEARNER_VIEW, $stored);
        }
    }

    /**
     * The scope cell drawn checked, by value.
     *
     * @param array $data The export.
     * @return array Values of the checked cells.
     */
    private function checked(array $data): array {
        return array_values(array_map(
            fn($cell) => $cell['value'],
            array_filter($data['line']['scope'], fn($cell) => $cell['checked'])
        ));
    }

    /**
     * With nothing stored, the tracker opens on the learner's open work: "To do", not "All".
     *
     * The owner's decision for the filter line (L1). The seed says the same, so the first save
     * writes what the reader saw.
     *
     * @return void
     */
    public function test_the_scope_defaults_to_to_do(): void {
        $this->learner(null);
        $data = $this->export();

        $this->assertTrue($data['scope_is_incomplete']);
        $this->assertFalse($data['scope_is_all']);
        $this->assertSame(['incomplete'], $this->checked($data));
        $this->assertSame('incomplete', json_decode($data['viewstatejson'], true)['trackerfilter']);
        // The scope waits for the completion batch: its counts cannot be told before.
        $this->assertTrue($data['line']['scopehidden']);
        $this->assertSame(['incomplete', 'all'], array_column($data['line']['scope'], 'value'));
    }

    /**
     * A reader who chose "All" before is shown "All": the remembered choice wins over the default.
     *
     * @return void
     */
    public function test_a_remembered_choice_wins(): void {
        $this->learner(json_encode(['trackerfilter' => 'all']));
        $data = $this->export();

        $this->assertTrue($data['scope_is_all']);
        $this->assertSame(['all'], $this->checked($data));
        $this->assertSame('all', json_decode($data['viewstatejson'], true)['trackerfilter']);
    }

    /**
     * A stored value the tracker does not offer reads as the default, and the plan's own scope is apart.
     *
     * The plan's "filter" key is the plan overview's scope: "all" there says nothing about the tracker.
     *
     * @return void
     */
    public function test_an_unknown_value_and_the_plans_scope_leave_the_default(): void {
        $this->learner(json_encode(['trackerfilter' => 'everything', 'filter' => 'all']));
        $data = $this->export();

        $this->assertSame(['incomplete'], $this->checked($data));
    }

    /**
     * The tracker seeds every key of the state, the plan's included, so its save resets none of them.
     *
     * @return void
     */
    public function test_the_seeded_view_state_carries_every_key(): void {
        $this->learner(json_encode(['sort' => 'name', 'view' => 'grid', 'favonly' => true, 'expanded' => true]));
        $state = json_decode($this->export()['viewstatejson'], true);

        $keys = learner_view::KEYS;
        sort($keys);
        $seeded = array_keys($state);
        sort($seeded);
        $this->assertSame($keys, $seeded);
        // The plan's chrome travels untouched through the tracker's seed.
        $this->assertSame('name', $state['sort']);
        $this->assertSame('grid', $state['view']);
        $this->assertTrue($state['favonly']);
        $this->assertTrue($state['expanded']);
    }

    /**
     * Export the tracker for a learner while another user, the admin, views it.
     *
     * @param array $answers The badges the test listener adds: competency id => list of [label, tone].
     * @param bool $register Whether to register the listener at all.
     * @return array Keys: data (the export), competencyid, learner (stdClass).
     */
    private function export_as_admin(array $answers, bool $register = true): array {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework();
        $competencyid = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id')])->get('id');
        $competency = $DB->get_record('competency', ['id' => $competencyid], '*', MUST_EXIST);
        $learner = $this->getDataGenerator()->create_user();
        $course = get_course((int) $this->getDataGenerator()->create_course()->id);

        require_once(__DIR__ . '/../fixtures/hook/status_badges_listener.php');
        status_badges_listener::reset();
        if ($register) {
            \core\di::set(
                \core\hook\manager::class,
                \core\hook\manager::phpunit_get_instance([
                    'local_dimensions' => __DIR__ . '/../fixtures/hook/status_badges_hooks.php',
                ]),
            );
            foreach ($answers as $label => $tone) {
                status_badges_listener::$answers[$competencyid][] = [$label, $tone];
            }
        }

        $page = new view_competency_page($competency, [(int) $course->id => $course], (int) $learner->id);
        $data = $page->export_for_template($PAGE->get_renderer('core'));
        return ['data' => $data, 'competencyid' => $competencyid, 'learner' => $learner];
    }

    /**
     * The hero carries the listener's badges, and the hook is built for the learner, not the viewer.
     *
     * @return void
     */
    public function test_the_hero_carries_status_badges_and_the_hook_the_learner(): void {
        $result = $this->export_as_admin(['Validity: Due soon' => 'warning']);

        $hero = $result['data']['hero'];
        $this->assertTrue($hero['hasstatusbadges']);
        $this->assertSame([['label' => 'Validity: Due soon', 'tone' => 'warning']], $hero['statusbadges']);

        $this->assertCount(1, status_badges_listener::$seen);
        $hook = status_badges_listener::$seen[0];
        $this->assertSame((int) $result['learner']->id, $hook->get_userid());
        $this->assertNotSame((int) $result['learner']->id, status_badges_listener::$viewers[0]);
        $this->assertSame([$result['competencyid']], $hook->get_competencyids());
    }

    /**
     * Without a listener the hero has no badges (control).
     *
     * @return void
     */
    public function test_without_a_listener_the_hero_has_no_status_badges(): void {
        $hero = $this->export_as_admin([])['data']['hero'];
        $this->assertFalse($hero['hasstatusbadges']);
        $this->assertSame([], $hero['statusbadges']);
    }
}
