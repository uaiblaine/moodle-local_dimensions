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

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Every web service of the plugin refuses to answer while competencies are disabled.
 *
 * Many of them read the competency tables directly rather than through core_competency\api, so
 * nothing else stops them: the hub pages call require_enabled(), and the services must agree.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
final class external_requires_competencies_test extends \advanced_testcase {
    /** @var string[] Services that answer without competencies, each with its reason. */
    private const array EXEMPT = [
        // The icon picker of an admin setting; it reads FontAwesome, not competencies.
        'get_fontawesome_icons.php',
    ];

    /**
     * Every service file other than the exempt ones calls api::require_enabled().
     *
     * @return void
     */
    public function test_every_service_requires_competencies(): void {
        $files = glob(__DIR__ . '/../../classes/external/*.php');
        $this->assertGreaterThan(40, count($files), 'The scan must see the services');
        $missing = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, self::EXEMPT, true)) {
                continue;
            }
            if (!preg_match('/\bapi::require_enabled\(\);/', file_get_contents($file))) {
                $missing[] = $name;
            }
        }
        $this->assertSame([], $missing, 'Services that answer while competencies are disabled');
    }

    /**
     * A service that reads the competency tables directly refuses once competencies are disabled,
     * and answers while they are enabled (the control).
     *
     * @return void
     */
    public function test_a_direct_reader_refuses_when_competencies_are_disabled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enabled', 1, 'core_competency');
        $result = \local_dimensions\external\search_competencies::execute('anything', 0, 25);
        $this->assertSame(0, $result['total']);

        set_config('enabled', 0, 'core_competency');
        try {
            \local_dimensions\external\search_competencies::execute('anything', 0, 25);
            $this->fail('The search answered while competencies were disabled.');
        } catch (\moodle_exception $e) {
            $this->assertSame('competenciesarenotenabled', $e->errorcode);
        }
    }
}
