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
 * Language file
 *
 * @package   local_cohorts
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addnewcohortmembers'] = 'Add new cohort members';

$string['cohortdescription'] = 'Auto populated {$a->name}';
$string['departmentcohorts'] = 'Department cohorts';
$string['emailexcludepattern'] = 'Email exclude pattern';
$string['emailexcludepattern_desc'] = 'Comma separated list of email patterns that are not included in the system cohorts. ' .
    'i.e. if the email address contains any of the text, it is excluded.';

$string['pluginname'] = "SOL Cohorts";

$string['staffcohorts'] = 'Staff cohorts';
$string['staffcohorts_desc'] = 'Comma separated list of staff cohort shortcodes used in the user "department" field';
$string['studentcohort'] = 'Student cohort for {$a->name}';
$string['synclocationcohorts'] = 'Sync Location Cohorts';
$string['systemcohorts'] = 'System cohorts';
