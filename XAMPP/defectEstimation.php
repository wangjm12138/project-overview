<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
$path = $config["path"];
$select = "";
$sqldata = $releasedata = $teamdata = $targetbuild = $team = $type = $label = $status = $output = $drilloutput = $pieoutput = $drillpie = [];
$targetbuildlist = $teamlist = $typelist = $labellist = $statuslist = $prioritylist = $jamabug = [];


$project = strtoupper($_GET["project"]);

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

function buildSelect($type) {
	global $select;
	if (strlen($select) == 0) {
		$select .= "$type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	} else { 
		$select .= "AND $type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	}
}

$resultO = $file_db->query("SELECT date, sum(count) as count FROM jirastatus WHERE type= 'Bug' AND status NOT IN ('Closed', 'Done') AND date BETWEEN datetime('now', '-3 month') AND datetime('now', 'localtime') group by date");

$resultC = $file_db->query("SELECT sum(count) as count FROM jirastatus WHERE type= 'Bug' AND status IN ('Closed', 'Done') AND date BETWEEN datetime('now', '-3 month') AND datetime('now', 'localtime') group by date ORDER BY date");

if (isset($_GET["targetbuild"]) OR isset($_GET["priority"])) {
		if (isset($_GET["targetbuild"])) {
			buildSelect("targetbuild");
		}
		if (isset($_GET["priority"])) {
			buildSelect("priority");
		}
		$resultO = $file_db->query("SELECT date, sum(count) as count FROM jirastatus WHERE $select AND type= 'Bug' AND status NOT IN ('Closed', 'Done') AND date BETWEEN datetime('now', '-3 month') AND datetime('now', 'localtime') group by date");
		$resultC = $file_db->query("SELECT sum(count) as count FROM jirastatus WHERE $select AND type= 'Bug' AND status IN ('Closed', 'Done') AND date BETWEEN datetime('now', '-3 month') AND datetime('now', 'localtime') group by date ORDER BY date");
	}
	
$openBugArr = array();
$openBugDate = array();
foreach($resultO as $row)
    {
        array_push($openBugArr, $row["count"]);
		array_push($openBugDate, $row["date"]);
    }
if (empty($openBugArr)) {
			echo "<script type='text/javascript'>alert('Open bugs data not found');history.go(-1);</script>";
			exit();
}
if (empty($openBugDate)) {
			echo "<script type='text/javascript'>alert('No date data found');history.go(-1);</script>";
			exit();
}

$closedBugArr = array();
foreach($resultC as $row)
    {
        array_push($closedBugArr, $row["count"]);
    }
if (empty($closedBugArr)) {
			echo "<script type='text/javascript'>alert('Closed bugs data not found');history.go(-1);</script>";
			exit();
}

$daysCount = 2;

if (isset($_GET["days"])) {
	$daysCount = $_GET["days"];
}

function calculate($days, $openBugArr, $closedBugArr, $openBugDate, $file_db) {
	
	$openBugFirstLast = array_slice($openBugArr, -$days, $days, true);
	$closedBugFirstLast = array_slice($closedBugArr, -$days, $days, true);
	$datoBug = array_slice($openBugDate, -$days, $days, true);
	
	$newBugsFirst = current($datoBug);
	$newBugsLast = end($datoBug);

	$newBugsInWindow = $file_db->query("SELECT count(type) AS count from rawjiradata where type = 'Bug' AND createddate between '$newBugsFirst' AND '$newBugsLast'");

	$newBugsCount = array();
	foreach($newBugsInWindow as $row)
		{
			array_push($newBugsCount, $row["count"]);
		}
	if (empty($newBugsCount)) {
				echo "<script type='text/javascript'>alert('New bugs data not found');history.go(-1);</script>";
				exit();
	}

	$openFirst = current($openBugFirstLast);
	$openLast = end($openBugFirstLast);

	$closedFirst = current($closedBugFirstLast);
	$closedLast = end($closedBugFirstLast);
	
	$closedBugs = $closedLast - $closedFirst;
	$newBugs = current($newBugsCount);
	
	if ($openLast > 0 && $closedBugs > 0 && $openFirst > $openLast && $closedBugs > $newBugs) {
		$slobe = ($closedBugs - $newBugs) / $days;
		
		$endPoint = $openLast / $slobe;
		
		if ($endPoint > 0) {
			return $endPoint;	
		}
	} else {
		return false;
	}
}

$endPoint = calculate($daysCount, $openBugArr, $closedBugArr, $openBugDate, $file_db);

while ($endPoint == false ) {
	$daysCount = $daysCount + 1;
	$endPoint = calculate($daysCount, $openBugArr, $closedBugArr, $openBugDate, $file_db);
	
	if ($daysCount > (count($openBugArr) + 1)) {
		break;
	}
}

$regressionX = array_slice($openBugArr, -$daysCount, true);
	
if ($endPoint == false) {
	$regressionstart = 0;
} else {
	$regressionstart = end($openBugArr);
}
$regressionslut = 0;

	$selprio = $seltar = [];
	
	//Get all available priorities and targetbuilds
	$temp = $file_db->query("SELECT DISTINCT targetbuild, priority FROM jirastatus");
	foreach ($temp as $row) {
		if (!in_array(ucwords($row["targetbuild"]), $seltar)) {
			array_push($seltar, ucwords($row["targetbuild"]));
		}
		if (!in_array(ucwords($row["priority"]), $selprio)) {
			array_push($selprio, ucwords($row["priority"]));
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
	<script src="https://code.highcharts.com/highcharts.js"></script>
	<script src="https://code.highcharts.com/modules/exporting.js"></script>

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
		<div class="container-fluid" onmousedown="return false">
			<div class="row clearfix">
				<!-- Plot -->
				<div class="col-md-14">
					<div class="card">
						<div class="body">
							<!-- Nav tabs -->
							<ul class="nav nav-tabs tab-nav-right" role="tablist">
								<li class="active"><a href="#defectEstimation" data-toggle="tab"><i class="material-icons">trending_down</i> DEFECT TREND</a></li>
							</ul>
							
							<!-- Tab panes -->
							<div class="tab-content">
							
								<div role="tabpanel" class="tab-pane fade in active chart" id="defectTrend">
									<div id="estimationContainer" style="width:100%; height: 600px; margin: 0 auto"></div>
									
									<div class="row clearfix">
									<script>
										$('body').on('click','#submit-request-estimation',function(){
											var targetbuilds = "", priorities = "", days = "", blVar = '<?php $daysCount ?>';
											
											$('select[name="targetbuild"] option:selected').each(function(){
												targetbuilds += encodeURIComponent($(this).attr('value'))+",";
											});
											targetbuilds = targetbuilds.replace(/,\s*$/, "");
											
											$('select[name="priority"] option:selected').each(function(){
												priorities += encodeURIComponent($(this).attr('value'))+",";
											});
											priorities = priorities.replace(/,\s*$/, "");
											
											$('select[name="days"] option:selected').each(function(){
												days += encodeURIComponent($(this).attr('value'))+",";
											});
											days = days.replace(/,\s*$/, "");
											
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
											if (priorities.length >= 1) {
												if (opt) {
													link += "priority="+priorities;
													opt = false;
												} else {
													link += "&priority="+priorities;
												}
											}
											if (days.length >= 1) {
												if (opt) {
													link += "days="+days;
													opt = false;
												} else {
													link += "&days="+days;
												}
											}
											
											window.location.href = link;
										});
									</script>
										<div class="col-md-4">
											<p><b>Targetbuild</b></p>
											<select name="targetbuild" class="form-control show-tick" multiple id="targetbuild">
												<?php foreach ($seltar as $targetbuild) { 
												echo "
												<option value='$targetbuild'>$targetbuild</option>";}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Priority</b></p>
											<select name="priority" class="form-control show-tick" multiple id="priority">
											<?php $firstElement = true; ?>
												<?php foreach ($selprio as $priority) {
													if($firstElement) {
														$firstElement = false;
													} else {
												echo "
													<option value='$priority'>$priority</option>";}}?>
											</select>
										</div>
										<div class="col-md-4">
											<p><b>Days</b></p>
											<select name="days" class="form-control show-tick">
													<?php echo "<option selected style='display:none' value='$daysCount'>$daysCount</option>";
														for ($days = 1; $days < (count($openBugArr) + 1); $days += 1) {
															echo "
															<option value='$days'>$days</option>";
														}?>
											</select>
										</div>
										<br>
										<center>
										<button id="submit-request-estimation" type="submit" class="btn btn-primary">
											<i class="material-icons">check</i>
											<span>APPLY FILTER(s)</span>
										</button>
										</center>
									</div>
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

//ESTIMATION START
//Highcharts license: 100051577-1nkgah

Highcharts.chart('estimationContainer', {
  yAxis: {
    min: 0
  },
  title: {
    text: "<?php echo $title; ?> bug Trend"
  },
    subtitle: {
	<?php if ($endPoint == true) : ?>
    text: "<center><p><br><b>This plot shows how the open defects count has developed over the recent period. The scatterplot represents the open defets count over the last 3 months. The regression line shows an estimation of when there will be 0 open defects.<br> <b>It's calculated by subtracting 'open defects since x days' with 'closed defects since x days'. If the closed defects are greater than the open defects then a negative trend will be shown. The script is by default set to check if a negative trend can be shown by the last 2 days, if it's not possible then that number will keep getting incremented by 1 until a negative trend can be shown. It is possible for the user to choose the days-count either by the dropdown menu or by the url.<br /></b><br> The open defect count is estimated to drop to zero in about <b><?php echo round($endPoint) . " days</b>." . "<br>A negative trend can be found in <b>". $daysCount . " days</b>.";?></p></center>"
	<?php else : ?>
    text: "<center><p><br>A negative trend cannot be found with the provided data.</p></center>"
	<?php endif ; ?>
  },
  tooltip: {
		formatter : function() {
			return 'Open bugs: <b>' + this.y + '<br></b>Day ' + (this.x)
		}
    },
  series: [{
    type: 'line',
	<?php if ($endPoint == true) : ?>
    data: [[-1, <?php echo($regressionstart); ?>], [<?php echo round($endPoint); ?>, <?php echo($regressionslut); ?>]], 
	<?php endif ; ?> 
    name: 'Regression Line',
    marker: {
      enabled: true
    },
    states: {
      hover: {
        lineWidth: 5
      }
    },
    enableMouseTracking: true
  }, {
    type: 'scatter',
    name: 'Days',
	lineWidth: 1,
    pointStart: <?php echo (count($openBugArr)*-1) ?>,
    data: [<?php echo implode(', ', $openBugArr); ?>],
    marker: {
      radius: 4
    }
  }]
});

//ESTIMATION END 
</script>
</html>