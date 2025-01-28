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

$string['deletecohort'] = 'Delete cohort';
$string['deletecohortfor'] = 'Delete cohort "{$a}"';
$string['deleteconfirm'] = 'You are about to delete cohort "{$a}".<br /><strong>This cannot be reversed - all enrolments will be lost.</strong>';
$string['departmentcohorts'] = 'Department cohorts';
$string['disablecohort'] = 'Disable cohort {$a}';
$string['disableconfirm'] = 'You are about to disable {$a}.<br /><strong>This will remove ALL members of this cohort and unenrol them from pages where this cohort is used.</strong>
    <br />Consider Hiding the cohort instead.';

$string['emailexcludepattern'] = 'Email exclude pattern';
$string['emailexcludepattern_desc'] = 'Comma separated list of email patterns that are not included in the system cohorts. ' .
    'i.e. if the email address contains any of the text, it is excluded.';
$string['enablecohort'] = 'Enable cohort {$a}';
$string['enabled'] = 'Enabled';

$string['hidecohort'] = 'Hide cohort';

$string['locationcohortdescription'] = 'Auto populated {$a->name}. Members are enrolled on currently running modules or modules in the current academic year.' .
    ' Suspended accounts are removed. Only Gateway enrolled students are included.';

$string['managecohorts_help'] = "<p>These cohorts are all automatically managed by Solent's Cohort plugin.</p>
<ul>
    <li>Cohort IDs starting with 'inst_' include users who have the matching 'institution' user profile field</li>
    <li>Cohort IDs starting with 'loc_' include users who are enrolled on a module with that location</li>
    <li>Most other cohorts include users who have a matching 'department' profile field</li>
</ul>
<p>Hiding a cohort will make it unavailable for adding to new pages, but not change enrolments.</p>
<p>Disabling a cohort will remove all cohort members, and therefore remove all the relevant enrolments.</p>
<p>Because cohort creation is automatic, a deleted cohort will automatically reappear but unattached to any pages. Consider Disabling to prevent membership updates.</p>";

$string['notenabled'] = 'Not enabled';
$string['notvisible'] = 'Not visible';

$string['pluginname'] = "SOL Cohorts";

$string['showcohort'] = 'Show cohort';
$string['solentmanagedcohorts'] = 'Solent managed cohorts';
$string['staffcohorts'] = 'Staff cohorts';
$string['staffcohorts_desc'] = 'Comma separated list of staff cohort shortcodes used in the user "department" field';
$string['studentcohort'] = 'Student cohort for {$a->name}';
$string['synclocationcohorts'] = 'Sync Location Cohorts';
$string['systemcohorts'] = 'System cohorts';

$string['userprofilefielddescription'] = 'Auto populated {$a->name}. Members are enrolled based on their user profile {$a->field} field. ' .
    'Only LDAP accounts are included. Suspended accounts are removed. "Excluded" accounts are not included.';

$string['viewingmembers'] = 'Viewing members of {$a}';
$string['viewmembers'] = 'View members';
$string['viewmembersof'] = 'View members of {$a}';
