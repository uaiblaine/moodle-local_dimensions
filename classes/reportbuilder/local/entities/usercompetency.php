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

namespace local_dimensions\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\report\{column, filter};
use local_dimensions\reportbuilder\local\helpers\rating;
use stdClass;

/**
 * User competency entity with a rating that needs no query per row.
 *
 * Core's `usercompetency` entity reads the competency and its scale for every row to name the
 * rating. This subclass keeps core's entity, column and filter names, so saved reports keep
 * their elements, and replaces only the `rating` column; it also adds a `rating` filter
 * (position in the scale), which core does not offer.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usercompetency extends \core_competency\reportbuilder\local\entities\usercompetency {
    /**
     * Initialise core's elements, then put the plugin's rating in place of core's.
     */
    public function initialise(): base {
        parent::initialise();

        $alias = $this->get_table_alias('competency_usercomp');

        $this->add_column((new column(
            'rating',
            new lang_string('rating', 'core_competency'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_field("{$alias}.grade")
            ->add_field(rating::scaleid_sql("{$alias}.competencyid"), 'scaleid')
            ->set_is_sortable(true, ["{$alias}.grade"])
            ->add_callback(static fn($grade, stdClass $row): string => rating::format($grade, $row->scaleid ?? null)));

        $filter = (new filter(
            number::class,
            'rating',
            new lang_string('filter_ratingvalue', 'local_dimensions'),
            $this->get_entity_name(),
            "{$alias}.grade"
        ))->add_joins($this->get_joins());
        $this->add_filter($filter)->add_condition($filter);

        return $this;
    }
}
