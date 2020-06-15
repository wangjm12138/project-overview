<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
$path = $config["path"];
$select = "";
$sqldata = $releasedata = $teamdata = $targetbuild = $team = $type = $label = $status = $output = $drilloutput = $pieoutput = $drillpie = [];
$targetbuildlist = $teamlist = $typelist = $labellist = $statuslist = $prioritylist = $jamabug = [];

$project = strtoupper($_GET["project"]);
$winType = $_GET["windowType"];

$servername = "localhost";
$username = "root";
$password = "jabra2020";
$dbname = "projects";

// Create connection
$file_db = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($file_db->connect_error) {
    die("Connection failed: " . $file_db->connect_error);
} 

if (substr($project, 0, 2) == "20") {
	$results = $file_db->query("SELECT * from projects where jama='$project'")->fetch_assoc();
} else {
	$results = $file_db->query("SELECT * from projects where keyy='$project'")->fetch_assoc();
}

$file_db = null;
$index = 0;
require_once('getcolor.php'); //required for generating or finding the right color for the series

if ($results) {
	//Project's info
	$title = $projectname = $results["name"];
	$jamaid = $results["jama"];
	$jira = true;
}

if (file_exists("$path/db/jira/$project.db")) { //check if the project has a datebase before connecting to avoid creating a new database 
	$file_db = new PDO("sqlite:$path/db/jira/$project.db");
}

//Generate data for custom plotting
$rowid = $file_db->query("SELECT max(rowid) AS rowid FROM jirasessions")->fetch()["rowid"]; //Used for fetching latest run
$results = $file_db->query("SELECT targetbuild, team, type, labels, priority, status FROM jirastatus WHERE jirasession_id='$rowid' GROUP BY targetbuild, team, type, labels, priority, status");
//Save sql data in array
foreach ($results as $row) {
	if (!in_array($row["targetbuild"], $targetbuildlist)) {
		array_push($targetbuildlist, $row["targetbuild"]);
	}
	if (!in_array($row["team"], $teamlist)) {
		array_push($teamlist, $row["team"]);
	}
	if (!in_array($row["type"], $typelist)) {
		array_push($typelist, $row["type"]);
	}
	if (!in_array($row["labels"], $labellist)) {
		array_push($labellist, $row["labels"]);
	}
	if (!in_array($row["status"], $statuslist)) {
		array_push($statuslist, $row["status"]);
	}
	if (!in_array($row["priority"], $prioritylist)) {
		array_push($prioritylist, $row["priority"]);
	}
}

function buildSelect($type) {
	global $select;
	if (strlen($select) == 0) {
		$select .= "$type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	} else {
		$select .= "AND $type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	}
}

if (isset($_GET["targetbuild"])) {
	buildSelect("targetbuild");
}
if (isset($_GET["team"])) {
	buildSelect("team");
}
if (isset($_GET["labels"])) {
	buildSelect("labels");
}
if (isset($_GET["type"])) {
	buildSelect("type");
}
if (isset($_GET["status"])) {
	buildSelect("status");
}
if (isset($_GET["priority"])) {
	buildSelect("priority");
}
if (strlen($select) > 1) {
	$results = $file_db->query("SELECT targetbuild, type, status, labels, team, date, count FROM jirastatus WHERE $select");
	$lastupdated = $file_db->query("SELECT MAX(date) as date FROM jirastatus WHERE $select")->fetch()["date"];
} else {
	$results = $file_db->query("SELECT targetbuild, type, status, labels, team, date, count FROM jirastatus");
	$lastupdated = $file_db->query("SELECT MAX(date) as date FROM jirastatus")->fetch()["date"];
}

foreach ($results as $row) {
	$status = $row["status"] . " " . $row["type"];
	//Main data
	if (!isset($sqldata[$status][$row["date"]])) {
		$sqldata[$status][$row["date"]] = $row["count"];
	} else {
		$sqldata[$status][$row["date"]] += $row["count"];
	}
	//Targetbuild data
	if (!isset($releasedata[$row["targetbuild"]][$status][$row["date"]])) {
		$releasedata[$row["targetbuild"]][$status][$row["date"]] = $row["count"];
	} else {
		$releasedata[$row["targetbuild"]][$status][$row["date"]] += $row["count"];
	}
	//team data
	$teamdata[$status][$row["targetbuild"]][$row["team"]][$row["date"]] = $row["count"];
}

//Sort main and drilldown array depending on the value and key
array_multisort(array_values($sqldata), SORT_DESC, array_keys($sqldata), SORT_ASC, $sqldata);
array_multisort(array_values($releasedata), SORT_ASC, array_keys($releasedata), SORT_ASC, $releasedata);
array_multisort(array_values($teamdata), SORT_ASC, array_keys($teamdata), SORT_ASC, $teamdata);

//Main plot
foreach ($sqldata as $st => $status) {
	$pdata = [];
	foreach ($status as $date => $count) {
		$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."'}";
	}
	$index += 1;
	$color = getColorJama($st);
	$output[] = "{name: '$st', color:'$color', data: [".implode( ', ', $pdata )."], index: $index}";
}

//Targetbuild plot
foreach ($releasedata as $tg => $target) {
	foreach ($target as $st => $status) {
		$pdata = [];
		foreach ($status as $date => $count) {
			$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."$tg'}";
		}
		$color = getColorJama($st);
		$drilloutput[] = "{type: 'area', id: '".str_replace(" ", "", $st)."', name: '$tg', color: '$color', data: [".implode( ', ', $pdata )."]}";
	}
}

//Team plot
foreach ($teamdata as $st => $status) {
	foreach ($status as $tg => $targetbuild) {
		foreach ($targetbuild as $tm => $team) {
			$pdata = [];
			foreach ($team as $date => $count) {
				$pdata[] = "{x: moment('$date').valueOf(), y: $count}";
			}
			$color = getColor();
			$drilloutput[] = "{type: 'area', id: '".str_replace(" ", "", $st)."$tg', name: '$tm', color: '$color', data: [".implode( ', ', $pdata )."]}";
		}
	}
}

//Pie chart
if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) {
	$temp = $file_db->query("SELECT team, count(*) as count from rawjiradata WHERE status!='Closed' and type='Bug' GROUP BY team ORDER BY count DESC");
	foreach ($temp as $row) {
		$pieoutput[] = "{'name': '".$row["team"]."', 'y': ".$row["count"].", 'drilldown': '".$row["team"]."'}";
	}
	$temp = $file_db->query("SELECT status, team, count(*) as count from rawjiradata WHERE status!='Closed' and type='Bug' GROUP BY team, status ORDER BY count DESC");
	$tempdrill = [];
	foreach ($temp as $row) {
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

//Custom subtitles
$isSubtitle = false;
$isSubtitlePie = false;

$checkSubtTable = $file_db->query('SELECT type FROM subtitles');
if ($checkSubtTable != null) {
	$typeVariants = array();
	foreach($checkSubtTable as $row)
	{
		array_push($typeVariants, $row["type"]);
	}
	if (in_array("plot", $typeVariants)) {
		$subtChecker = $file_db->query('SELECT subtitle FROM subtitles WHERE type="plot"');
		$subtArr = array();
		foreach($subtChecker as $row){
			array_push($subtArr, $row["subtitle"]);
		}
		$subtitleVal = current($subtArr);
		if ($subtArr != null) {
			$isSubtitle = true;
		}
	}
	if (in_array("plotPie", $typeVariants)) {
		$subtCheckerPie = $file_db->query('SELECT subtitle FROM subtitles WHERE type="plotPie"');
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
	
	if (isset($_POST['subButtonPie'])) {
        $subtitlePie = $_POST["subtitlePie"];
		
		if ($subtitlePie == "") {
			echo "<script type='text/javascript'>alert('You must add a subtitle');history.go(-1);</script>";
		} else {
			if ($isSubtitlePie) {
				$query = "UPDATE subtitles SET subtitle=:subtitlePie WHERE type='plotPie'";
				$stmt = $file_db->prepare($query);
				$stmt->bindParam(':subtitlePie', $subtitlePie);
				$stmt->execute();
				
			} else {
				$pieType = "plotPie";
				$file_db->query("CREATE TABLE IF NOT EXISTS subtitles (type TEXT, subtitle TEXT)");
				$file_db->exec("INSERT INTO subtitles (type, subtitle) VALUES('$pieType','$subtitlePie')");
				
			}
			header("Refresh:0");
		}
    }
    else {
		$subtitle = $_POST["subtitle"];

		if ($subtitle == "") {
			echo "<script type='text/javascript'>alert('You must add a subtitle');history.go(-1);</script>";
		} else {
			if ($isSubtitle) {
				$query = "UPDATE subtitles SET subtitle=:subtitle WHERE type='plot'";
				$stmt = $file_db->prepare($query);
				$stmt->bindParam(':subtitle', $subtitle);
				$stmt->execute();
			} else {
				$file_db->query("CREATE TABLE IF NOT EXISTS subtitles (type TEXT, subtitle TEXT)");
				$file_db->exec("INSERT INTO subtitles (type, subtitle) VALUES('$winType','$subtitle')");
			}
			header("Refresh:0");
		}
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

    <title><?php echo $projectname; ?>, project details</title>
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
    <!-- Custom Css -->
    <link href="/css/style.css" rel="stylesheet">
    <!-- AdminBSB Themes. -->
    <link href="/css/themes/theme-blue.min.css" rel="stylesheet" />
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
	</style>

    <!--   Core JS Files   -->
    <script src="/js/jquery.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/material.min.js"></script>
	<!-- Plugins -->
    <script src="/js/bootstrap-select.min.js"></script>
    <script src="/js/jquery.slimscroll.min.js"></script>
    <script src="/js/waves.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
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
<script>
	$(document).ready(function() {
		// Setup - add a text input to each footer cell
		$('#rawtable tfoot th').each( function () {
			var title = $(this).text();
			$(this).html( '<input type="text" placeholder="Search in '+title+'" />' );
		});
		var table = $('#rawtable').DataTable( {
			"processing": true,
			"serverSide": true,
			"deferRender": true,
			"ajax": {
				"url": "/data",
				"type": "POST",
				"data": {
					'project': '<?php echo $project; ?>',
					'platform': 'jira',
					<?php  
					if (isset($_GET["status"])) {
						echo "'status': '".$_GET["status"]."', ";
					}
					if (isset($_GET["team"])) {
						echo "'team': '".$_GET["team"]."', ";
					} 
					if (isset($_GET["targetbuild"])) {
						echo "'targetbuild': '".$_GET["targetbuild"]."', ";
					} 
					if (isset($_GET["type"])) {
						echo "'type': '".$_GET["type"]."', ";
					} 
					if (isset($_GET["labels"])) {
						echo "'labels': '".$_GET["labels"]."'";
					} 
					if (isset($_GET["priority"])) {
						echo "'priority': '".$_GET["priority"]."'";
					}
					?>
					
				}
			},
			"columns" : [
				{'data' : 'targetbuild'},
				{'data' : 'keyy'},
				<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?>{'data' : 'jama'},<?php endif; ?>
				{'data' : 'name'},
				{'data' : 'type'},
				{'data' : 'status'},
				{'data' : 'team'},
				{'data' : 'priority'}
			],
			"columnDefs": [
				{
					"render": function ( data, type, row ) {
						return "<a href='<?php echo $config["jira"]; ?>/browse/"+row["keyy"]+"' target='blank' title='Open "+row["type"]+" in Jira'>"+row["keyy"]+"</a>";
					},
					"targets": [1]
				},
				<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?>
				{
					"render": function ( data, type, row ) {
						if (row["jama"] == "0") {
							return "";
						} else {
							return "<a href='<?php echo $config["jama"]; ?>#/items/"+row["jamaid"]+"' target='blank' title='Open "+row["type"]+" in Jira'>"+row["jama"]+"</a>";
						}
					},
					"targets": [2]
				}
				<?php endif; ?>
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
		} );
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
	} );
    </script>
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
				<div class="col-md-14">
					<div class="card">
						<div class="body">
							<!-- Nav tabs -->
							<ul class="nav nav-tabs tab-nav-right" role="tablist">
                                <li class="active"><a href="#trend" data-toggle="tab"><i class="material-icons">ic_trending_up</i> TREND</a></li>
								<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?>
                                <li><a href="#pie" data-toggle="tab"><i class="material-icons">ic_pie_chart</i> PIE CHART</a></li>
								<?php endif; ?>
                                <li><a href="#table" data-toggle="tab"><i class="material-icons">ic_list</i> TABLE</a></li>
							</ul>
							
							<!-- Tab panes -->
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane fade in active chart" id="trend">
									<div id="container" style="width:100%; height: 640px; margin: 0 auto"></div>
									<?php
									if ($index > 5) {
										echo "<center>
										<button id='uncheckAll' type='button' class='btn btn-default waves-effect'> Hide all series </button>
										<button id='checkAll' type='button' class='btn btn-default waves-effect'> Show all series </button>
									</center>";}?>
									<div class="row clearfix">
									<script>
										$('body').on('click','#submit-request',function(){
											var statuses = "", teams = "", targetbuilds = "", types = "", labels = "", priorities = "";
											$('select[name="status"] option:selected').each(function(){
												statuses += encodeURIComponent($(this).attr('value'))+",";
											});
											statuses = statuses.replace(/,\s*$/, "");
											$('select[name="team"] option:selected').each(function(){
												teams += encodeURIComponent($(this).attr('value'))+",";
											});
											teams = teams.replace(/,\s*$/, "");
											$('select[name="targetbuild"] option:selected').each(function(){
												targetbuilds += encodeURIComponent($(this).attr('value'))+",";
											});
											targetbuilds = targetbuilds.replace(/,\s*$/, "");
											$('select[name="type"] option:selected').each(function(){
												types += encodeURIComponent($(this).attr('value'))+",";
											});
											types = types.replace(/,\s*$/, "");
											$('select[name="labels"] option:selected').each(function(){
												labels += encodeURIComponent($(this).attr('value'))+",";
											});
											labels = labels.replace(/,\s*$/, "");
											$('select[name="priority"] option:selected').each(function(){
												priorities += encodeURIComponent($(this).attr('value'))+",";
											});
											priorities = priorities.replace(/,\s*$/, "");
											link = window.location.href.split('?')[0]+"?";
											opt = true;
											if (targetbuilds.length >= 1) {
												if (opt) {
													link += "targetbuild="+targetbuilds;
													opt = false;
												} else {
													link += "&targetbuild="+targetbuilds;
												}
											}
											if (labels.length >= 1) {
												if (opt) {
													link += "labels="+labels;
													opt = false;
												} else {
													link += "&labels="+labels;
												}
											}
											if (statuses.length >= 1) {
												if (opt) {
													link += "status="+statuses;
													opt = false;
												} else {
													link += "&status="+statuses;
												}
											}
											if (types.length >= 1) {
												if (opt) {
													link += "type="+types;
													opt = false;
												} else {
													link += "&type="+types;
												}
											}
											if (teams.length >= 1) {
												if (opt) {
													link += "team="+teams;
													opt = false;
												} else {
													link += "&team="+teams;
												}
											}
											if (priorities.length >= 1) {
												if (opt) {
													link += "priority="+priorities;
													opt = false;
												} else {
													link += "&priority="+priorities;
												}
											}
											window.location.href = link;
										});
									</script>
										<div class="col-md-4">
											<p><b>Status</b></p>
											<select name="status" class="form-control show-tick" multiple id="status">
												<?php foreach ($statuslist as $status) { 
												echo "
												<option value='$status'>$status</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Targetbuild</b></p>
											<select name="targetbuild" class="form-control show-tick" multiple id="targetbuild">
												<?php foreach ($targetbuildlist as $targetbuild) { 
												echo "
												<option value='$targetbuild'>$targetbuild</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Team</b></p>
											<select name="team" class="form-control show-tick" multiple id="team">
												<?php foreach ($teamlist as $team) { 
												echo "
												<option value='$team'>$team</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Label</b></p>
											<select name="labels" class="form-control show-tick" multiple id="labels">
												<?php foreach ($labellist as $label) { 
												echo "
												<option value='$label'>$label</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Type</b></p>
											<select name="type" class="form-control show-tick" multiple id="type">
												<?php foreach ($typelist as $type) { 
												echo "
												<option value='$type'>$type</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Priority</b></p>
											<select name="priority" class="form-control show-tick" multiple id="priority">
												<?php foreach ($prioritylist as $priority) { 
												echo "
												<option value='$priority'>$priority</option>";}?>
											</select>
										</div>
										<br>
										<center>
										<button id="submit-request" type="submit" class="btn btn-primary">
											<i class="material-icons">check</i>
											<span>APPLY FILTER(s)</span>
										</button>
										</center>
										<br>
										<form method="POST" action="">										
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
										if ( team.indexOf( o.text.replace("%20", "") ) != -1 ) {
											o.selected = true;
										}
									}   								
									</script>
									<?php endif ; ?>
									<?php if(isset($_GET['targetbuild'])) : ?>
									<script>
									<?php echo "var targetbuild = '" . $_GET['targetbuild'] . "';"; ?>
									var targetbuild = targetbuild.split(','); 
									var select = document.getElementById('targetbuild');
									for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
										o = select.options[i];
										if ( targetbuild.indexOf( o.text ) != -1 ) {
											o.selected = true;
										}
									}   								
									</script>
									<?php endif ; ?>
									<?php if(isset($_GET['type'])) : ?>
									<script>
									<?php echo "var type = '" . $_GET['type'] . "';"; ?>
									var type = type.split(','); 
									var select = document.getElementById('type');
									for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
										o = select.options[i];
										if ( type.indexOf( o.text ) != -1 ) {
											o.selected = true;
										}
									}   								
									</script>
									<?php endif ; ?>
									<?php if(isset($_GET['labels'])) : ?>
									<script>
									<?php echo "var labels = '" . $_GET['labels'] . "';"; ?>
									var labels = labels.split(','); 
									var select = document.getElementById('labels');
									for ( var i = 0, l = select.options.length, o; i < l; i++ ) {
										o = select.options[i];
										if ( labels.indexOf( o.text ) != -1 ) {
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
								</div>
								<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?>
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
								<?php endif ; ?>
								<div role="tabpanel" class="tab-pane fade" id="table">
									<div class="table-responsive">
										<table id="rawtable" class="table table-striped table-bordered wrap" width="100%">
											<thead>
											<tr>
												<th>Target build</th>
												<th>Jira ID</th>
												<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?><th>Jama ID</th><?php endif; ?>
												
												<th>Name</th>
												<th>Type</th>
												<th>Status</th>
												<th>Team</th>
												<th>Priority</th>
											</tr>
											</thead>
											<tfoot>
											<tr>
												<th>Target build</th>
												<th>Jira ID</th>
												<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?><th>Jama ID</th><?php endif; ?>
												
												<th>Name</th>
												<th>Type</th>
												<th>Status</th>
												<th>Team</th>
												<th>Priority</th>
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
<script>
$(document).ready(function(){
	Highcharts.Tick.prototype.drillable = function() {};
	Highcharts.setOptions({
		global: {
			useUTC: false
		},
		lang: {
			drillUpText: '◁ Go back'
		}
	});
	var allSeries = [<?php echo implode( ', ', $drilloutput ); ?>];
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

					allSeries.forEach(function(ser) {
						if (ser.id === drillId) {
							chart.addSingleSeriesAsDrilldown(point, ser);
						}
					});
					i++;
					if (i == 1) {
						chart.setTitle({ text: "<?php echo $title ?>: "+point.series.name+" Trend" }),
						title = point.series.name;
					} else if (i >= 2) {
						chart.setTitle({ text: ""+point.series.name+": "+title+" <?php echo $title ?> Trend"});
					}
					chart.applyDrilldown();
				},
				drillup: function(e) {
					i--;
					if (i == 1) {
						chart.setTitle({ text: "<?php echo $title ?>: "+title+" Trend"});
					} else {
						chart.setTitle({ text: "<?php echo $title; ?> Trend" });
						i = 0;
					}
				}
			}
		},
		title: {
		<?php if ($winType == "plot") : ?>
			text: "<?php echo $title; ?> bug Status"
		},
		<?php else :?>
			text: "<?php echo $title; ?> Trend"
		},
		<?php endif ?>
		subtitle: {
			<?php if ($isSubtitle) :?>
        text: 'Last updated: <?php echo $lastupdated; ?><?php echo '<br><b>'; echo ucfirst($subtitleVal);?>',
			<?php else :?>
		text: 'Last updated: <?php echo $lastupdated; ?>',
			<?php endif ?>
			style: {
				fontSize: '13.4'
			}
		},
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
				text: "Issues"
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
			shared: true,
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
		series: [<?php echo implode( ', ', $output ); ?>]
	});
	<?php if ($index > 5) {
		echo "$('#uncheckAll').click(function(){
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
	});";}?>
});
<?php if (isset($_GET["type"]) and strpos($_GET["type"], "Bug") !== false) : ?>
Highcharts.chart('piechart', {
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
                x: -200
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
</script>
</html>