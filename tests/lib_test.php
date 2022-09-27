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
 * @package   block_package
 * @author    Mark Sharp <mark.sharp@solent.ac.uk>
 * @copyright 2022 Solent University {@link https://www.solent.ac.uk}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_cohorts;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

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
        academic();
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
        academic();
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
        support();
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
        support();
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
}
