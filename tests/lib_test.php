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

    public function test_academic() {
        // Create cohort.
        $cohort = $this->getDataGenerator()->create_cohort([
            'name' => 'Academic',
            'idnumber' => 'academic'
        ]);
        // Create people to add.
        $academics = [];
        $nonacademics = [];
        $oldacademicsactive = [];
        $academicssuspended = [];
        for ($x = 0; $x < 5; $x++) {
            $academics[] = $this->getDataGenerator()->create_user([
                'department' => 'academic'
            ]);
            $nonacademics[] = $this->getDataGenerator()->create_user();
            $oldacademicsactive[] = $this->getDataGenerator()->create_user();
            $academicssuspended[] = $this->getDataGenerator()->create_user([
                'suspended' => 1,
                'department' => 'academic'
            ]);
        }
        // Prefill the cohort with people that will be removed.
        foreach ($oldacademicsactive as $academic) {
            cohort_add_member($cohort->id, $academic->id);
        }
        foreach ($academicssuspended as $suspended) {
            cohort_add_member($cohort->id, $suspended->id);
        }
        academic();

    }
}
