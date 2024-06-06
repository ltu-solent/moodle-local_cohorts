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

namespace local_cohorts\task;

use context_system;
use local_cohorts\helper;

/**
 * Tests for SOL Cohorts
 *
 * @package    local_cohorts
 * @category   test
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class location_cohort_sync_test extends \advanced_testcase {
    /**
     * Test local_cohort_sync
     *
     * @covers \local_cohort\task\local_cohort_sync
     * @return void
     */
    public function test_execute() {
        global $DB;
        $this->resetAfterTest();
        // This test sets up courses that fall into 3 locations.
        // Each location has courses for different semester patterns.
        // Each location has courses set up from 2020/21 with up to 2 years in the future.
        // Each course has 3 sets of 2 students, which roll on and off as the years progress.
        // This mimics student progression through their academic career.
        // 2020 [0, 1, 2]
        // 2021 [1, 2, 3]
        // 2022 [2, 3, 4]
        // 2023 [3, 4 ,5] etc.
        // Only students in currently running courses or in this academic year are included.
        // Non-students are not included in the cohorts.

        $dg = $this->getDataGenerator();
        $enrol = enrol_get_plugin('solaissits');
        $this->enable_plugin();
        $catid = $dg->create_custom_field_category([])->get('id');
        $dg->create_custom_field(['categoryid' => $catid, 'type' => 'text', 'shortname' => 'location', 'name' => 'Location']);
        $locations = [
            'loc_solent-university_stu' => 'Solent University',
            'loc_qahe_stu' => 'QAHE',
            'loc_a-really-long-name-for-an-institution-that-might-get-trimmed-at-some-point-with-some-punctua_stu' =>
                'A really long * name for an institution that might get trimmed at some point, with some ! punctuation',
        ];
        $currentsession = helper::get_current_session();
        // Start with oldest session first.
        $sessionmenu = array_reverse(helper::get_session_menu());
        $periods = [
            'SEM1' => ['start' => 'YYYY-09-21', 'end' => '20YY-01-14'],
            'SEM2' => ['start' => '20YY-01-21', 'end' => '20YY-05-14'],
            'S1S2' => ['start' => 'YYYY-09-21', 'end' => '20YY-05-14'],
            'INYR' => ['start' => 'YYYY-08-13', 'end' => 'YYYY-12-12'],
            'SPAN1' => ['start' => '20YY-05-14', 'end' => '20YY-09-15'],
        ];
        $courses = [];
        $students = [];
        $currentstudents = [];
        $studentindex = 0;
        $studentmap = [];
        $moduleleader = $dg->create_user();
        $tutor = $dg->create_user();
        $expectedoutput = '';
        // Create a module for each period type in each session for each location.
        foreach ($sessionmenu as $session) {
            $x = 0;
            $studentmap[$session] = range($studentindex, $studentindex + 2);
            $studentindex++;
            [$start, $end] = explode('/', $session);
            foreach ($periods as $periodkey => $period) {
                // This will fail in 2100.
                $startdate = str_replace(['YYYY', '20YY'], [$start, "20{$end}"], $period['start']);
                $enddate = str_replace(['YYYY', '20YY'], [$start, "20{$end}"], $period['end']);
                foreach ($locations as $locationkey => $location) {
                    $idnumber = "ABC10{$x}_A_PERIOD_{$session}";
                    $course = $dg->create_course([
                        'shortname' => str_replace('PERIOD', $periodkey, $idnumber),
                        'idnumber' => str_replace('PERIOD', $periodkey, $idnumber),
                        'startdate' => strtotime($startdate),
                        'enddate' => strtotime($enddate),
                        'customfield_location' => $location,
                    ]);
                    $courses[$session][$periodkey][$locationkey] = $course;
                    $enrol->add_instance($course);
                    $dg->enrol_user($moduleleader->id, $course->id, 'editingteacher', 'solaissits');
                    $dg->enrol_user($tutor->id, $course->id, 'teacher', 'manual');
                    foreach ($studentmap[$session] as $index) {
                        if (!isset($students[$index][$location])) {
                            $students[$index][$location][] = $dg->create_user();
                            $students[$index][$location][] = $dg->create_user();
                        }
                    }
                    foreach ($studentmap[$session] as $index) {
                        foreach ($students[$index][$location] as $student) {
                            $dg->enrol_user($student->id, $course->id, 'student', 'solaissits');
                            if ($session == $currentsession) {
                                $currentstudents[$location][$student->id] = $student;
                            }
                        }
                    }
                    $x++;
                }
            }
        }
        $task = new location_cohort_sync();
        $task->execute();

        $systemcontext = context_system::instance();
        $expectedoutput = '';
        foreach ($locations as $lokey => $location) {
            $cohort = $DB->get_record('cohort', [
                'idnumber' => $lokey,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
            $this->assertEquals($cohort->name, get_string('studentcohort', 'local_cohorts', ['name' => $location]));
            $members = $DB->get_records('cohort_members', ['cohortid' => $cohort->id], '', 'userid');
            $expectedoutput .= "Start processing for \"{$location}\" cohort\n";
            foreach ($currentstudents[$location] as $currentstudent) {
                $this->assertArrayHasKey($currentstudent->id, $members);
                $expectedoutput .= " - Adding {$currentstudent->username}\n";
            }
            $expectedoutput .= "End processing for \"{$location}\" cohort\n";
        }

        // Add a student to two Solent modules.
        $newstudent = $dg->create_user();
        $dg->enrol_user(
            $newstudent->id,
            $courses[$currentsession]['SEM1']['loc_solent-university_stu']->id,
            'student',
            'solaissits'
        );
        $dg->enrol_user(
            $newstudent->id,
            $courses[$currentsession]['SEM2']['loc_solent-university_stu']->id,
            'student',
            'solaissits'
        );
        $task->execute();
        $expectedoutput .= "Start processing for \"Solent University\" cohort\n" .
            " - Adding {$newstudent->username}\n" .
            "End processing for \"Solent University\" cohort\n" .
            "Start processing for \"QAHE\" cohort\n" .
            "End processing for \"QAHE\" cohort\n" .
            "Start processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n" .
            "End processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n";

        // Run task once more to show that no changes are made.
        $task->execute();
        $expectedoutput .= "Start processing for \"Solent University\" cohort\n" .
            "End processing for \"Solent University\" cohort\n" .
            "Start processing for \"QAHE\" cohort\n" .
            "End processing for \"QAHE\" cohort\n" .
            "Start processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n" .
            "End processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n";

        // Suspend that student.
        $newstudent->suspended = 1;
        user_update_user($newstudent, false, false);
        $task->execute();
        $expectedoutput .= "Start processing for \"Solent University\" cohort\n" .
            " - Removing {$newstudent->username}\n" .
            "End processing for \"Solent University\" cohort\n" .
            "Start processing for \"QAHE\" cohort\n" .
            "End processing for \"QAHE\" cohort\n" .
            "Start processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n" .
            "End processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n";

        // Reenable that student.
        $newstudent->suspended = 0;
        user_update_user($newstudent, false, false);
        $task->execute();
        $expectedoutput .= "Start processing for \"Solent University\" cohort\n" .
            " - Adding {$newstudent->username}\n" .
            "End processing for \"Solent University\" cohort\n" .
            "Start processing for \"QAHE\" cohort\n" .
            "End processing for \"QAHE\" cohort\n" .
            "Start processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n" .
            "End processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n";

        // Disable enrolment on one course - student still be enrolled on another,
        // so this won't affect the cohort membership.
        $dg->enrol_user($newstudent->id,
            $courses[$currentsession]['SEM1']['loc_solent-university_stu']->id,
            'student',
            'solaissits',
            0,
            0,
            ENROL_USER_SUSPENDED
        );
        $enrolinstance = $DB->get_record('enrol', [
            'courseid' => $courses[$currentsession]['SEM1']['loc_solent-university_stu']->id,
            'enrol' => 'solaissits',
        ]);
        $enrolment = $DB->get_record('user_enrolments', [
            'userid' => $newstudent->id,
            'enrolid' => $enrolinstance->id,
        ]);
        $this->assertEquals(ENROL_USER_SUSPENDED, $enrolment->status);
        $task->execute();
        // No changes.
        $expectedoutput .= "Start processing for \"Solent University\" cohort\n" .
            "End processing for \"Solent University\" cohort\n" .
            "Start processing for \"QAHE\" cohort\n" .
            "End processing for \"QAHE\" cohort\n" .
            "Start processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n" .
            "End processing for \"A really long * name for an institution that might " .
                "get trimmed at some point, with some ! punctuation\" cohort\n";
        $this->expectOutputString($expectedoutput);
    }

    /**
     * Helper to enable the solaissits enrolment plugin.
     *
     * @return void
     */
    protected function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['solaissits'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }
}
