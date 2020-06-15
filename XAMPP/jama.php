<?php
//ini_set('display_errors', 'On');
ini_set('max_execution_time', 300);
$config = parse_ini_file("config.ini");
require_once('getcolor.php'); //required for generating or finding the right color for the series
$path = $config["path"];
$pypath = $config["pypath"];
$type = $_GET["type"]; //Get url argument "type"
$index = 0; //used for series index in highcharts
$output = $drilloutput = $pieoutput = $drillpie = $sqldata = $releasedata = $teamdata = [];
//Dropdowns
$selstatus = $selteam = $selrel = $selpri = [];

/**************************************
* Create databases and                *
* open connections                    *
**************************************/

$servername = "localhost";
$username = "root";
$password = "jabra2020";
$dbname = $jamaid;

// Create connection
$file_db = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($file_db->connect_error) {
    die("Connection failed: " . $file_db->connect_error);
} 

if (isset($_GET["plot"])) {
	if ($_GET["plot"] == "frame") {
		require 'frame.php';
		exit();
	}
}
if ($type == "testplan" or $type == "testmaturity") {
	require 'test.php';
	exit();
}

if (isset($_POST["refresh"])) {
	$result = exec("$pypath/python.exe $path/Refresh.py $jamaid $type");
	echo $result;
	exit();
}

if ($type == "designspec" || $type== "testapproval" || $type == "changes" || $type == "features" || $type == "requirements" || $type == "defects" || $type == "userstories") {
	$order = array("Rejected" => 1, "Approved" => 2, "Previously Approved" => 3, "Textual Change" => 4, "Proposal" => 5, "Info Pending" => 6, "Ready For Estimation" => 7, "Draft" => 8, "Closed" => 9, "Completed" => 10, "Resolved" => 11, "In Testing" => 12, "In Progress" => 13, "Reopened" => 14, "Open" => 15, "Pending Platform (Only In Project)" => 16, "On-Hold" => 17, "Approved For Implementation (Pl)" => 18, "Committed" => 19, "Committed In Pi" => 20, "Committed In Pi (Pl)" => 21, "Submitted (Pl)" => 22, "Stretch In Pi (Pl)" => 23, "Approved For Implementation" => 24, "Proposal (Pl)" => 25, "In Review" => 26, "Completed (Pl)" => 27);
		
	//Used for defining type name
	if ($type == "userstories") {
		$typename = "User stories";
	} elseif ($type == "designspec") {
		$typename = "Design Specifications";
	} elseif ($type == "testapproval") {
		$typename = "Test Case Approval";
	} elseif ($type == "changes") {
		$typename = "Change Requests";
	} elseif ($type == "defects") {
		$typename = "bug Status";
	} else {
		$typename = ucwords($type);
	}

	function buildSelect($type) {
		global $select;
		if (strlen($select) == 0) {
			$select = "$type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
		} else {
			$select .= "AND $type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
		}
	}
	
	if (isset($_GET["team"]) OR isset($_GET["rel"]) OR isset($_GET["priority"]) OR isset($_GET["status"])) {
		if (isset($_GET["status"])) {
			buildSelect("status");
		}
		if (isset($_GET["team"])) {
			buildSelect("team");
		}
		if (isset($_GET["rel"])) {
			buildSelect("rel");
		}
		if (isset($_GET["priority"])) {
			buildSelect("priority");
		}
		$results = $file_db->query("SELECT * FROM $type WHERE $select ORDER BY status ASC, date ASC");
		if (empty($results)) {
			echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
			exit();
		}
		$temp = $file_db->query("SELECT MAX(date) as date from $type WHERE $select")->fetch_assoc()["date"];
		$temp = $file_db->query("SELECT MAX(date) as date from $type WHERE $select")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
		$piedata = $file_db->query("SELECT team, count(*) as count from alldefects WHERE NOT status ='Closed' AND $select GROUP BY team ORDER BY count DESC");
		$piedrilldata = $file_db->query("SELECT status, team, count(*) as count from alldefects WHERE NOT status='Closed' AND $select GROUP BY team, status ORDER BY count DESC");
	} else {
		$results = $file_db->query("SELECT * FROM $type ORDER BY status ASC, date ASC");
		$temp = $file_db->query("SELECT MAX(date) as date from $type")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
		$piedata = $file_db->query("SELECT team, count(*) as count from alldefects WHERE NOT status ='Closed' GROUP BY team ORDER BY count DESC");
		$piedrilldata = $file_db->query("SELECT status, team, count(*) as count from alldefects WHERE NOT status='Closed' GROUP BY team, status ORDER BY count DESC");
	}
	
	foreach ($results as $row) {
		$status = ucwords($row["status"]);
		//Status data
		if (!isset($sqldata[$status][$row["date"]])) {
			$sqldata[$status][$row["date"]] = $row["count"];
		} else {
			$sqldata[$status][$row["date"]] += $row["count"];
		}
		//Release drilldown data
		if (!isset($releasedata[$status][$row["rel"]][$row["date"]])) {
			$releasedata[$status][$row["rel"]][$row["date"]] = $row["count"];
		} else {
			$releasedata[$status][$row["rel"]][$row["date"]] += $row["count"];
		}
		//Team drilldown data
		if ($type == "designspec" or $type == "defects" or $type == "testapproval") {
			if (!isset($teamdata[$status][$row["rel"]][$row["team"]][$row["date"]])) {
				$teamdata[$status][$row["rel"]][$row["team"]][$row["date"]] = $row["count"];
			} else {
				$teamdata[$status][$row["rel"]][$row["team"]][$row["date"]] += $row["count"];
			}
		}
	}
	
	//Pie chart
	if ($type == "defects") {
		foreach ($piedata as $row) {
			$pieoutput[] = "{'name': '".$row["team"]."', 'y': ".$row["count"].", 'drilldown': '".$row["team"]."'}";
		}
		$tempdrill = [];
		foreach ($piedrilldata as $row) {
			$tempdrill[$row["team"]][$row["status"]] = $row["count"];
		}
		foreach ($tempdrill as $tm => $team) {
			$pdata = [];
			foreach ($team as $st => $count) {
				$pdata[] = "['$st', $count]";
			}
			$drillpie[] = "{tooltip: {pointFormat: '<b>{point.name}</b>: {point.percentage:.1f} ({point.y}) %'},'name': '$tm', 'id': '$tm', 'data': [".implode( ', ', $pdata )."]}";
		}	
	}
	
	//Add undefined indexes to $order array
	$lastOrder = end($order);
	foreach (array_keys($sqldata) as $status) {
		if (!array_key_exists($status,$order)) {
			$lastOrder = $lastOrder + 1;
			$order += [ $status => $lastOrder ];
		}
	}
	
	//Sort the status array after the given key in $order[]
	uksort($sqldata, function($a, $b) use($order) {
			return $order[$a] > $order[$b];
	});
	
	
	//Sort the release array by most items in each key
	array_multisort(array_values($releasedata), SORT_ASC, array_keys($releasedata), SORT_ASC, $releasedata);
	//Main Plot
	foreach ($sqldata as $st => $status) {
		$pdata = [];
		$color = getColorJama($st);
		foreach ($status as $date => $count) {
			$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."'}";
		}
		$index += 1;
		$output[] = "{name:'$st', color: '$color', data: [".implode( ', ', $pdata )."], index: $index}";
	}
	//Drilldown plot
	foreach ($releasedata as $st => $status) {
		foreach ($status as $rl => $rel) {
			$pdata = [];
			foreach ($rel as $date => $count) {
				if ($type == "designspec" or $type == "defects" or $type == "testapproval") {
					$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."$rl'}";
				} else {
					$pdata[] = "{x: moment('$date').valueOf(), y: $count}";
				}
			}
			$color = getColorJama($rl);
			$drilloutput[] = "{type: 'area', id: '".str_replace(" ", "", $st)."', name: '$rl', color: '$color', data: [".implode( ', ', $pdata )."]}";
		}
	}
	//Drilldown team
	if ($type == "designspec" or $type == "defects" or $type == "testapproval") {
		//Dropdowns
		$selstatus = $selteam = $selrel = $selpri = [];
		if ($type == "defects") { 
			$temp = $file_db->query("SELECT DISTINCT status, rel, team, priority FROM $type GROUP BY status, rel, team, priority ORDER BY status, rel, team, priority");
		} else {
			$temp = $file_db->query("SELECT DISTINCT status, rel, team FROM $type GROUP BY status, rel, team ORDER BY status, rel, team");
		}
		foreach ($temp as $row) {
			if (!in_array(ucwords($row["status"]), $selstatus)) {
				array_push($selstatus, ucwords($row["status"]));
			}
			if (!in_array($row["team"], $selteam)) {
				array_push($selteam, $row["team"]);
			}
			if (!in_array($row["rel"], $selrel)) {
				array_push($selrel, $row["rel"]);
			}
			if ($type == "defects") {
				if (!in_array($row["priority"], $selpri)) {
					array_push($selpri, $row["priority"]);
				}
			}
		}
		//Sort the team array
		array_multisort(array_values($teamdata), SORT_ASC, array_keys($teamdata), SORT_ASC, $teamdata);
		//Team plot
		foreach ($teamdata as $st => $status) {
			foreach ($status as $rl => $rel) {
				foreach ($rel as $tm => $team) {
					$pdata = [];
					foreach ($team as $date => $count) {
						$pdata[] = "{x: moment('$date').valueOf(), y: $count}";
					}
					$color = getColorJama($tm);
					$drilloutput[] = "{type: 'area', id: '$st$rl', name: '$tm', color: '$color', data: [".implode( ', ', $pdata )."]}";
				}
			}
		}
	}
	if (empty($output)) {
		echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
		exit();
	}
} elseif ($type == "coverage") {
	$typename = "Test Coverage of Requirements";
	
	$selstatus = $selteam = $selrel = $selpri = [];
	$temp = $file_db->query("SELECT DISTINCT status, rel FROM $type GROUP BY status, rel ORDER BY status, rel");
	
	function buildSelect($type) {
		global $select;
		if (strlen($select) == 0) {
			$select = "$type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
		} else {
			$select .= "AND $type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
		}
	}
	
	if (isset($_GET["rel"]) OR isset($_GET["status"])) {
		if (isset($_GET["status"])) {
			buildSelect("status");
		}
		if (isset($_GET["rel"])) {
			buildSelect("rel");
		}
		$results = $file_db->query("SELECT * FROM $type WHERE $select ORDER BY status ASC, date ASC");
		
		if (empty($results)) {
			echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
			exit();
		}
		$temp = $file_db->query("SELECT MAX(date) as date from $type WHERE $select")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
	} else {
		$results = $file_db->query("SELECT team, sum(covered) as covered, sum(expected) as expected, date FROM coverage group by date, team ORDER by date ASC");
		$temp = $file_db->query("SELECT MAX(date) as date from $type")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
	}
	
	foreach ($results as $row) {
		//Main data
		$sqldata[$row["team"]][$row["date"]] = ["covered" => $row["covered"], "expected" => $row["expected"]];
	}
	
	//Get the current coverage
	$expected = $file_db->query("SELECT sum(expected) as sum FROM coverage GROUP BY date ORDER by date DESC LIMIT 1")->fetch_assoc()["sum"];
	$covered = $file_db->query("SELECT sum(covered) as sum FROM coverage GROUP BY date ORDER by date DESC LIMIT 1")->fetch_assoc()["sum"];
	$currentcov = $covered . "/" . $expected;
	
	array_multisort(array_keys($sqldata), SORT_ASC, array_values($sqldata), SORT_ASC, $sqldata);
	
	foreach ($sqldata as $tm => $team) {
		$pdata = [];
		foreach ($team as $dt => $date) {
			if ($date["expected"] != 0) {
				$y = $date["covered"] / $date["expected"] * 100;
			} else {
				$y = 0;
			}
			if ($y > 100) {
				$y = 100;
			}
			$pdata[] = "{x: moment('$dt').valueOf(), actual: " .$date["covered"]. ", expected: " .$date["expected"]. ", y: $y}";
		}
		$color = getColor();
		$output[] = "{ name: '$tm', data: [".implode( ', ', $pdata )."], color: '$color'}";
	}
	$temp = $file_db->query("SELECT MAX(date) as date from coverage")->fetch_assoc()["date"];
	$lastupdated = date('j. F, Y', strtotime($temp));
	
	if (empty($output)) {
		echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
		exit();
	}
	
	//Get all available release and status
	$temp = $file_db->query("SELECT DISTINCT rel, status FROM coverage");
	if ($temp != null) {
		foreach ($temp as $row) {
		if (!in_array(ucwords($row["rel"]), $selrel)) {
			array_push($selrel, ucwords($row["rel"]));
		}
		if (!in_array(ucwords($row["status"]), $selstatus)) {
			array_push($selstatus, ucwords($row["status"]));
		}
	}
	}
	
} else {
	include('404.html');
	exit();
}

$isSubtitle = false;
$isSubtitlePie = false;

$checkSubtTable = $file_db->query('SELECT type FROM subtitles');
if ($checkSubtTable != null) {
	$typeVariants = array();
	foreach($checkSubtTable as $row)
	{
		array_push($typeVariants, $row["type"]);
	}
	if (in_array("defects", $typeVariants)) {
		$subtChecker = $file_db->query('SELECT subtitle FROM subtitles WHERE type="defects"');
		$subtArr = array();
		foreach($subtChecker as $row){
			array_push($subtArr, $row["subtitle"]);
		}
		$subtitleVal = current($subtArr);
		if ($subtArr != null) {
			$isSubtitle = true;
		}
	}
	if (in_array("defectsPie", $typeVariants)) {
		$subtCheckerPie = $file_db->query('SELECT subtitle FROM subtitles WHERE type="defectsPie"');
		$subtArrPie = array();
		foreach($subtCheckerPie as $row){
			array_push($subtArrPie, $row["subtitle"]);
		}
		$subtitleValPie = current($subtArrPie);
		if ($subtArrPie != null) {
			$isSubtitlePie = true;
		}
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	if (isset($_POST['subtitle'])) {
        $subtitle = $_POST["subtitle"];

		if ($subtitle == "") {
			echo "<script type='text/javascript'>alert('You must add a subtitle');history.go(-1);</script>";
		} else {
			if ($isSubtitle) {
				$query = "UPDATE subtitles SET subtitle=? WHERE type='defects'";
				$stmt = $file_db->prepare($query);
				$stmt->bind_param('s', $subtitle);
				$stmt->execute();
			} else {
				$file_db->query("CREATE TABLE IF NOT EXISTS subtitles (type TEXT, subtitle TEXT)");
				$query = "INSERT INTO subtitles (type, subtitle) VALUES('$type','$subtitle')";
				$stmt = $file_db->prepare($query);
				$stmt->execute();
			}
			header("Refresh:0");
		}
    } 
	else {
		$subtitlePie = $_POST["subtitlePie"];
		
		if ($subtitlePie == "") {
			echo "<script type='text/javascript'>alert('You must add a subtitle');history.go(-1);</script>";
		} else {
			if ($isSubtitlePie) {
				$query = "UPDATE subtitles SET subtitle=? WHERE type='defectsPie'";
				$stmt = $file_db->prepare($query);
				$stmt->bind_param('s', $subtitlePie);
				$stmt->execute();
				
			} else {
				$pieType = "defectsPie";
				$file_db->query("CREATE TABLE IF NOT EXISTS subtitles (type TEXT, subtitle TEXT)");
				$query = "INSERT INTO subtitles (type, subtitle) VALUES('$pieType','$subtitlePie')";
				$stmt = $file_db->prepare($query);
				$stmt->execute();
			}
			header("Refresh:0");
		}
	}
}

// Close file db connection
$file_db = null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

    <title><?php echo $projectname . " - " . $typename; ?></title>

    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />
    <!-- Favicon-->
    <link rel="icon" href="/css/favicon.ico" type="image/x-icon">
    <!--     Fonts and icons     -->
    <link href="/css/font-awesome.min.css" rel="stylesheet">
    <link href='/css/fonts/material-icons.css' rel='stylesheet' type='text/css'>
    <!-- Bootstrap core CSS     -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <!-- Plugins -->
    <link href="/css/waves.min.css" rel="stylesheet" />
	<link href="/css/bootstrap-select.min.css" rel="stylesheet">
	<link href="/css/sweetalert.css" rel="stylesheet">
    <!-- Custom Css -->
    <link href="/css/style.css" rel="stylesheet">
    <!-- AdminBSB Themes. -->
    <link href="/css/themes/theme-blue.min.css" rel="stylesheet" />
    <!--   Core JS Files   -->
    <script src="/js/jquery.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/material.min.js"></script>
	<!-- Plugins -->
    <script src="/js/bootstrap-select.min.js"></script>
    <script src="/js/jquery.slimscroll.min.js"></script>
    <script src="/js/waves.min.js"></script>
	<script src="/js/sweetalert.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
	<style>
	.dropdown-menu{
	max-height: 400px;
	overflow-y: auto;
	}
	.panel.panel-primary{
	max-height: 60vh;
	overflow-y: auto;
	}
	tfoot {
    display: table-header-group;
	}
	.dataTables_filter {
	  display: none;
	}
	<?php if ($type == "defects") : ?>
	.refAndApply {
		padding-top: 150px;
	}
	.subtt {
		padding-top: 25px;
	}
	<?php endif ; ?>
	</style>
	<!-- Highcharts Js -->
    <script src="/js/highcharts.js"></script>
    <script src="/js/highcharts-more.js"></script>
    <script src="/js/exporting.js"></script>
    <script src="/js/drilldown.js"></script>
    <script src="/js/moment.min.js"></script>

    <!-- Datatables -->
	<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs/jszip-2.5.0/dt-1.10.16/b-1.5.1/b-colvis-1.5.1/b-flash-1.5.1/b-html5-1.5.1/b-print-1.5.1/cr-1.4.1/r-2.2.1/datatables.min.css"/>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/pdfmake.min.js"></script>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.32/vfs_fonts.js"></script>
	<script type="text/javascript" src="https://cdn.datatables.net/v/bs/jszip-2.5.0/dt-1.10.16/b-1.5.1/b-colvis-1.5.1/b-flash-1.5.1/b-html5-1.5.1/b-print-1.5.1/cr-1.4.1/r-2.2.1/datatables.min.js"></script>
</head>
<body class="theme-blue">
    <!-- Page Loader -->
    <div class="page-loader-wrapper">
        <div class="loader">
            <div class="preloader">
                <div class="spinner-layer pl-yellow">
                    <div class="circle-clipper left">
                        <div class="circle"></div>
                    </div>
                    <div class="circle-clipper right">
                        <div class="circle"></div>
                    </div>
                </div>
            </div>
            <p>Please wait...</p>
        </div>
    </div>
    <!-- #END# Page Loader -->
    <!-- Overlay For Sidebars -->
    <div class="overlay"></div>
    <!-- #END# Overlay For Sidebars -->
    <!-- Top Bar -->
<?php include('navbar.php'); ?>
    <!-- #Top Bar -->
	<!-- Left Sidebar -->
<?php include('sidebar.php'); ?>
	<!-- #END# Left Sidebar -->
	<section class="content">
		<div class="container-fluid">
			<div class="row clearfix">
				<!-- Plot -->
				<div class="col-sm-14 col-lg-14">
					<div class="card">
						<div class="body">
							<!-- Nav tabs -->
							<ul class="nav nav-tabs tab-nav-right" role="tablist">
                                <li class="active"><a href="#trend" data-toggle="tab"><i class="material-icons">ic_trending_up</i> TREND</a></li>
								<?php if ($type == "defects") : ?>
                                <li><a href="#pie" data-toggle="tab"><i class="material-icons">ic_pie_chart</i> PIE CHART</a></li>
								<?php endif; ?>
                                <li><a href="#table" data-toggle="tab"><i class="material-icons">ic_list</i> TABLE</a></li>
							</ul>
							<!-- Tab panes -->
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane fade in active chart" id="trend">
								<?php if ($type == "coverage") : ?>
									<div id="container" style="width:100%; height: 600px; margin: 0 auto"></div>
									<center>
										<button id="uncheckAll" type="button" class="btn btn-default waves-effect"> Hide all series </button>
										<button id="checkAll" type="button" class="btn btn-default waves-effect"> Show all series </button>
									</center>
									<div class="row clearfix">
											<div class="col-md-4">
												<p><b>Status</b></p>
												<select name="status" class="form-control show-tick" multiple id="status">
												<?php $firstElement = true; ?>
													<?php foreach ($selstatus as $status) {
														if($firstElement) {
															$firstElement = false;
															} else {
														echo "
														<option value='$status'>$status</option>";
														}
													}?>
												</select>
											</div>
											<div class="col-md-4">
												<p><b>Release</b></p>
												<select name="rel" class="form-control show-tick" multiple id="rel">
												<?php $firstElement = true; ?>
													<?php foreach ($selrel as $rel) { 
														if($firstElement) {
															$firstElement = false;
															} else {
														echo "
														<option value='$rel'>$rel</option>";
														}
													}?>
												</select>
											</div>
											<br>
											<center>
											<br>
											<button id="submit-request" type="submit" class="btn btn-primary">
												<i class="material-icons">check</i>
												<span>APPLY FILTER(s)</span>
											</button>
											</center>
									</div>
									<script>
											$('body').on('click','#submit-request',function(){
												var status = "", rel = "";
												link = window.location.href.split('?')[0]+"?";
												opt = true;
												$('select[name="status"] option:selected').each(function(){
													status += encodeURIComponent($(this).attr('value'))+",";
												});
												status = status.replace(/,\s*$/, "");
												$('select[name="rel"] option:selected').each(function(){
													rel += encodeURIComponent($(this).attr('value'))+",";
												});
												rel = rel.replace(/,\s*$/, "");
												if (rel.length >= 1) {
													if (opt) {
														link += "rel="+rel;
														opt = false;
													} else {
														link += "&rel="+rel;
													}
												}
												if (status.length >= 1) {
													if (opt) {
														link += "status="+status;
														opt = false;
													} else {
														link += "&status="+status;
													}
												}
												window.location.href = link;
											});
										</script>
										<?php if(isset($_GET['status'])) : ?>
										<script>
										<?php echo "var status = '" . $_GET['status'] . "';"; ?>
										var status = status.split(','); 
										var select = document.getElementById('status');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( status.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
										<?php if(isset($_GET['rel'])) : ?>
										<script>
										<?php echo "var rel = '" . $_GET['rel'] . "';"; ?>
										var rel = rel.split(','); 
										var select = document.getElementById('rel');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( rel.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
									
								<?php else : ?>
									<div id="container" style="width:100%; height: 600px; margin: 0 auto"></div>
									<?php if($type == "defects" or $type == "designspec" or $type == "testapproval") : ?>
										<script>
											$('body').on('click','#submit-request',function(){
												var status = "", team = "", rel = "", priority = "";
												link = window.location.href.split('?')[0]+"?";
												opt = true;
												<?php if ($type == "defects" or $type == "designspec") : ?>
												$('select[name="status"] option:selected').each(function(){
													status += encodeURIComponent($(this).attr('value'))+",";
												});
												status = status.replace(/,\s*$/, "");
												<?php endif ;?>
												$('select[name="team"] option:selected').each(function(){
													team += encodeURIComponent($(this).attr('value'))+",";
												});
												team = team.replace(/,\s*$/, "");
												$('select[name="rel"] option:selected').each(function(){
													rel += encodeURIComponent($(this).attr('value'))+",";
												});
												rel = rel.replace(/,\s*$/, "");
												<?php if ($type == "defects") : ?>
												$('select[name="priority"] option:selected').each(function(){
													priority += encodeURIComponent($(this).attr('value'))+",";
												});
												priority = priority.replace(/,\s*$/, "");
												<?php endif ;?>
												if (rel.length >= 1) {
													if (opt) {
														link += "rel="+rel;
														opt = false;
													} else {
														link += "&rel="+rel;
													}
												}
												if (status.length >= 1) {
													if (opt) {
														link += "status="+status;
														opt = false;
													} else {
														link += "&status="+status;
													}
												}
												if (team.length >= 1) {
													if (opt) {
														link += "team="+team;
														opt = false;
													} else {
														link += "&team="+team;
													}
												}
												<?php if ($type == "defects") : ?>
												if (priority.length >= 1) {
													if (opt) {
														link += "priority="+priority;
														opt = false;
													} else {
														link += "&priority="+priority;
													}
												}
												<?php endif ;?>
												window.location.href = link;
											});
										</script>
										<div class="row clearfix">
											<?php if ($type == "defects" or $type == "designspec") : ?>
											<div class="col-md-4">
												<p><b>Status</b></p>
												<select name="status" class="form-control show-tick" multiple id="status">
													<?php foreach ($selstatus as $status) {
														echo "
														<option value='$status'>$status</option>";
													}?>
												</select>
											</div>
											<?php endif ;?>
											<div class="col-md-4">
												<p><b>Team</b></p>
												<select name="team" class="form-control show-tick" multiple id="team">
													<?php foreach ($selteam as $team) { 
														echo "
															<option value='$team'>$team</option>";
													}?>
												</select>
											</div>
											<div class="col-md-4">
												<p><b>Release</b></p>
												<select name="rel" class="form-control show-tick" multiple id="rel">
													<?php foreach ($selrel as $rel) { 
														echo "
														<option value='$rel'>$rel</option>";
													}?>
												</select>
											</div>
											<?php if ($type == "defects") : ?>
											<div class="col-md-4">
												<p><b>Priority</b></p>
												<select name="priority" class="form-control show-tick" multiple id="priority">
													<?php foreach ($selpri as $priority) { 
														if (strlen($priority) > 1) {
															echo "
															<option value='$priority'>$priority</option>";
														}
													}?>
												</select>
											</div>
											<?php endif ;?>
											<br>
											<div class="refAndApply">
												<center>
												<button id="submit-request" type="submit" class="btn btn-primary">
													<i class="material-icons">check</i>
													<span>APPLY FILTER(s)</span>
												</button>
												<button id="refresh" type="submit" class="btn btn-primary">
													<i class="material-icons">refresh</i>
													<span>REFRESH DATA</span>
												</button>
												</center>
											</div>
											<?php if ($type == "defects") : ?>
											<form method="POST" action="" class="subtt">										
												<div class="col-sm-4">
													<div class="form-group">
														<div class="form-line">
															<input type="text" name="subtitle" class="form-control" placeholder="Add a subtitle..">
														</div>
													</div>
												</div>
												<button type="submit" name="subButton" value="subButton" class="btn bg-blue btn-circle waves-effect waves-circle waves-float">
													<i class="material-icons">save</i>
												</button>
											</form>
											<?php endif ;?>
										</div>
										<?php if(isset($_GET['status'])) : ?>
										<script>
										<?php echo "var status = '" . $_GET['status'] . "';"; ?>
										var status = status.split(','); 
										var select = document.getElementById('status');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( status.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
										<?php if(isset($_GET['team'])) : ?>
										<script>
										<?php echo "var team = '" . $_GET['team'] . "';"; ?>
										var team = team.split(','); 
										var select = document.getElementById('team');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( team.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
										<?php if(isset($_GET['rel'])) : ?>
										<script>
										<?php echo "var rel = '" . $_GET['rel'] . "';"; ?>
										var rel = rel.split(','); 
										var select = document.getElementById('rel');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( rel.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
										<?php if(isset($_GET['priority'])) : ?>
										<script>
										<?php echo "var priority = '" . $_GET['priority'] . "';"; ?>
										var priority = priority.split(','); 
										var select = document.getElementById('priority');
                                        for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
											o = select.options[i];
											if ( priority.indexOf( o.text ) != -1 ) {
												o.selected = true;
											}
                                        }   								
										</script>
										<?php endif ; ?>
									<?php else : ?>
									<div class="row clearfix js-sweetalert">
										<center>
											<button id="refresh" type="submit" class="btn btn-primary">
												<i class="material-icons">refresh</i>
												<span>REFRESH DATA</span>
											</button>
										</center>
									</div>
									<?php endif; ?>
								<?php endif; ?>
								</div>
								<?php if ($type == "defects") : ?>
								<div role="tabpanel" class="tab-pane fade chart" id="pie">
									<div id="piechart" style="width:100%; height: 600px; margin: 0 auto"></div>
										<form method="POST" action="">										
											<div class="col-sm-4">
												<div class="form-group">
													<div class="form-line">
														<input type="text" name="subtitlePie" class="form-control" placeholder="Add a subtitle..">
													</div>
												</div>
											</div>
											<button type="submit" name="subButtonPie" value="subButtonPie" class="btn bg-blue btn-circle waves-effect waves-circle waves-float">
												<i class="material-icons">save</i>
											</button>
										</form>
								</div>
								<?php endif; ?>
								<div role="tabpanel" class="tab-pane fade" id="table">
									<div class="table-responsive">
										<table id="rawtable" class="table table-striped table-bordered wrap" width="100%">
										<?php if ($type == "coverage") : ?>
										This table shows an overview of all requirements, with the verifying teams and the team(s) missing testcase.
										<br>
										<br>
										<?php endif; ?>
											<thead>
											<tr>
												<th>Jama ID</th>
												<th>Name</th>
												<th>Status</th>
												<th>Release</th>
												<?php if ($type != "coverage") : ?>
												<?php echo ($type == "designspec" or $type == "defects" or $type == "testapproval") ? "<th>Team</th>" : ''; ?>
												<?php echo ($type == "defects") ? "<th>Jira ID</th>" : ''; ?>
												<?php echo ($type == "defects") ? "<th>Priority</th>" : ''; ?>
												<?php echo ($type == "changes") ? "<th>Priority</th><th>Requester</th>" : ''; ?>
												<?php else : ?>
												<th>Verifying team</th>
												<th>Missing testcase</th>
												<?php endif ;?>
											</tr>
											</thead>
											<tfoot>
											<tr>
												<th>Jama ID</th>
												<th>Name</th>
												<th>Status</th>
												<th>Release</th>
												<?php if ($type != "coverage") : ?>
												<?php echo ($type == "designspec" or $type == "defects" or $type == "testapproval") ? "<th>Team</th>" : ''; ?>
												<?php echo ($type == "defects") ? "<th>Jira ID</th>" : ''; ?>
												<?php echo ($type == "defects") ? "<th>Priority</th>" : ''; ?>
												<?php echo ($type == "changes") ? "<th>Priority</th><th>Requester</th>" : ''; ?>
												<?php else : ?>
												<th>Verifying team</th>
												<th>Missing testcase</th>
												<?php endif ;?>
											</tr>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<!-- #END# Plot -->
		</div>
	</section>
</body>
<script type="text/javascript">
//Highcharts license: 100051577-1nkgah
<?php if ($type == "coverage") : ?>
Highcharts.setOptions({
	global: {
		useUTC: false
	}
});
var chart = Highcharts.chart('container', {
	chart: {
		type: 'area',
		zoomType: 'x',
		resetZoomButton: {
			position: {
				x: 0,
				y: -30
			}
		}
	},
	credits: false,
	title: {
		text: 'Test Coverage of Requirements'
	},
	subtitle: {
		text: "<center><p><b>This plot represents the total coverage of all requirments. It's calculated by going through each requirement and check the verifying team(s). The verifying team is then 'expected' to create at least 1 test case under this requirement. Once the verifying team has created a test case, it's then counted as 'covered'. The current numbers in the plot are the following: covered/expected. The total coverage value is the sum of expected test cases for all requirements.</b> <br>Current coverage: <?php echo $currentcov . "<br>Last updated: ". $lastupdated; ?></p></center>"
	},
	tooltip: {
		shared: true,
		pointFormat: '{series.name}: <b>{point.actual} / {point.expected}</b><br/>',
		xDateFormat: '%A, %b %e, %Y'
	},
	xAxis: {
		type: 'datetime',
	},
	yAxis: {
		allowDecimals: false,
		title: {
			text: 'Test cases'
		},
		labels: {
			formatter: function() {
				return this.value+"%";
			}
		},
		min: 0,
		max: 100
	},
	plotOptions: {
		series: {
			marker: {
				enabled: false
			}
		}
	},
	series: [<?php echo implode(', ', $output); ?>],
	exporting: {
		width: 800
	}
});
$('#uncheckAll').click(function(){
	var series = chart.series;
	for(i=0; i < chart.series.length; i++) {
		series[i].setVisible(false, false);
	}
	chart.redraw();
});
$('#checkAll').click(function(){
	var series = chart.series;
	for(i=0; i < chart.series.length; i++) {
		series[i].setVisible(true, true);
	}
	chart.redraw();
});
<?php else : ?>
Highcharts.Tick.prototype.drillable = function() {};
Highcharts.setOptions({
	global: {
		useUTC: false
	},
	lang: {
		drillUpText: '◁ Go back'
	}
});
var allSeries = [<?php echo implode(', ', $drilloutput); ?>];
var i = 0;
var title = "";
var chart = Highcharts.chart('container', {
	chart: {
		type: 'area',
		zoomType: 'x',
		resetZoomButton: {
			position: {
				x: 0,
				y: -30
			}
		},
		events: {
			drilldown: function(e) {
				var chart = this,
					point = e.point,
					drillId = point.drilldown;
				i++;
				allSeries.forEach(function(ser) {
					if (ser.id === drillId) {
						chart.addSingleSeriesAsDrilldown(point, ser);
					}
				});
				if (i == 1) {
					chart.setTitle({ text: "<?php echo $typename; ?>: "+point.series.name+" Trend" }),
					title = point.series.name;
				} else if (i >= 2) {
					chart.setTitle({ text: ""+point.series.name+": "+title+" <?php echo $typename; ?> Trend"});
				}
				chart.applyDrilldown();
			},
			drillup: function(e) {
				i--;
				if (i == 1) {
					chart.setTitle({ text: "<?php echo $typename ?>: "+title+"" });
				} else {
					chart.setTitle({ text: "<?php echo $typename; ?> Trend" }),
					i = 0;
				}
			}
		}
	},
	credits: false,
	title: {
	<?php if ($type == "defects") : ?>
		text: "<?php echo $projectname; ?> bug Status"
	},
	<?php else :?>
		text: "<?php echo $typename; ?> Trend"
	},
	<?php endif ?>
	subtitle: {
<?php if ($type == "defects") : ?>
	<?php if ($isSubtitle) :?>
        text: 'Last updated: <?php echo $lastupdated; ?><?php echo '<br><b>'; echo ucfirst($subtitleVal);?>',
	<?php else :?>
		text: 'Last updated: <?php echo $lastupdated; ?>',
	<?php endif ?>
			style: {
				fontSize: '13.4'
			}
		},
<?php else :?>
		text: 'Last updated: <?php echo $lastupdated; ?>'
	},
<?php endif; ?>
	xAxis: {
		type: 'datetime',
		tickmarkPlacement: 'on',
		title: {
			enabled: false
		}
	},
	yAxis: {
		allowDecimals: false,
		reversedStacks: false,
		title: {
		<?php if ($type == "testapproval") : ?>
			text: "Test cases"
		<?php else : ?>
			text: "<?php echo $typename; ?>"
		<?php endif; ?>
		}
	},
	drilldown: {
		activeAxisLabelStyle: {
			color: '#666666',
			fontWeight: 'regular',
			textDecoration: 'none'
		},
		drillUpButton : {
			position: {
				x: 0,
				y: -30
			}
		}
	},
	tooltip: {
		shared: false,
		split: true,
		xDateFormat: '%A, %b %e, %Y'
	},
	plotOptions: {
		area: {
			stacking: 'normal',
			lineColor: '#666666',
			lineWidth: 1,
			marker: {
				enabled: false,
				lineWidth: 1,
				lineColor: '#666666'
			}
		}
	},
	series: [<?php echo implode(', ', $output); ?>],
	exporting: {
		width: 800
	}
});
<?php endif; ?> 
<?php if ($type == "defects") : ?>
var chart = Highcharts.chart('piechart', {
    chart: {
        plotBackgroundColor: null,
        plotBorderWidth: null,
        plotShadow: false,
        type: 'pie',
        events: {
			drilldown: function(e) {
				var chart = this,
					point = e.point,
					drillId = point.drilldown;
				i++;
				if (i == 1) {
					chart.setTitle({ text: point.name });
				} else {
					chart.setTitle({ text: e.seriesOptions.name });
				}
				chart.applyDrilldown();
			},
			drillup: function(e) {
				var chart = this;
				i--;
				chart.setTitle({ text: e.seriesOptions.name });
			}
        }     
    },
	credits: false,
    title: {
        text: 'Defects pr. team'
    },
<?php if ($isSubtitlePie) :?>
	subtitle: {
        text: '<?php echo '<b>'; echo ucfirst($subtitleValPie);?>',
			style: {
				fontSize: '13.4'
			}
		},
<?php endif ?>
    tooltip: {
		formatter : function() {
			return "<b>"+ this.point.name+"</b>: "+this.y+" ("+this.percentage.toFixed(1)+")%"
		}
    },
    plotOptions: {
        pie: {
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b>: {point.y} ({point.percentage:.1f}%)',
                style: {
                    color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                }
            }
        }
    },
	drilldown: {
		activeAxisLabelStyle: {
			color: '#666666',
			fontWeight: 'regular',
			textDecoration: 'none'
		}
	},
    series: [{
        name: 'Total defects',
        colorByPoint: true,
        data: [<?php echo implode(', ', $pieoutput); ?>]
    }],
	drilldown: {
		series: [<?php echo implode(', ', $drillpie); ?>],
        drillUpButton: {
            relativeTo: 'spacingBox',
            position: {
                y: 20,
                x: -100
            },
            theme: {
                fill: 'white',
                'stroke-width': 1,
                stroke: 'silver',
                r: 0,
                states: {
                    hover: {
                        fill: '#a4edba'
                    },
                    select: {
                        stroke: '#039',
                        fill: '#a4edba'
                    }
                }
            }

        },
	}
});
<?php endif; ?> 
$('#refresh').click(function() {
    swal({
        title: "Are you sure?",
        text: "By confirming, latest data will be fetched from Jama, and this could take some time",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#DD6B55",
        confirmButtonText: "Confirm",
        closeOnConfirm: false,
        showLoaderOnConfirm: true
    }, function(isConfirm) {
        if (!isConfirm) return;
        $.ajax({
            type: "POST",
            url: "#",
            data: {
                refresh: true
            },
            success: function(data) {
                if (data == "Success") {
                    swal({
                            title: "Updated!",
                            text: "[" + new Date().toLocaleString() + "] Latest data successfully fetched from Jama!",
                            type: "success"
                        },
                        function() {
                            location.reload();
                        });
                } else {
                    swal("Error!", "Can't establish connection to Jama. Please try again later", "error");
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                swal("Error!", "Can't establish connection to Jama. Please try again later", "error");
            }
        });
    });
});
$(document).ready(function() {
	// Setup - add a text input to each footer cell
	$('#rawtable tfoot th').each( function () {
		var title = $(this).text();
		$(this).html( '<input type="text" placeholder="Search in '+title+'" />' );
	});
	var table = $('#rawtable').DataTable({
		"processing": true,
		"serverSide": true,
		"deferRender": true,
		"ajax": {
			"url": "/data",
			"type": "POST",
			"data": {
				'project': '<?php echo $jamaid; ?>',
				'type': '<?php echo $type; ?>',
				<?php  
				if (isset($_GET["status"])) {
					echo "'status': '".$_GET["status"]."', ";
				}
				if (isset($_GET["team"])) {
					echo "'team': '".$_GET["team"]."', ";
				} 
				if (isset($_GET["rel"])) {
					echo "'rel': '".$_GET["rel"]."', ";
				} 
				if (isset($_GET["priority"])) {
					echo "'priority': '".$_GET["priority"]."'";
				}
				?>
			}
		},
		"columns" : [
			{'data' : 'uniqueid'},
			{'data' : 'name'},
			{'data' : 'status'},
			{'data' : 'rel'},
			<?php 
			if ($type == "designspec" or $type == "defects" or $type == "testapproval") {
				echo "{'data' : 'team'},
				";
				if ($type == "defects") {
					echo "{'data' : 'jira'},
						{'data' : 'priority'},";
				}
			}
			if ($type == "changes") {
				echo "{'data' : 'priority'},
					{'data' : 'requester'}
				";
			}
			if ($type == "coverage") {
				echo "{'data' : 'verify'},
					{'data' : 'missing'}
				";
			}
			?>
		],
		"columnDefs": [
			{
				"render": function ( data, type, row ) {
					return "<a href='<?php echo $config["jama"]."#/items/"?>"+row['id']+"<?php echo "?projectId=$jamaid" ?>' target='blank' title='Open <?php echo $type ?> in Jama'>"+row['uniqueid']+"</a>";
				},
				"targets": [0]
			},
			<?php if ($type == "features") : ?>
			{
				"render": function ( data, type, row ) {
					return "<a href='relation?id="+row['id']+"&type=feat' target='blank' title='Show relationship for this feature'>"+row['name']+"</a>";
				},
				"targets": [1]
			},
			<?php elseif ($type == "requirements") : ?>
			{
				"render": function ( data, type, row ) {
					return "<a href='relation?id="+row['id']+"&type=req' target='blank' title='Show relationship for this requirement'>"+row['name']+"</a>";
				},
				"targets": [1]
			},			
			<?php endif; ?>
			{
				"render": function ( data, type, row ) {
					return row['status'].charAt(0).toUpperCase() + row['status'].slice(1);
				},
				"targets": [2]
			},
			<?php if ($type == "defects") : ?>
			{
				"render": function ( data, type, row ) {
					if (row['jira'].length > 1) {
						return "<a href='"+row['jira']+"' target='blank' title='Open defect in Jira'>"+row['jira'].split("https://jira.jabra.com/browse/")[1];+"</a>";
					} else {
						return "";
					}
				},
				"targets": [5]
			}
			<?php endif ; ?>
			<?php if ($type == "coverage") : ?>
			{
				"render": function ( data, type, row ) {
					return row["verify"].split(',').join('<br>');
				},
				"targets": [4]
			},
			{
				"render": function ( data, type, row ) {
					return row["missing"].split(',').join('<br>');
				},
				"targets": [5]
			}
			<?php endif ; ?>
		],
		rowCallback: function(row, data, index){
			status = data['status'].charAt(0).toUpperCase() + data['status'].slice(1);
			if (status == 'Open' || status == 'Draft'){
				$(row).find('td:contains('+status+')').css({'background': 'red', 'color': 'white'});
			} else if (status == 'Closed' || status == 'Approved'){
				$(row).find('td:contains('+status+')').css({'background': 'green', 'color': 'white'});
			} else if (status == 'In Testing' || status == 'Completed'){
				$(row).find('td:contains('+status+')').css({'background': 'blue', 'color': 'white'});
			} else if (status == 'Resolved' || status == 'Textual Change'){
				$(row).find('td:contains('+status+')').css({'background': 'purple', 'color': 'white'});
			} else if (status == 'In Progress' || status == 'Info Pending' || status == 'Info pending' ){
				$(row).find('td:contains('+status+')').css({'background': 'orange', 'color': 'white'});
			} else if (status == 'Rejected'){
				$(row).find('td:contains('+status+')').css({'background': 'grey', 'color': 'white'});
			} else if (status == 'Proposal'){
				$(row).find('td:contains('+status+')').css({'background': 'yellow', 'color': 'black'});
			} else if (status == 'Reopened'){
				$(row).find('td:contains('+status+')').css({'background': 'pink', 'color': 'black'});
			}
		},
		conditionalPaging: true,
		responsive: true,
		dom: 'Bfrtip',
		colReorder: true,
		"iDisplayLength": 50,
		lengthMenu: [
			[ 10, 25, 50, -1 ],
			[ '10 rows', '25 rows', '50 rows', 'Show all' ]
		],
		buttons: [
			{
				extend: 'colvis',
				text: 'Show/hide fields'
			},
			'pageLength',
			'copy',
			'excel',
			'pdf',
			'print'
		]
	});
	$.fn.dataTable.ext.errMode = function(obj,param,err){
                var tableId = obj.sTableId;
                console.log('Handling DataTable issue of Table '+tableId);
        };
	// Apply the search
	table.columns().every( function () {
		var that = this;
		$( 'input', this.footer() ).on( 'keyup change', function () {
			if ( that.search() !== this.value ) {
				that
					.search( this.value )
					.draw();
			}
		});
	});
});
</script>
</html>
