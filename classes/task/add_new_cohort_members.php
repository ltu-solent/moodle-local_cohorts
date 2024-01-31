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
 * Cohorts sync task
 *
 * @package   local_cohorts
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_cohorts\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/cohorts/lib.php');

use context_system;
use local_cohorts\helper;
use stdClass;

/**
 * Add new cohort members
 */
class add_new_cohort_members extends \core\task\scheduled_task {
    /**
     * {@inheritDoc}
     *
     * @return string
     */
    public function get_name() {
        return get_string('addnewcohortmembers', 'local_cohorts');
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $config = get_config('local_cohorts');
        $depts = explode(',', trim($config->systemcohorts));
        $context = context_system::instance();
        foreach ($depts as $dept) {
            if (empty($dept)) {
                continue;
            }
            $cohortid = $DB->get_field('cohort', 'id', ['idnumber' => $dept]);
            // Create the system cohort if it doesn't exist.
            if (!$cohortid) {
                $cohort = new stdClass();
                $cohort->name = ucwords($dept);
                $cohort->idnumber = $dept;
                $cohort->contextid = $context->id;
                $cohortid = cohort_add_cohort($cohort);
            }
            helper::update_user_department_cohort($cohortid);
        }
        student6();
    }
}
