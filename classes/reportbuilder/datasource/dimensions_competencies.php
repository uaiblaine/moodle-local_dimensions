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

namespace local_dimensions\reportbuilder\datasource;

use core\reportbuilder\local\entities\context;
use core_cohort\reportbuilder\local\entities\cohort;
use core_competency\reportbuilder\datasource\competencies;
use core_competency\reportbuilder\local\entities\{competency, framework};
use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\helpers\database;
use local_dimensions\constants;
use local_dimensions\reportbuilder\local\entities\{competency_extras, usercompetency};

/**
 * Competencies datasource extended with local_dimensions customfields.
 *
 * The core competencies datasource (framework, context, competency, user
 * competency, user and cohort entities, joined as core joins them), with two
 * changes: the user competency entity is the plugin's {@see usercompetency},
 * whose rating needs no query per row, and a {@see competency_extras} entity
 * adds the customfields local_dimensions defines in the `competency` area.
 *
 * @package    local_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class dimensions_competencies extends competencies {
    /**
     * Human-readable name shown in the report-builder UI.
     */
    #[\Override]
    public static function get_name(): string {
        return get_string('datasource_dimensions_competencies', 'local_dimensions');
    }

    /**
     * Offered only while competencies are enabled on the site.
     */
    #[\Override]
    public static function is_available(): bool {
        return \core_competency\api::is_enabled();
    }

    /**
     * Wire entities and joins, as core's datasource does, with the plugin's two entities.
     */
    #[\Override]
    protected function initialise(): void {
        $frameworkentity = new framework();
        $frameworkalias = $frameworkentity->get_table_alias('competency_framework');

        $contextentity = new context();
        $contextalias = $contextentity->get_table_alias('context');

        $this->set_main_table('competency_framework', $frameworkalias);

        // Context is joined unconditionally: the framework and competency entities use it too.
        $this->add_join("LEFT JOIN {context} {$contextalias} ON {$contextalias}.id = {$frameworkalias}.contextid");
        $this->add_entity($contextentity);

        $this->add_entity($frameworkentity
            ->set_table_join_alias('context', $contextalias));

        $competencyentity = new competency();
        $competencyalias = $competencyentity->get_table_alias('competency');
        $this->add_entity($competencyentity
            ->set_table_join_alias('context', $contextalias)
            ->add_join("LEFT JOIN {competency} {$competencyalias}
                ON {$competencyalias}.competencyframeworkid = {$frameworkalias}.id"));

        // Dimensions customfields, keyed to the same competency rows.
        $extrasentity = new competency_extras();
        $this->add_entity($extrasentity
            ->set_table_alias('competency', $competencyalias)
            ->add_joins($competencyentity->get_joins()));

        $usercompetencyentity = new usercompetency();
        $usercompetencyalias = $usercompetencyentity->get_table_alias('competency_usercomp');
        $this->add_entity($usercompetencyentity
            ->add_joins($competencyentity->get_joins())
            ->add_join("LEFT JOIN {competency_usercomp} {$usercompetencyalias}
                ON {$usercompetencyalias}.competencyid = {$competencyalias}.id"));

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_joins($usercompetencyentity->get_joins())
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$usercompetencyalias}.userid"));

        $cohortentity = new cohort();
        $cohortalias = $cohortentity->get_table_alias('cohort');
        $cohortmemberalias = database::generate_alias();
        $this->add_entity($cohortentity
            ->add_joins($userentity->get_joins())
            ->add_joins([
                "LEFT JOIN {cohort_members} {$cohortmemberalias} ON {$cohortmemberalias}.userid = {$useralias}.id",
                "LEFT JOIN {cohort} {$cohortalias} ON {$cohortalias}.id = {$cohortmemberalias}.cohortid",
            ]));

        $this->add_all_from_entities([
            $contextentity->get_entity_name(),
            $frameworkentity->get_entity_name(),
            $competencyentity->get_entity_name(),
            $extrasentity->get_entity_name(),
            $usercompetencyentity->get_entity_name(),
            $userentity->get_entity_name(),
        ]);

        $this->add_all_from_entity(
            $cohortentity->get_entity_name(),
            ['name', 'idnumber', 'description', 'customfield*'],
            ['cohortselect', 'name', 'idnumber', 'customfield*'],
            ['cohortselect', 'name', 'idnumber', 'customfield*'],
        );
    }

    /**
     * Core's default columns, with the competency's Dimensions type and first tag.
     *
     * A field an administrator deleted is left out: a default column must exist.
     *
     * @return string[]
     */
    #[\Override]
    public function get_default_columns(): array {
        $columns = [
            'framework:name',
            'competency:name',
            'competency_extras:customfield_' . constants::CFIELD_TYPE,
            'competency_extras:customfield_' . constants::CFIELD_TAG1,
            'user:fullname',
            'usercompetency:proficient',
        ];
        return array_values(array_filter($columns, fn(string $column): bool => $this->get_column($column) !== null));
    }
}
