<?php
global $CFG;
require_once("$CFG->dirroot/config.php");
require_once("$CFG->dirroot/lib/adminlib.php");
require_once("$CFG->dirroot/cohort/lib.php");

function academic(){
    global $DB;

    // add new users to cohort
    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0,'academic');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber=>'academic'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else{
        foreach ($resultusersall as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
            if($user->suspended == 1 && cohort_is_member($cohortid->id, $user->id)){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }

    //remove invalid users from cohort
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else{
        // process request
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id'=>$user->userid));
            if($memberdetails->department != 'academic' || $memberdetails->suspended == 1){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }
}

function support(){
    global $DB;

    // add new users to cohort
    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0,'support');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber=>'support'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else{
        foreach ($resultusersall as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }

            if($user->suspended == 1 && cohort_is_member($cohortid->id, $user->id)){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }

    //remove invalid users from cohort
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else{
        // process request
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id'=>$user->userid));
            if($memberdetails->department != 'support' || $memberdetails->suspended == 1){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }
}

function management(){

    global $DB;

    // add new users to cohort
    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND department = ?");
    $params = array(0,'management');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber=>'management'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else{
        foreach ($resultusersall as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
            if($user->suspended == 1 && cohort_is_member($cohortid->id, $user->id)){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }

    //remove invalid users from cohort
    $sql = ("SELECT * FROM mdl_cohort_members c JOIN mdl_user u ON u.id = c.userid WHERE c.cohortid = ?");
    $params = array($cohortid->id);
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else{
        // process request
        foreach ($cohortmembers as $user) {
            $memberdetails = $DB->get_record('user', array('id'=>$user->userid));
            if($memberdetails->department != 'management' || $memberdetails->suspended == 1){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }
}


function mydevelopment(){

    global $DB;

    $sql = ("SELECT * FROM mdl_user WHERE deleted = ? AND (department = ? OR department = ? OR department = ?) AND email NOT LIKE ? AND email NOT LIKE ? AND email NOT LIKE ? AND email LIKE ?");
    $params = array(0, 'support', 'academic', 'management', 'academic%', 'consultant%', 'jobshop%', '%@solent.ac.uk');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber=>'mydevelopment'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else{
        // process request
        foreach ($resultusersall as $user) {
            if ($user->suspended == 0 && !cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
            if($user->suspended == 1 && cohort_is_member($cohortid->id, $user->id)){
                cohort_remove_member($cohortid->id, $user->id);
            }
        }
    }
}

function student6(){

    global $DB;

    // add new users to cohort
    $sql = ("SELECT *
            FROM mdl_user
            WHERE (timecreated > unix_timestamp((NOW()) - INTERVAL 6 MONTH)
            AND  deleted = ? AND suspended = ? AND department = ?)");
    $params = array(0, 0,'student');
    $resultusersall = $DB->get_records_sql($sql, $params);

    $cohortid = $DB->get_record('cohort', array(idnumber=>'student6'), 'id');

    if (empty($resultusersall)) {
        echo "No users </ br>";
    } else{
        foreach ($resultusersall as $user) {
            if (!cohort_is_member($cohortid->id, $user->id)) {
                cohort_add_member($cohortid->id, $user->id);
            }
        }
    }

    // remove invalid users from cohort
    $sql = ("SELECT u.id, c.id cohortid
            FROM mdl_cohort_members cm
            JOIN mdl_user u ON u.id = cm.userid
            JOIN mdl_cohort c ON c.id = cm.cohortid
            WHERE c.idnumber = ?
            AND (u.timecreated < unix_timestamp((NOW()) - INTERVAL 6 MONTH)
            OR (suspended = 1 OR deleted = 1 OR department != 'student'))");
    $params = array($cohortid->idnumber = 'student6');
    $cohortmembers = $DB->get_records_sql($sql, $params);

    if (empty($cohortmembers)) {
        echo "No users </ br>";
    } else{
        // process request
        foreach ($cohortmembers as $user) {
            cohort_remove_member($user->cohortid, $user->id);
        }
    }
}
