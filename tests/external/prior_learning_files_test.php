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

use core_competency\api;
use core_competency\evidence;
use core_competency\url;
use core_competency\user_evidence;
use core_external\external_api;

/**
 * Tests the competency summary's hasfiles flag on prior-learning evidence.
 *
 * Every learner record in the fixture is linked to the competency through core's API, so each one has
 * the 'linked' evidence row core writes with the record's URL. The flag describes the record as it is
 * now, and only for a viewer allowed to open the owner's records.
 *
 * The covers tag stays in this docblock because moodle-cs for Moodle 4.5 cannot see PHPUnit
 * attributes.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_user_competency_summary_in_plan
 */
final class prior_learning_files_test extends \advanced_testcase {
    /** @var string Web service under test. */
    private const WSNAME = 'local_dimensions_get_user_competency_summary_in_plan';

    /** @var string The descidentifier core writes when a record is linked to a competency. */
    private const LINKED = 'evidence_evidenceofpriorlearninglinked';

    /**
     * The owner sees which linked records hold files, and no other evidence type carries the flag.
     *
     * @return void
     */
    public function test_the_owner_sees_which_records_hold_files(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $withfile = $this->linked_record($fixture, 'Certificate', ['certificate.pdf']);
        $withoutfile = $this->linked_record($fixture, 'Workshop notes', []);
        $this->grade_in_plan($fixture);

        $this->setUser($fixture['learner']);
        $rows = $this->evidence($fixture);

        $this->assertTrue($this->row_for($rows, $withfile)->hasfiles);
        $this->assertFalse($this->row_for($rows, $withoutfile)->hasfiles);
        $rating = $this->rows_of_type($rows, 'evidence_manualoverrideinplan');
        $this->assertCount(1, $rating, 'Control: the manual rating is in the payload.');
        $this->assertObjectNotHasProperty('hasfiles', reset($rating));
    }

    /**
     * A reviewer who reads the plan and its ratings but not the owner's records gets no flag at all.
     *
     * The control grants the same role userevidenceview and gets both flags.
     *
     * @return void
     */
    public function test_a_reviewer_without_userevidenceview_gets_no_flag(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $withfile = $this->linked_record($fixture, 'Certificate', ['certificate.pdf']);
        $withoutfile = $this->linked_record($fixture, 'Workshop notes', []);

        $this->setAdminUser();
        $reviewer = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $syscontextid = \context_system::instance()->id;
        assign_capability('moodle/competency:planview', CAP_ALLOW, $roleid, $syscontextid);
        assign_capability('moodle/competency:usercompetencyview', CAP_ALLOW, $roleid, $syscontextid);
        role_assign($roleid, (int) $reviewer->id, $syscontextid);

        $this->setUser($reviewer);
        $this->assertFalse(user_evidence::can_read_user((int) $fixture['learner']->id));
        $json = $this->call($fixture);
        $rows = json_decode($json)->usercompetencysummary->evidence;
        // The rows are in the payload, so the missing key is not an empty list.
        $this->assertCount(2, $this->rows_of_type($rows, self::LINKED));
        $this->assertSame(0, substr_count($json, 'hasfiles'), 'The payload tells this viewer nothing about files.');

        $this->setAdminUser();
        assign_capability('moodle/competency:userevidenceview', CAP_ALLOW, $roleid, $syscontextid);
        $this->setUser($reviewer);
        $rows = $this->evidence($fixture);
        $this->assertTrue($this->row_for($rows, $withfile)->hasfiles);
        $this->assertFalse($this->row_for($rows, $withoutfile)->hasfiles);
    }

    /**
     * A record unlinked or deleted since reads false; its 'linked' row stays in the payload.
     *
     * The unlinked record keeps its file, so only the current link can decide it. The still-linked
     * record beside them is the control.
     *
     * @return void
     */
    public function test_an_unlinked_or_deleted_record_reads_false(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $kept = $this->linked_record($fixture, 'Certificate', ['certificate.pdf']);
        $unlinked = $this->linked_record($fixture, 'Old portfolio', ['portfolio.pdf']);
        $deleted = $this->linked_record($fixture, 'Withdrawn', ['withdrawn.pdf']);

        $this->setUser($fixture['learner']);
        api::delete_user_evidence_competency($unlinked, $fixture['competencyid']);
        api::delete_user_evidence($deleted);
        $this->assertCount(1, (new user_evidence($unlinked))->get_files(), 'Control: unlinking keeps the file.');

        $rows = $this->evidence($fixture);
        $this->assertTrue($this->row_for($rows, $kept)->hasfiles);
        $this->assertFalse($this->row_for($rows, $unlinked)->hasfiles);
        $this->assertFalse($this->row_for($rows, $deleted)->hasfiles);
        $unlinkrows = $this->rows_of_type($rows, 'evidence_evidenceofpriorlearningunlinked');
        $this->assertNotEmpty($unlinkrows);
        foreach ($unlinkrows as $row) {
            $this->assertObjectNotHasProperty('hasfiles', $row);
        }
    }

    /**
     * A record whose file area holds only a directory entry holds no file.
     *
     * @return void
     */
    public function test_a_directory_entry_is_not_a_file(): void {
        global $DB;

        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $withfile = $this->linked_record($fixture, 'Certificate', ['certificate.pdf']);
        $directoryonly = $this->linked_record($fixture, 'Empty folder', []);
        get_file_storage()->create_directory(
            \context_user::instance((int) $fixture['learner']->id)->id,
            'core_competency',
            'userevidence',
            $directoryonly,
            '/'
        );
        $this->assertSame(1, $DB->count_records('files', [
            'component' => 'core_competency',
            'filearea' => 'userevidence',
            'itemid' => $directoryonly,
            'filename' => '.',
        ]), 'Control: the area holds its directory entry.');

        $this->setUser($fixture['learner']);
        $rows = $this->evidence($fixture);

        $this->assertTrue($this->row_for($rows, $withfile)->hasfiles);
        $this->assertFalse($this->row_for($rows, $directoryonly)->hasfiles);
    }

    /**
     * A row is answered only from the owner's own record, read in the owner's own context.
     *
     * Two guards meet here, and the fixture holds each on its own. A row in the owner's evidence that
     * carries another learner's record URL must not match that record, even though a stray file sits
     * in the owner's context under that record's id. And the owner's record without files must stay
     * false, even though a stray file sits under its id in the other learner's context.
     *
     * @return void
     */
    public function test_a_row_is_answered_only_from_the_owners_record(): void {
        $this->resetAfterTest();
        $fixture = $this->build_fixture();
        $withfile = $this->linked_record($fixture, 'Certificate', ['certificate.pdf']);
        $withoutfile = $this->linked_record($fixture, 'Workshop notes', []);

        $other = $this->getDataGenerator()->create_user();
        $foreign = $this->linked_record($fixture, 'Their certificate', ['theirs.pdf'], $other);
        $this->store_file((int) $fixture['learner']->id, $foreign, 'stray.pdf');
        $this->store_file((int) $other->id, $withoutfile, 'stray.pdf');

        $this->setAdminUser();
        api::add_evidence(
            (int) $fixture['learner']->id,
            $fixture['competencyid'],
            \context_user::instance((int) $fixture['learner']->id),
            evidence::ACTION_LOG,
            self::LINKED,
            'core_competency',
            'Their certificate',
            false,
            url::user_evidence($foreign)->out(false)
        );

        $this->setUser($fixture['learner']);
        $rows = $this->evidence($fixture);

        $this->assertTrue($this->row_for($rows, $withfile)->hasfiles);
        $this->assertFalse($this->row_for($rows, $foreign)->hasfiles);
        $this->assertFalse($this->row_for($rows, $withoutfile)->hasfiles);
    }

    /**
     * A learner with an active plan holding one competency.
     *
     * @return array The learner, the competency id and the plan id.
     */
    private function build_fixture(): array {
        $this->setAdminUser();
        $scale = $this->getDataGenerator()->create_scale(['scale' => 'Emerging,Developing,Competent']);
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework([
            'scaleid' => $scale->id,
            'scaleconfiguration' => json_encode([
                ['scaleid' => (int) $scale->id],
                ['id' => 2, 'scaledefault' => 1, 'proficient' => 1],
                ['id' => 3, 'proficient' => 1],
            ]),
        ]);
        $competency = $ccg->create_competency(['competencyframeworkid' => $framework->get('id')]);
        $template = $ccg->create_template();
        $ccg->create_template_competency([
            'templateid' => (int) $template->get('id'),
            'competencyid' => (int) $competency->get('id'),
        ]);
        $learner = $this->getDataGenerator()->create_user();
        $plan = $ccg->create_plan([
            'userid' => $learner->id,
            'templateid' => (int) $template->get('id'),
            'status' => \core_competency\plan::STATUS_ACTIVE,
        ]);

        return [
            'learner' => $learner,
            'competencyid' => (int) $competency->get('id'),
            'planid' => (int) $plan->get('id'),
        ];
    }

    /**
     * A prior-learning record with files, linked to the competency by its owner through core's API.
     *
     * @param array $fixture The fixture.
     * @param string $name The record's name.
     * @param array $filenames The files to store in the record's area.
     * @param \stdClass|null $owner The record's owner, the fixture's learner when null.
     * @return int The record id.
     */
    private function linked_record(array $fixture, string $name, array $filenames, ?\stdClass $owner = null): int {
        $owner = $owner ?? $fixture['learner'];
        $ccg = $this->getDataGenerator()->get_plugin_generator('core_competency');
        $record = $ccg->create_user_evidence(['userid' => $owner->id, 'name' => $name]);
        $id = (int) $record->get('id');
        foreach ($filenames as $filename) {
            $this->store_file((int) $owner->id, $id, $filename);
        }

        $this->setUser($owner);
        api::create_user_evidence_competency($id, $fixture['competencyid']);
        $this->setAdminUser();

        return $id;
    }

    /**
     * Store a file in a user's prior-learning file area.
     *
     * @param int $userid The user whose context holds the file.
     * @param int $itemid The item id, a record id.
     * @param string $filename The file name.
     * @return void
     */
    private function store_file(int $userid, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($userid)->id,
            'component' => 'core_competency',
            'filearea' => 'userevidence',
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'Evidence');
    }

    /**
     * Rate the competency in the plan, which writes a manual rating evidence row.
     *
     * @param array $fixture The fixture.
     * @return void
     */
    private function grade_in_plan(array $fixture): void {
        $this->setAdminUser();
        api::grade_competency_in_plan($fixture['planid'], $fixture['competencyid'], 2);
    }

    /**
     * Call the web service as the current user.
     *
     * @param array $fixture The fixture.
     * @return string The JSON payload.
     */
    private function call(array $fixture): string {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function(self::WSNAME, [
            'competencyid' => $fixture['competencyid'],
            'planid' => $fixture['planid'],
        ]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));

        return $response['data'];
    }

    /**
     * The evidence rows of the payload, as the current user.
     *
     * @param array $fixture The fixture.
     * @return array The decoded rows.
     */
    private function evidence(array $fixture): array {
        return json_decode($this->call($fixture))->usercompetencysummary->evidence;
    }

    /**
     * The one 'linked' row carrying a record's URL.
     *
     * @param array $rows The decoded rows.
     * @param int $id The record id.
     * @return \stdClass
     */
    private function row_for(array $rows, int $id): \stdClass {
        $url = url::user_evidence($id)->out(false);
        $matches = array_values(array_filter($rows, static function (\stdClass $row) use ($url): bool {
            return $row->descidentifier === self::LINKED && $row->url === $url;
        }));
        $this->assertCount(1, $matches, "One linked row for record {$id}.");
        $this->assertObjectHasProperty('hasfiles', $matches[0], "The linked row for record {$id} carries the flag.");

        return $matches[0];
    }

    /**
     * The rows written with a descidentifier.
     *
     * @param array $rows The decoded rows.
     * @param string $descidentifier The descidentifier.
     * @return array
     */
    private function rows_of_type(array $rows, string $descidentifier): array {
        return array_values(array_filter($rows, static function (\stdClass $row) use ($descidentifier): bool {
            return $row->descidentifier === $descidentifier;
        }));
    }
}
