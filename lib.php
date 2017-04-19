<?php
global $CFG;
require_once("$CFG->dirroot/config.php");
require_once("$CFG->dirroot/lib/adminlib.php");
require_once("$CFG->dirroot/cohort/lib.php");

function add_academics(){
	
	global $DB;
	$usersall = array();
	$table = 'user';
	$conditions = array('deleted'=>0, 'suspended'=>0, 'department'=>'academic');
	$fields = 'id';
	$sortby = '';					
	$resultusersall = $DB->get_records_menu($table, $conditions, $sortby, $fields);	
	
	$cohortid = 6;

	foreach($resultusersall as $key => $value){
		 $usersall[$key] = $key;						
	}		

	if (empty($usersall)) {
		echo "No users </ br>";
	} else{
		// process request
		foreach ($usersall as $user) {
			if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$user))) {
				//echo $user . "<br />";
				cohort_add_member($cohortid, $user);
			}
		}		
	}

	unset($usersall);
}

function add_support(){
	
	global $DB;
	$usersall = array();
	$table = 'user';
	$conditions = array('deleted'=>0, 'suspended'=>0, 'department'=>'support');
	$fields = 'id';
	$sortby = '';					
	$resultusersall = $DB->get_records_menu($table, $conditions, $sortby, $fields);	
	
	$cohortid = 7;

	foreach($resultusersall as $key => $value){
		 $usersall[$key] = $key;						
	}		

	if (empty($usersall)) {
		echo "No users </ br>";
	} else{
		// process request
		foreach ($usersall as $user) {
			if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$user))) {				
				cohort_add_member($cohortid, $user);
			}
		}		
	}

	unset($usersall);	
}

function add_management(){
	
	global $DB;
	$usersall = array();
	$table = 'user';
	$conditions = array('deleted'=>0, 'suspended'=>0, 'department'=>'management');
	$fields = 'id';
	$sortby = '';					
	$resultusersall = $DB->get_records_menu($table, $conditions, $sortby, $fields);	
	
	$cohortid = 8;

	foreach($resultusersall as $key => $value){
		 $usersall[$key] = $key;						
	}		

	if (empty($usersall)) {
		echo "No users </ br>";
	} else{
		// process request
		foreach ($usersall as $user) {
			if (!$DB->record_exists('cohort_members', array('cohortid'=>$cohortid, 'userid'=>$user))) {
				cohort_add_member($cohortid, $user);
			}
		}		
	}

	unset($usersall);		
}