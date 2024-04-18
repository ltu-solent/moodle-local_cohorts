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
        $context = context_system::instance();
        // Note: this function does require that the cohort already exists, and will not create a new cohort on the fly.
        $cohorts = $DB->get_records('cohort', ['contextid' => $context->id, 'component' => 'local_cohorts']);
        foreach ($cohorts as $cohort) {
            $userfield = 'department';
            if (strpos($cohort->idnumber, 'inst_') === 0) {
                $userfield = 'institution';
            }
            switch ($cohort->idnumber) {
                case 'all-staff':
                    helper::update_all_staff_cohort();
                    break;
                case 'student6':
                    student6();
                    break;
                default:
                    helper::update_user_profile_cohort($cohort->id, $userfield);
                    break;
            }
        }
    }
}
