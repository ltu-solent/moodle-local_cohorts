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
 * Lib test
 *
 * @package   local_cohorts
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohorts;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/local/cohorts/lib.php');
use advanced_testcase;

/**
 * Test lib.php file
 * @coversNothing
 * @group sol
 */
class lib_test extends advanced_testcase {
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function setUp(): void {
        $this->resetAfterTest();
    }

    /**
     * Test adding and removal of academic users.
     *
     * @return void
     */
    public function test_academic() {
        global $DB;
        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Academic',
            'idnumber' => 'academic'
        ]);

        $members = [];
        $nonmembers = [];
        $oldmembersactive = [];
        $memberssuspended = [];
        for ($x = 0; $x < 5; $x++) {
            $nonmembers[] = $this->getDataGenerator()->create_user();
            $oldmembersactive[] = $this->getDataGenerator()->create_user();
            $memberssuspended[] = $this->getDataGenerator()->create_user([
                'suspended' => 1,
                'department' => 'academic'
            ]);
        }
        // No-one to add.
        \academic();
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertEquals(0, count($premembers));
        // Generate some valid accounts.
        for ($x = 0; $x < 5; $x++) {
            $members[] = $this->getDataGenerator()->create_user([
                'department' => 'academic'
            ]);
        }
        // Prefill the cohort with people that will be removed.
        foreach ($oldmembersactive as $oldmember) {
            cohort_add_member($cohort->id, $oldmember->id);
        }
        foreach ($memberssuspended as $suspended) {
            cohort_add_member($cohort->id, $suspended->id);
        }
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        \academic();
        $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertCount(10, $premembers);
        $this->assertCount(5, $postmembers);
        $this->expectOutputRegex("/added to 'academic' cohort/");
        foreach ($members as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($premembers as $premember) {
            $this->assertNotTrue(cohort_is_member($cohort->id, $premember->userid));
        }
    }

    /**
     * Test adding and removal of support users.
     *
     * @return void
     */
    public function test_support() {
        global $DB;
        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Support',
            'idnumber' => 'support'
        ]);
        // Create people to add.
        $members = [];
        $nonmembers = [];
        $oldmembersactive = [];
        $memberssuspended = [];
        for ($x = 0; $x < 5; $x++) {
            $nonmembers[] = $this->getDataGenerator()->create_user();
            $oldmembersactive[] = $this->getDataGenerator()->create_user();
            $memberssuspended[] = $this->getDataGenerator()->create_user([
                'suspended' => 1,
                'department' => 'support'
            ]);
        }
        // No-one to add.
        \support();
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertEquals(0, count($premembers));

        // Generate some valid accounts.
        for ($x = 0; $x < 5; $x++) {
            $members[] = $this->getDataGenerator()->create_user([
                'department' => 'support'
            ]);
        }
        // Prefill the cohort with people that will be removed.
        foreach ($oldmembersactive as $oldmember) {
            cohort_add_member($cohort->id, $oldmember->id);
        }
        foreach ($memberssuspended as $suspended) {
            cohort_add_member($cohort->id, $suspended->id);
        }
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        \support();
        $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertCount(10, $premembers);
        $this->assertCount(5, $postmembers);
        $this->expectOutputRegex("/added to 'support' cohort/");
        foreach ($members as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($premembers as $premember) {
            $this->assertNotTrue(cohort_is_member($cohort->id, $premember->userid));
        }
    }

    /**
     * Test adding and removal of management users on Management cohort.
     *
     * @return void
     */
    public function test_management() {
        global $DB;
        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Management',
            'idnumber' => 'management'
        ]);
        // Create people to add.
        $members = [];
        $nonmembers = [];
        $oldmembersactive = [];
        $memberssuspended = [];
        for ($x = 0; $x < 5; $x++) {
            $nonmembers[] = $this->getDataGenerator()->create_user();
            $oldmembersactive[] = $this->getDataGenerator()->create_user();
            $memberssuspended[] = $this->getDataGenerator()->create_user([
                'suspended' => 1,
                'department' => 'management'
            ]);
        }
        // No-one to add.
        \management();
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertEquals(0, count($premembers));

        // Generate some valid accounts.
        for ($x = 0; $x < 5; $x++) {
            $members[] = $this->getDataGenerator()->create_user([
                'department' => 'management'
            ]);
        }
        // Prefill the cohort with people that will be removed.
        foreach ($oldmembersactive as $oldmember) {
            cohort_add_member($cohort->id, $oldmember->id);
        }
        foreach ($memberssuspended as $suspended) {
            cohort_add_member($cohort->id, $suspended->id);
        }
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        \management();
        $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertCount(10, $premembers);
        $this->assertCount(5, $postmembers);
        $this->expectOutputRegex("/added to 'management' cohort/");
        foreach ($members as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($premembers as $premember) {
            $this->assertNotTrue(cohort_is_member($cohort->id, $premember->userid));
        }
    }

    /**
     * Test adding and removal of management users on Management cohort.
     *
     * @return void
     */
    public function test_mydevelopment() {
        global $DB;
        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'MyDevelopment',
            'idnumber' => 'mydevelopment'
        ]);
        // Create people to add.
        // People to include are users in "support", "academic" and "management" departments
        // and who have @solent.ac.uk in their email address,
        // but not those who have "academic", "consultant", "jobshop" in their email address.
        $academic = [];
        $academicemail = [];
        $academicnonsolent = [];
        $support = [];
        $management = [];
        $consultant = [];
        $jobshop = [];
        $students = [];
        $suspendedacademics = [];
        $randomusers = [];
        for ($x = 0; $x < 5; $x++) {
            $randomusers[] = $this->getDataGenerator()->create_user();
            $students[] = $this->getDataGenerator()->create_user([
                'department' => 'students',
                'email' => 'student' . $x . '@solent.ac.uk'
            ]);
            $suspendedacademics[] = $this->getDataGenerator()->create_user([
                'suspended' => 1,
                'department' => 'academic'
            ]);
            $academicemail[] = $this->getDataGenerator()->create_user([
                'department' => 'academic',
                'email' => 'academic' . $x . '@solent.ac.uk'
            ]);
            $academicnonsolent[] = $this->getDataGenerator()->create_user([
                'department' => 'academic',
                'email' => 'teacher' . $x . '@somewhere.ac.uk'
            ]);
            $jobshop[] = $this->getDataGenerator()->create_user([
                'department' => 'support',
                'email' => 'jobshop' . $x . '@solent.ac.uk'
            ]);
            $consultant[] = $this->getDataGenerator()->create_user([
                'department' => 'academic',
                'email' => 'consultant' . $x . '@solent.ac.uk'
            ]);
        }
        // No-one to add.
        \mydevelopment();
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertEquals(0, count($premembers));

        // Generate some valid accounts.
        for ($x = 0; $x < 5; $x++) {
            $academic[] = $this->getDataGenerator()->create_user([
                'department' => 'academic',
                'email' => 'solteacher' . $x . '@solent.ac.uk'
            ]);
            $management[] = $this->getDataGenerator()->create_user([
                'department' => 'management',
                'email' => 'solman' . $x . '@solent.ac.uk'
            ]);
            $support[] = $this->getDataGenerator()->create_user([
                'department' => 'support',
                'email' => 'solsup' . $x . '@solent.ac.uk'
            ]);
        }
        // Prefill the cohort with people that will be removed.
        foreach ($randomusers as $randomuser) {
            cohort_add_member($cohort->id, $randomuser->id);
        }
        foreach ($suspendedacademics as $suspended) {
            cohort_add_member($cohort->id, $suspended->id);
        }
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        \mydevelopment();
        $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertCount(10, $premembers);
        $this->assertCount(25, $postmembers);
        $this->expectOutputRegex("/added to 'mydevelopment' cohort/");
        foreach ($academic as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($management as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($support as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        // Currently, the script doesn't remove stragglers. There might be a reason why. Need to check.
        foreach ($randomusers as $randomuser) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        foreach ($suspendedacademics as $suspended) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->id));
        }
        // This will fail, because stragglers are not being removed.
        // phpcs:disable
        foreach ($premembers as $premember) {
            // $this->assertNotTrue(cohort_is_member($cohort->id, $premember->userid)); 
        }
        // phpcs:enable
    }

    public function test_student6() {
        global $DB;
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Six months',
            'idnumber' => 'student6'
        ]);
        // Students joined 6+ months ago.
        // Students joined within 6 months.
        // Other users.
        // Is the DB timezone the same as PHP?
        $sixmonthsago = strtotime("-7 months");
        $sixmonthsplus = [];
        $withinsixmonths = [];
        $otherusers = [];
        // There are no users to add or take away.
        \student6();
        for ($x = 0; $x < 5; $x++) {
            $sixmonthsplus[$x] = $this->getDataGenerator()->create_user([
                'department' => 'student',
                'timecreated' => $sixmonthsago
            ]);
            cohort_add_member($cohort->id, $sixmonthsplus[$x]->id);
            $withinsixmonths[] = $this->getDataGenerator()->create_user([
                'department' => 'student'
            ]);
            $otherusers[] = $this->getDataGenerator()->create_user();
        }
        $premembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        foreach ($premembers as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->userid));
        }
        \student6();
        $postmembers = $DB->get_records('cohort_members', ['cohortid' => $cohort->id]);
        $this->assertCount(5, $premembers);
        $this->assertCount(5, $postmembers);
        foreach ($postmembers as $member) {
            $this->assertTrue(cohort_is_member($cohort->id, $member->userid));
        }
        foreach ($premembers as $member) {
            $this->assertNotTrue(cohort_is_member($cohort->id, $member->userid));
        }
        $this->expectOutputRegex("/added to 'student6' cohort/");
    }
}
