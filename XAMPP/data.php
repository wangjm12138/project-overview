<?php
//error_reporting(E_ALL);
//ini_set('display_errors', '1');
$config = parse_ini_file("config.ini");
$path = $config["path"];
$data_type = "";
if (!empty($_POST)) {
	$project = strtoupper($_POST["project"]);
	$select = "";
	$opt = true;
	function buildSelect($type) {
		global $opt;
		global $select;
		if ($opt) {
			$select = "$type IN ('" . implode("','", explode(",", $_POST[$type])) . "') ";
			$opt = false;
		} else {
			$select .= "AND $type IN ('" . implode("','", explode(",", $_POST[$type])) . "') ";
		}
	}
	if (isset($_POST["targetbuild"])) {
		buildSelect("targetbuild");
	}
	if (isset($_POST["release"])) {
		buildSelect("release");
	}
	if (isset($_POST["status"])) {
		buildSelect("status");
	}
	if (isset($_POST["team"])) {
		buildSelect("team");
	}
	if (isset($_POST["priority"])) {
		buildSelect("priority");
	}
	if (isset($_POST["labels"])) {
		buildSelect("labels");
	}
	//Jira
	if (isset($_POST["platform"]) and $_POST["platform"] == "jira") {
		if (isset($_POST["type"])) {
			buildSelect("type");
		}
		$data_type = "jira";
		$table = "rawjiradata";
		// Table's primary key
		$primaryKey = 'key';
		 
		// SQL server connection information
		$sql_details = array(
			'db'   => "$path/db/jira/$project.db"
		);
		// Array of database columns which should be read and sent back to DataTables.
		if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) {
			$columns = array(
				array( 'db' => 'targetbuild', 'dt' => 'targetbuild' ),
				array( 'db' => 'key',  'dt' => 'keyy' ),
				array( 'db' => 'name',   'dt' => 'name' ),
				array( 'db' => 'type',     'dt' => 'type' ),
				array( 'db' => 'status',     'dt' => 'status' ),
				array( 'db' => 'team',     'dt' => 'team' ),
				array( 'db' => 'priority',     'dt' => 'priority' )
			);
			
		} else {
			$columns = array(
				array( 'db' => 'targetbuild', 'dt' => 'targetbuild' ),
				array( 'db' => 'key',  'dt' => 'keyy' ),
				array( 'db' => 'name',   'dt' => 'name' ),
				array( 'db' => 'type',     'dt' => 'type' ),
				array( 'db' => 'status',     'dt' => 'status' ),
				array( 'db' => 'team',     'dt' => 'team' ),
				array( 'db' => 'priority',     'dt' => 'priority' ),
				array( 'db' => 'jama',     'dt' => 'jama' ),
				array( 'db' => 'jamaid',     'dt' => 'jamaid' )
			);
		}
	//Jama
	} else {
		if (isset($_POST["type"])) {
			$type = $_POST["type"];
			// DB table to use
			if ($type == "feattest") {
				$table = "allfeatures";
			} elseif ($type == "reqtest") {
				$table = "allrequirements";
			} else {
				$table = "all$type";
			}

			if ($type == "designspec" or $type == "defects" or $type == "testapproval") {
				if ($type == "defects") {
					// Array of database columns which should be read and sent back to DataTables.
					$columns = array(
						array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
						array( 'db' => 'name',  'dt' => 'name' ),
						array( 'db' => 'status',   'dt' => 'status' ),
						array( 'db' => 'rel',     'dt' => 'rel' ),
						array( 'db' => 'team',     'dt' => 'team' ),
						array( 'db' => 'jira',     'dt' => 'jira' ),
						array( 'db' => 'priority',     'dt' => 'priority' ),
						array( 'db' => 'id', 'dt' => 'id' )
					);
				} else {
					$columns = array(
						array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
						array( 'db' => 'name',  'dt' => 'name' ),
						array( 'db' => 'status',   'dt' => 'status' ),
						array( 'db' => 'rel',     'dt' => 'rel' ),
						array( 'db' => 'team',     'dt' => 'team' ),
						array( 'db' => 'id', 'dt' => 'id' )
					);
				}
			} elseif ($type == "changes") {
				$select = "";
				// Array of database columns which should be read and sent back to DataTables.
				$columns = array(
					array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
					array( 'db' => 'name',  'dt' => 'name' ),
					array( 'db' => 'status',   'dt' => 'status' ),
					array( 'db' => 'rel',     'dt' => 'rel' ),
					array( 'db' => 'priority',     'dt' => 'priority' ),
					array( 'db' => 'requester',     'dt' => 'requester' ),
					array( 'db' => 'id', 'dt' => 'id' )
				);			
			} elseif ($type == "coverage") {
				$select = "";
				// Array of database columns which should be read and sent back to DataTables.
				$columns = array(
					array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
					array( 'db' => 'name',  'dt' => 'name' ),
					array( 'db' => 'status',   'dt' => 'status' ),
					array( 'db' => 'rel',     'dt' => 'rel' ),
					array( 'db' => 'verify',     'dt' => 'verify' ),
					array( 'db' => 'missing',     'dt' => 'missing' ),
					array( 'db' => 'id', 'dt' => 'id' )
				);			
			} elseif ($type == "feattest") {
				$select = "";
				// Array of database columns which should be read and sent back to DataTables.
				$columns = array(
					array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
					array( 'db' => 'name',  'dt' => 'name' ),
					array( 'db' => 'test_status',   'dt' => 'status' ),
					array( 'db' => 'rel',     'dt' => 'rel' ),
					array( 'db' => 'id', 'dt' => 'id' )
				);
			} elseif ($type == "reqtest") {
				$select = "";
				// Array of database columns which should be read and sent back to DataTables.
				$columns = array(
					array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
					array( 'db' => 'name',  'dt' => 'name' ),
					array( 'db' => 'test_status',   'dt' => 'status' ),
					array( 'db' => 'rel',     'dt' => 'rel' ),
					array( 'db' => 'team',     'dt' => 'team' ),
					array( 'db' => 'id', 'dt' => 'id' )
				);
			} else {
				$select = "";
				// Array of database columns which should be read and sent back to DataTables.
				$columns = array(
					array( 'db' => 'uniqueid', 'dt' => 'uniqueid' ),
					array( 'db' => 'name',  'dt' => 'name' ),
					array( 'db' => 'status',   'dt' => 'status' ),
					array( 'db' => 'rel',     'dt' => 'rel' ),
					array( 'db' => 'id', 'dt' => 'id' )
				);		
			}
		} else {
			$testplan = $_POST["testplan"];
			if (isset($_POST["status"]) and isset($_POST["date"]) and !isset($_POST["rel"])) {
				$select = "testplan_id = $testplan AND status = '".$_POST["status"]."' AND executionDate = '".$_POST["date"]."'";
			} elseif (isset($_POST["status"]) and isset($_POST["date"]) and isset($_POST["rel"])) {
				$rel = implode("','", explode("," , $_POST["rel"]));
				$select = "testplan_id = $testplan AND status = '".$_POST["status"]."' AND executionDate = '".$_POST["date"]."' AND rel IN ('$rel')";
			} elseif (isset($_POST["rel"])) {
				$rel = implode("','", explode("," , $_POST["rel"]));
				$select = "testplan_id = $testplan AND rel IN ('$rel')";
			} else {
				$select = "testplan_id = $testplan";
			}
			// DB table to use
			$table = 'testcases';
			// Array of database columns which should be read and sent back to DataTables.
			$columns = array(
				array( 'db' => 'uniqueid',  'dt' => 'uniqueid' ),
				array( 'db' => 'name',  'dt' => 'name' ),
				array( 'db' => 'status',   'dt' => 'status' ),
				array( 'db' => 'rel',     'dt' => 'rel' ),
				array( 'db' => 'testgroup_name',     'dt' => 'testgroup_name' ),
				array( 'db' => 'testcycle_name',     'dt' => 'testcycle_name' ),
				array( 'db' => 'executionDate',      'dt' => 'executionDate' ),
				array( 'db' => 'testcycle_id',      'dt' => 'testcycle_id' ),
				array( 'db' => 'id', 'dt' => 'id' )
			);
		}
		// Table's primary key
		$primaryKey = 'id';
		$data_type = "jama";
		
		$servername = "localhost";
		$username = "root";
		$password = "jabra2020";
		$dbname = $project;
		
		// SQL server connection information
		$sql_details = array('user' => $username, 'pass' => $password, 'db' => $dbname, 'host' => $servername);
	}
	if ($data_type == "jira") {
		require( 'ssp.class.php' );
	} else {
		require( 'ssp.class_jama.php' );
	}
	echo json_encode(
		SSP::complex( $_POST, $sql_details, $table, $primaryKey, $columns, null, $select )
	);
} else {
	echo "Nothing to see here";
}