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

namespace local_cohorts\tables;

use context_system;
use lang_string;
use moodle_url;
use table_sql;

/**
 * Class cohorts_members_table
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cohorts_members_table extends table_sql {
    /**
     * Constructor
     *
     * @param string $uniqueid
     * @param array $filters Parameters required to select the cohort.
     */
    public function __construct($uniqueid, $filters) {
        parent::__construct($uniqueid);

        $columns = [
            'id',
            'fullname',
            'auth',
            'username',
            'email',
            'idnumber',
            'department',
            'location',
            'lastlogin',
        ];

        $columnheadings = [
            'id',
            new lang_string('fullname'),
            new lang_string('authentication'),
            new lang_string('username'),
            new lang_string('email'),
            new lang_string('idnumber'),
            new lang_string('department'),
            new lang_string('location'),
            new lang_string('lastlogin'),
        ];

        $this->define_columns($columns);
        $this->define_headers($columnheadings);
        $this->define_baseurl(new moodle_url("/local/cohorts/members.php", ['cohortid' => $filters['cohortid']]));
        $userfieldsapi = \core_user\fields::for_identity(context_system::instance(), false)->with_userpic();
        $userfields = $userfieldsapi->get_sql('u', false, '', $this->useridfield, false)->selects;
        $fields = 'cm.id, cm.timeadded, u.auth, u.username, u.lastlogin, ' . $userfields;
        $from = "{cohort_members} cm
            JOIN {user} u ON u.id = cm.userid";
        $where = 'cm.cohortid = :cohortid ORDER BY u.firstname, u.lastname';
        $this->set_sql($fields, $from, $where, [
            'cohortid' => $filters['cohortid'],
        ]);
    }

    /**
     * Last login
     *
     * @param object $row
     * @return string
     */
    public function col_lastlogin($row): string {
        if ($row->lastlogin == 0) {
            return get_string('never');
        }
        return userdate($row->lastlogin);
    }
}
