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
use core_customfield\category;
use core_customfield\field;
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
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();
        // Run the test as an admin user, because the customfield location_name is locked.
        // This is only relevant to testing in local where the field is already set-up.
        $this->setAdminUser();
        // This test sets up courses that fall into 3 locations.
        // Each location has courses for different semester patterns.
        // Each location has courses set up from 2020/21 with a minimum of 2 years in the future.
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
        $this->setup_customfield('location_name');
        $this->setup_customfield('level_code');
        $locations = [
            'loc_a-really-long-name-for-an-institution-that-might-get-trimmed-at-some-point-with-some-punctua_stu' =>
                'A really long * name for an institution that might get trimmed at some point, with some ! punctuation',
            'loc_qahe_stu' => 'QAHE',
            'loc_solent-university_stu' => 'Solent University',
        ];
        $currentsession = helper::get_current_session();
        // Start with oldest session first.
        $sessionmenu = array_reverse(helper::get_session_menu());
        $periods = [
            'SEM1' => ['start' => 'YYYY-09-21', 'end' => '20YY-01-14', 'level' => '03'],
            'SEM2' => ['start' => '20YY-01-21', 'end' => '20YY-05-14', 'level' => '04'],
            'S1S2' => ['start' => 'YYYY-09-21', 'end' => '20YY-05-14', 'level' => '05'],
            'INYR' => ['start' => 'YYYY-08-13', 'end' => 'YYYY-12-12', 'level' => '06'],
            'SPAN1' => ['start' => '20YY-05-14', 'end' => '20YY-09-15', 'level' => '07'],
        ];
        $courses = [];
        $students = [];
        $currentstudents = [
            'location' => [],
            'loclevel' => [],
        ];
        $studentindex = 0;
        $studentmap = [];
        $moduleleader = $dg->create_user();
        $tutor = $dg->create_user();
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
                        'customfields' => [
                            [
                                'shortname' => 'location_name',
                                'value' => $location,
                            ],
                            [
                                'shortname' => 'level_code',
                                'value' => $period['level'],
                            ],
                        ],
                    ]);
                    $courses[$session][$periodkey][$locationkey] = $course;
                    $enrol->add_instance($course);
                    $dg->enrol_user($moduleleader->id, $course->id, 'editingteacher', 'solaissits');
                    $dg->enrol_user($tutor->id, $course->id, 'teacher', 'manual');
                    foreach ($studentmap[$session] as $index) {
                        if (!isset($students[$index][$location])) {
                            $shortlocation = substr(helper::slugify($location), 0, 35);
                            $students[$index][$location][] = $dg->create_user([
                                'username' => 'stud' . $index . $shortlocation . '1',
                            ]);
                            $students[$index][$location][] = $dg->create_user([
                                'username' => 'stud' . $index . $shortlocation . '2',
                            ]);
                        }
                    }
                    foreach ($studentmap[$session] as $index) {
                        foreach ($students[$index][$location] as $student) {
                            $dg->enrol_user($student->id, $course->id, 'student', 'solaissits');
                            if ($session == $currentsession) {
                                $currentstudents['location'][$location][$student->id] = $student;
                                $currentstudents['loclevel'][$location . '+' . $period['level']][$student->id] = $student;
                            }
                            // This accounts for any spanning modules.
                            $currentlyrunning = ($course->startdate <= time() && $course->enddate >= time());
                            if ($currentlyrunning) {
                                $currentstudents['location'][$location][$student->id] = $student;
                                $currentstudents['loclevel'][$location . '+' . $period['level']][$student->id] = $student;
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
        // Using Regex here as the output is too complicated to exactly match string.
        $expectedoutputregex = "#Start processing for \".*\" cohort#";
        $this->expectOutputRegex($expectedoutputregex);
        foreach ($locations as $lokey => $location) {
            $cohort = $DB->get_record('cohort', [
                'idnumber' => $lokey,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
            $this->assertEquals($cohort->name, get_string('studentcohort', 'local_cohorts', ['name' => $location]));
            $cohortsize = $DB->count_records('cohort_members', [
                'cohortid' => $cohort->id,
            ]);
            $this->assertCount($cohortsize, $currentstudents['location'][$location]);
            foreach ($currentstudents['location'][$location] as $currentstudent) {
                $this->assertTrue(cohort_is_member($cohort->id, $currentstudent->id));
            }
            // Get level cohorts for location here.
            if (!$task->skiplevel) {
                foreach ($periods as $periodkey => $period) {
                    $level = $period['level'];
                    $loclevname = $location . ' level ' . $level . ' students';
                    [$loclevcohort, $loclevstatus] = helper::get_cohort($loclevname, '', $systemcontext);
                    foreach ($currentstudents['loclevel'][$location . '+' . $level] as $currentstudent) {
                        $this->assertTrue(cohort_is_member($loclevcohort->id, $currentstudent->id));
                    }
                }
            }
        }

        // Check yo-yo enrolments don't happen - run the task again, and make sure no enrolments have changed.
        $task->execute();
        foreach ($locations as $lokey => $location) {
            $cohort = $DB->get_record('cohort', [
                'idnumber' => $lokey,
                'contextid' => $systemcontext->id,
                'component' => 'local_cohorts',
            ]);
            $this->assertEquals($cohort->name, get_string('studentcohort', 'local_cohorts', ['name' => $location]));
            $cohortsize = $DB->count_records('cohort_members', [
                'cohortid' => $cohort->id,
            ]);
            $this->assertCount($cohortsize, $currentstudents['location'][$location]);
            foreach ($currentstudents['location'][$location] as $currentstudent) {
                $this->assertTrue(cohort_is_member($cohort->id, $currentstudent->id));
            }
            // Get level cohorts for location here.
            if (!$task->skiplevel) {
                foreach ($periods as $periodkey => $period) {
                    $level = $period['level'];
                    $loclevname = $location . ' level ' . $level . ' students';
                    [$loclevcohort, $loclevstatus] = helper::get_cohort($loclevname, '', $systemcontext);
                    foreach ($currentstudents['loclevel'][$location . '+' . $level] as $currentstudent) {
                        $this->assertTrue(cohort_is_member($loclevcohort->id, $currentstudent->id));
                    }
                }
            }
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
        $solentcohort = $DB->get_record('cohort', [
            'idnumber' => 'loc_solent-university_stu',
            'contextid' => $systemcontext->id,
            'component' => 'local_cohorts',
        ]);
        $countbefore = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $task->execute();
        $countafter = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $this->assertSame($countbefore + 1, $countafter);
        $this->assertTrue(cohort_is_member($solentcohort->id, $newstudent->id));

        // Run task once more to show that no changes are made.
        $countbefore = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $task->execute();
        $countafter = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $this->assertSame($countbefore, $countafter);
        $this->assertTrue(cohort_is_member($solentcohort->id, $newstudent->id));

        // Suspend that student.
        $countbefore = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $newstudent->suspended = 1;
        user_update_user($newstudent, false, false);
        $task->execute();
        $countafter = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $this->assertSame($countbefore - 1, $countafter);
        $this->assertFalse(cohort_is_member($solentcohort->id, $newstudent->id));

        // Reenable that student.
        $countbefore = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $newstudent->suspended = 0;
        user_update_user($newstudent, false, false);
        $task->execute();
        $countafter = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $this->assertSame($countbefore + 1, $countafter);
        $this->assertTrue(cohort_is_member($solentcohort->id, $newstudent->id));

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
        $countbefore = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $task->execute();
        $countafter = $DB->count_records('cohort_members', [
            'cohortid' => $solentcohort->id,
        ]);
        $this->assertSame($countbefore, $countafter);
        $this->assertTrue(cohort_is_member($solentcohort->id, $newstudent->id));
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

    /**
     * Set up a customfield customfield, if required.
     *
     * @param string $shortname
     * @return void
     */
    protected function setup_customfield($shortname) {
        $category = category::get_record([
            'name' => 'Student Records System',
            'area' => 'course',
            'component' => 'core_course',
        ]);
        if (!$category) {
            // No category, so create and use it.
            $category = new category(0, (object)[
                'name' => 'Student Records System',
                'description' => 'Fields managed by the university\'s Student records system. Do not change unless asked to.',
                'area' => 'course',
                'component' => 'core_course',
                'contextid' => context_system::instance()->id,
            ]);
            $category->save();
        }
        $field = field::get_record([
            'shortname' => $shortname,
            'categoryid' => $category->get('id'),
        ]);
        if ($field) {
            // Already exists. Nothing to do here.
            return;
        }
        $this->getDataGenerator()->create_custom_field([
            'categoryid' => $category->get('id'),
            'type' => 'text',
            'shortname' => $shortname,
            'name' => ucwords(str_replace('_', ' ', $shortname)),
        ]);
    }
}
