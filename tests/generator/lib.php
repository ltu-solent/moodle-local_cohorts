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

use local_cohorts\helper;

/**
 * Data generator class
 *
 * @package    local_cohorts
 * @category   test
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @author Mark Sharp <mark.sharp@solent.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_cohorts_generator extends component_generator_base {
    /**
     * Add managed cohort, and status record
     *
     * @param array $cohort Cohort to be added.
     * @param bool $status Enabled?
     * @return object
     */
    public function add_managed_cohort(array $cohort, bool $status = true): object {
        $dg = \testing_util::get_data_generator();
        $cohort = $dg->create_cohort($cohort);
        helper::update_cohort_status($cohort, $status);
        return $cohort;
    }
}
