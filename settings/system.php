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
 * TODO describe file system
 *
 * @package    local_cohorts
 * @copyright  2024 Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('local_cohorts_system', new lang_string('systemcohorts', 'local_cohorts'));

$name = 'local_cohorts/staffcohorts';
$title = new lang_string('staffcohorts', 'local_cohorts');
$desc = new lang_string('staffcohorts_desc', 'local_cohorts');
$default = 'academic,management,support';
$setting = new admin_setting_configtext($name, $title, $desc, $default, PARAM_TEXT);
$page->add($setting);

$name = 'local_cohorts/emailexcludepattern';
$title = new lang_string('emailexcludepattern', 'local_cohorts');
$desc = new lang_string('emailexcludepattern_desc', 'local_cohorts');
$default = 'academic,consultant,jobshop';
$setting = new admin_setting_configtext($name, $title, $desc, $default, PARAM_TEXT);
$page->add($setting);

$settings->add($page);
