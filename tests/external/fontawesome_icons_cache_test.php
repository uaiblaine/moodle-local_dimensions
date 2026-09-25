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

/**
 * The icon picker's core fallback map is read from its cache and built only on a miss.
 *
 * Without Boost Union the map is built from core's icon map and a parse of Font Awesome's SCSS
 * variables, and every picker search asks for it. A site with Boost Union installed never
 * reaches the fallback, so the tests skip there.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_dimensions\external\get_fontawesome_icons
 */
final class fontawesome_icons_cache_test extends \advanced_testcase {
    /** @var array A map no build produces, so finding it in a search proves the cache was read. */
    private const SENTINEL = [
        'local_dimensions:fa-zzsentinel' => ['class' => 'fa-zzsentinel', 'source' => 'fasolid'],
    ];

    /**
     * Skip where Boost Union supplies the map, then log in as admin for the web service.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        if (
            file_exists($CFG->dirroot . '/theme/boost_union/lib.php')
            && file_exists($CFG->dirroot . '/theme/boost_union/locallib.php')
        ) {
            $this->markTestSkipped('Boost Union is installed, and its own map replaces the fallback this test reads.');
        }
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A search reads the cached map, and nothing is built beside it.
     *
     * Changes that must make it fail: return build_icon_map_fallback() directly from
     * build_icon_map(); rebuild whenever the fallback is reached, ignoring what get() returned.
     *
     * @return void
     */
    public function test_a_search_reads_the_cached_map(): void {
        \cache::make('local_dimensions', 'fontawesome_iconmap')->set('iconmap', self::SENTINEL);

        $this->assertSame(
            [['name' => 'local_dimensions:fa-zzsentinel', 'class' => 'fa-zzsentinel', 'source' => 'fasolid']],
            $this->search('zzsentinel')
        );
        // A core icon the built map always holds is not found, so no fresh build was merged in.
        $this->assertSame([], $this->search('i/checked'));
    }

    /**
     * On a miss the built map is served and stored for the next search.
     *
     * The control for the test above: with the cache purged, the sentinel is not found and the
     * real map is what the cache holds afterwards.
     *
     * Change that must make it fail: drop the set() that stores the built map.
     *
     * @return void
     */
    public function test_a_miss_builds_the_map_and_stores_it(): void {
        $cache = \cache::make('local_dimensions', 'fontawesome_iconmap');
        $cache->purge();
        $this->assertFalse($cache->get('iconmap'));

        $this->assertSame([], $this->search('zzsentinel'));
        $this->assertContains(
            ['name' => 'core:i/checked', 'class' => 'fa-check', 'source' => 'core'],
            $this->search('i/checked')
        );

        $stored = $cache->get('iconmap');
        $this->assertIsArray($stored, 'The built map was not stored.');
        $this->assertArrayNotHasKey('local_dimensions:fa-zzsentinel', $stored);
        $this->assertSame(['class' => 'fa-check', 'source' => 'core'], $stored['core:i/checked']);
    }

    /**
     * Search the icon map through the web service, as the picker does.
     *
     * @param string $query The search text.
     * @return array The icons returned.
     */
    private function search(string $query): array {
        $_POST['sesskey'] = sesskey();
        $response = external_api::call_external_function('local_dimensions_get_fontawesome_icons', ['query' => $query]);
        $this->assertFalse($response['error'], json_encode($response['exception'] ?? null));
        return $response['data']['icons'];
    }
}
