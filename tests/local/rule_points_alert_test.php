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
 * The points rule alert names every condition the rule modal refuses a points config for.
 *
 * rule_config.js readPointsConfig() refuses what core_competency\competency_rule_points::validate_config()
 * would, and the modal answers every refusal with the one inline alert, central_rule_invalidpoints.
 * An alert that names only some of the conditions leaves the author unable to find the one that
 * failed, and nothing in the pipeline compares a lang string with the check it reports.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class rule_points_alert_test extends \basic_testcase {
    /** @var array Refusal in readPointsConfig() => the phrase naming it, per language. */
    private const CONDITIONS = [
        '/Number\.isInteger\(comp\.points\) && comp\.points >= 0/' => [
            'en' => 'whole numbers of zero or more',
            'pt_br' => 'números inteiros maiores ou iguais a zero',
        ],
        '/requiredpoints < 1/' => [
            'en' => 'required points must be at least 1',
            'pt_br' => 'pontos necessários devem ser pelo menos 1',
        ],
        '/total < requiredpoints/' => [
            'en' => 'total available points must be at least the required points',
            'pt_br' => 'total de pontos disponíveis deve ser ao menos igual aos pontos necessários',
        ],
    ];

    /**
     * Each condition the modal refuses is named by the alert, in both languages.
     *
     * The first assertions are the control: the refusal is really made, and the alert is the one
     * the modal shows for it, so the wording is checked against conditions that exist.
     *
     * Change that must make it fail: put the old wording of central_rule_invalidpoints back ("The
     * total available points must be at least the required points."), in either language.
     *
     * @return void
     */
    public function test_the_alert_names_every_refused_condition(): void {
        $root = dirname(__DIR__, 2);
        $source = $this->code((string) file_get_contents($root . '/amd/src/central/rule_config.js'));
        $this->assertSame(
            1,
            preg_match('/\nconst readPointsConfig = \(pointsEl\) => \{\n(.*?)\n\};/s', $source, $match),
            'readPointsConfig() was not found.'
        );
        $check = $match[1];
        $this->assertStringContainsString('Number.isInteger(requiredpoints)', $check);
        $this->assertMatchesRegularExpression(
            '/if \(!wholepoints \|\| requiredpoints < 1 \|\| total < requiredpoints\) \{\s*return null;/',
            $check
        );
        $this->assertMatchesRegularExpression(
            '/data-region="error"[^>]*>\s*\{\{#str\}\}central_rule_invalidpoints, local_dimensions\{\{\/str\}\}/',
            (string) file_get_contents($root . '/templates/central/rule_config.mustache')
        );

        $missing = [];
        foreach (self::CONDITIONS as $refusal => $phrases) {
            $this->assertMatchesRegularExpression($refusal, $check);
            foreach ($phrases as $lang => $phrase) {
                $alert = $this->strings($lang)['central_rule_invalidpoints'];
                if (!str_contains($alert, $phrase)) {
                    $missing[] = $lang . ': "' . $phrase . '"';
                }
            }
        }
        $this->assertSame([], $missing, 'central_rule_invalidpoints does not name: ' . implode('; ', $missing));
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
     * The strings a language file of the plugin declares.
     *
     * @param string $lang The language directory name.
     * @return array String key => text.
     */
    private function strings(string $lang): array {
        $string = [];
        include(dirname(__DIR__, 2) . '/lang/' . $lang . '/local_dimensions.php');
        return $string;
    }
}
