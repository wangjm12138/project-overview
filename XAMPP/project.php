<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
$path = $config["path"];
$project = strtoupper($_GET["project"]);
$pdp = $task = $bug = false;

$output = "";
$sqldata = [];

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
	$result = $file_db->query("SELECT * FROM projects WHERE jama = '$project'");
} else {
	$result = $file_db->query("SELECT * FROM projects WHERE keyy = '$project'");
}

// Check if project exists by using its jira or jama id 
if ($result) {
	foreach ($result as $row) {
	//Project's info
	$jamaid = $row["jama"];
	$title = $row["name"];
	$projectname = $row["name"];
	if (strlen($row["keyy"]) > 2) {
		$project = $row["keyy"];
		$jira = true;
	} else {
		$jira = false;
	}
}
} elseif (file_exists('C:\xampp\mysql\data\\'.$project.'.db')) {
	$jamaid = $title = $projectname = $project;
	$jira = false;
} else {
	echo "No data found";
	exit();
}

//Check if the user needs to access to Jama plot (.htaccess)
if (isset($_GET["platform"]) && $_GET["platform"] == "jama") {
	include("jama.php");
	exit();
}

if (file_exists("$path/db/jira/$project.db")) { //check if the project has a database before connecting to avoid creating a new database 
	// Connect to the project database
	$file_db = new PDO("sqlite:$path/db/jira/$project.db");
} 

if ($jira) {
	$rowid = $file_db->query("SELECT max(rowid) AS rowid FROM jirasessions")->fetch()["rowid"]; //Used for fetching latest run
	$openpdp = $file_db->query("SELECT sum(count) AS count FROM jirastatus WHERE jirasession_id=$rowid AND labels='PDP' AND status!='Closed' and status!='Done' and status!='Resolved' AND type='Task'")->fetch()["count"];
	$opentask = $file_db->query("SELECT sum(count) AS count FROM jirastatus WHERE jirasession_id=$rowid AND labels='Other' AND status!='Closed' and status!='Done' and status!='Resolved' AND type='Task'")->fetch()["count"];
	$openbug = $file_db->query("SELECT sum(count) AS count FROM jirastatus WHERE jirasession_id=$rowid AND labels='Other' AND status!='Closed' and status!='Resolved' AND status!='Done' AND type='Bug'")->fetch()["count"];
	$temp = $file_db->query("SELECT type, labels FROM jirastatus WHERE jirasession_id=$rowid");
	foreach ($temp as $row) {
		if ($row["type"] == "Task" && $row["labels"] == "Other") {
			$task = true;
		} 
		if ($row["type"] == "Bug") {
			$bug = true;
		} 
		if ($row["labels"] == "PDP") {
			$pdp = true;
		}
	}	
	$results = $file_db->query("SELECT type, targetbuild, status, labels, team, date, sum(count) from jirastatus WHERE jirasession_id=$rowid GROUP BY targetbuild, type, status, labels, team ORDER BY (CASE WHEN targetbuild='Marketing demo' THEN 1 WHEN targetbuild='Trunk' THEN 2 WHEN targetbuild='Phase -2' THEN 3 WHEN targetbuild='Phase -1' THEN 4 WHEN targetbuild='Phase A' THEN 5 WHEN targetbuild='Phase B' THEN 6 WHEN targetbuild='Phase C' THEN 7 WHEN targetbuild='Phase C' THEN 8 WHEN targetbuild='Phase D' THEN 9 WHEN targetbuild='Phase E' THEN 10 WHEN targetbuild='Phase F' THEN 11 WHEN targetbuild='Proto' THEN 12 WHEN targetbuild='Alpha' THEN 13 WHEN targetbuild='Beta' THEN 14 WHEN targetbuild='Pilot' THEN 15 WHEN targetbuild='Mass' THEN 16 WHEN targetbuild='SR01' THEN 17 WHEN targetbuild='SR02' THEN 18 WHEN targetbuild='SR03' THEN 19 WHEN targetbuild='SR04' THEN 20 WHEN targetbuild='SR05' THEN 21 WHEN targetbuild='SR06' THEN 22 WHEN targetbuild='SR07' THEN 23 WHEN targetbuild='SR08' THEN 24 WHEN targetbuild='SR09' THEN 25 WHEN targetbuild='SR10' THEN 26 ELSE 27 END)");
	
	//Save sql data in array
	foreach ($results as $row) {
		$sqldata[$row["targetbuild"]][$row["labels"]][$row["status"]][$row["type"]][$row["team"]] = ["amount" => $row["sum(count)"], "status" => ""];
	}
	
	//Build project overview table
	foreach ($sqldata as $tg => $targetbuild) {
		$alltasks = $allpdp = $alldefects = "";
		foreach ($targetbuild as $lb => $label) {
			foreach ($label as $st => $status) {
				foreach ($status as $tp => $type) {
					$totaltasks = $totalpdp = $totaldefects = 0;
					$temptask = $temppdp = $tempdefect = "<p>";
					foreach ($type as $tm => $team) {
						if ($lb == "PDP") {
							$temppdp .= $tm . ": <a href='$project/table?targetbuild=$tg&labels=$lb&status=$st&type=$tp&team=".str_replace("&","%26",$tm)."' title='Expand issues in a table' target='_self'>".$team['amount']."</a><br>";
							$totalpdp += $team['amount'];
						} elseif ($tp == "Bug") {
							$tempdefect .= $tm . ": <a href='$project/table?targetbuild=$tg&status=$st&type=$tp&team=".str_replace("&","%26",$tm)."' title='Expand issues in a table' target='_self'>".$team['amount']."</a><br>";
							$totaldefects += $team['amount'];							
						} elseif ($tp == "Task" or $tp == "Sub-task") {			
							$temptask .= $tm . ": <a href='$project/table?targetbuild=$tg&labels=$lb&status=$st&type=$tp&team=".str_replace("&","%26",$tm)."' title='Expand issues in a table' target='_self'>".$team['amount']."</a><br>";
							$totaltasks += $team['amount'];											
						}
					}
					$temptask .= $temppdp .= $tempdefect .= "</p>";
					//Compare current results from yesterday
					$diff = $file_db->query("SELECT sum(count) as count FROM jirastatus WHERE jirasession_id=".($rowid-1)." AND targetbuild='$tg' AND labels='$lb' AND status='$st' AND type='$tp'")->fetch()['count'];
					if ($lb == "PDP") {
						if ($totalpdp > $diff and $diff > 0) {
							$trend = "<i class=\"material-icons up\">trending_up</i>";
						} elseif ($totalpdp < $diff) {
							$trend = "<i class=\"material-icons up\">trending_down</i>";
						} elseif ($totalpdp = $diff) {
							$trend = "<i class=\"material-icons up\">trending_flat</i>";
						} else {
							$trend = "(trend)";
						}
						$allpdp .= "<h5><span title='Click to collapse row and show teams'>$st $lb $tp: <a href='$project/table?targetbuild=$tg&labels=$lb&status=$st&type=$tp' title='Expand issues in a table' target='_self'>$totalpdp</a>&nbsp;<a href='$project/plot?targetbuild=$tg&labels=$lb&status=$st&type=$tp' title='Show trend' target='_self'>$trend</a></h4><p>" . $temppdp;											
					} elseif ($tp == "Bug") {
						if ($totaldefects > $diff and $diff > 0) {
							$trend = "<i class=\"material-icons up\">trending_up</i>";
						} elseif ($totaldefects < $diff) {
							$trend = "<i class=\"material-icons up\">trending_down</i>";
						} elseif ($totaldefects == $diff) {
							$trend = "<i class=\"material-icons up\">trending_flat</i>";
						} else {
							$trend = "(trend)";
						}
						$alldefects .= "<h5><span title='Click to collapse row and show teams'>$st $tp: <a href='$project/table?targetbuild=$tg&status=$st&type=$tp' title='Expand issues in a table' target='_self'>$totaldefects</a>&nbsp;<a href='$project/plot?targetbuild=$tg&status=$st&type=$tp' title='Show trend' target='_self'>$trend</a></h4><p>" . $tempdefect;											
					} elseif ($tp == "Task" or $tp == "Sub-task") {
						if ($totaltasks > $diff and $diff > 0) {
							$trend = "<i class='material-icons up'>trending_up</i>";
						} elseif ($totaltasks < $diff) {
							$trend = "<i class='material-icons up'>trending_down</i>";
						} elseif ($totaltasks == $diff) {
							$trend = "<i class='material-icons up'>trending_flat</i>";
						} else {
							$trend = "(trend)";
						}
						$alltasks .= "<h5><span title='Click to collapse row and show teams'>$st $tp: <a href='$project/table?targetbuild=$tg&labels=$lb&status=$st&type=$tp' title='Expand issues in a table' target='_self'>$totaltasks</a>&nbsp;<a href='$project/plot?targetbuild=$tg&labels=$lb&status=$st&type=$tp' title='Show trend' target='_self'>$trend</a></h4><p>" . $temptask;
					}
				}
			}
		}		
		$output .= "<tr><td><a href='$project/plot?targetbuild=$tg' title='Show trend for this target build' target='_self'>$tg</a></td>";
		if ($pdp) {
			$output .= "<td>$allpdp</td>";
		}
		if ($task) {
			$output .= "<td>$alltasks</td>";
		}
		if ($bug) {
			$output .= "<td>$alldefects</td>";
		}
		$output .= "</tr>";
	}
	
	//Generate data for custom plotting
	$targetbuildlist = $teamlist = $typelist = $labellist = $prioritylist = $statuslist = [];
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
}

// Close file db connection
$file_db = null;
?>

<!DOCTYPE html>
<html>
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
	<!--   Core JS Files   -->
    <script src="/js/jquery.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js//material.min.js"></script>
	<!-- Plugins -->
    <script src="/js/bootstrap-select.min.js"></script>
    <script src="/js/jquery.slimscroll.min.js"></script>
    <script src="/js/waves.min.js"></script>
    <script src="/js/jquery.countTo.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
    <script src="/js/index.js"></script>
    <!-- Bootstrap core CSS     -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <!-- Plugins -->
    <link href="/css/waves.min.css" rel="stylesheet" />
	<link href="/css/bootstrap-select.min.css" rel="stylesheet">
    <!-- Custom Css -->
    <link href="/css/style.css" rel="stylesheet">
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
	</style>
	
    <script type="text/javascript">
        $(document).ready(
		    function () {
		        $('td p').slideUp();
		        $('td h5').click(
		            function () {
		                $(this).siblings('p').slideToggle();
		            }
		        );
		    }
		);
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
        <div class="container-fluid" onmousedown="return false">
            <!-- Widgets -->
            <div class="row clearfix">
				<?php if ($pdp) : ?>
				<a href = "<?php echo $project;?>/table?labels=PDP&status=Open&type=Task">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box bg-pink hover-expand-effect">
                        <div class="icon">
                            <i class="material-icons">assignment</i>
                        </div>
						<div class="content">
                            <div class="text">OPEN PDP TASKS</div>
                            <div class="number count-to" data-from="0" data-to="<?php echo $openpdp; ?>" data-speed="15" data-fresh-interval="20"></div>
                        </div>
                    </div>
                </div>
				</a>
				<?php endif; ?>
				<?php if ($task) : ?>
				<a href = "<?php echo $project;?>/table?labels=Other&status=Open&type=Task">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box bg-cyan hover-expand-effect">
                        <div class="icon">
                            <i class="material-icons">help</i>
                        </div>
                        <div class="content">
                            <div class="text">OPEN TASKS</div>
                            <?php if ($jira) : ?><div class="number count-to" data-from="0" data-to="<?php echo $opentask; ?>" data-speed="1000" data-fresh-interval="20"></div><?php endif; ?>
                        </div>
                    </div>
                </div>
				</a>
				<?php endif; ?>
				<?php if ($bug) : ?>
				<a href = "<?php echo $project;?>/table?status=Open&type=Bug">
                <div class="col-lg-3 col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box bg-light-green hover-expand-effect">
                        <div class="icon">
                            <i class="material-icons">bug_report</i>
                        </div>
                        <div class="content">
                            <div class="text">OPEN BUGS</div>
                            <?php if ($jira) : ?><div class="number count-to" data-from="0" data-to="<?php echo $openbug; ?>" data-speed="1000" data-fresh-interval="20"></div><?php endif; ?>
                        </div>
                    </div>
                </div>
				</a>
				<?php endif; ?>
            </div>
            <!-- #END# Widgets -->
            <div class="row clearfix">
                <!-- Task Info -->
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
                    <div class="card">
                        <div class="header">
                            <h2>Progress per target build</h2>
                        </div>
                        <div class="body">
                            <div class="table-responsive">
                                <table class="table table-hover dashboard-task-infos">
                                    <thead>
                                        <tr>
                                            <th>Target build</th>
											<?php if ($pdp) : echo "<th><b>PDP </b>(<a href='$project/plot?labels=PDP&type=Task'>trend</a>)</th>"; endif; ?>
											<?php if ($task) : echo "<th><b>Task </b>(<a href='$project/plot?labels=Other&type=Task'>trend</a>)</th>"; endif; ?>
											<?php if ($bug) : echo "<th><b>Bug </b>(<a href='$project/plot?type=Bug'>trend</a>)</th>"; endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
										<?php echo $output;?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
				<?php if (isset($targetbuildlist)) : ?>
				<!-- Custom builder-->
				<div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
					<div class="card">
						<div class="header">
							<h2>Custom plot generator</h2>
							<span>Build custom plot with other conditions than provided above</span>
						</div>
						<div class="body">
							<div class="row clearfix">
							<script>
								$('body').on('click','#submit-request',function(){
									var statuses = "", teams = "", targetbuilds = "", types = "", labels = "", priorities = "";
									$('select[name="status"] option:selected').each(function(){
										statuses += encodeURIComponent($(this).attr('value'))+",";
									});
									statuses = statuses.replace(/,\s*$/, "");
									$('select[name="team"] option:selected').each(function(){
										teams += encodeURIComponent($(this).attr('value').replace("&", "%26"))+",";
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
									link = window.location.href+"/plot?";
									opt = true;
									if (statuses.length >= 1) {
										if (opt) {
											link += "status="+statuses;
											opt = false;
										} else {
											link += "&status="+statuses;
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
									if (types.length >= 1) {
										if (opt) {
											link += "type="+types;
											opt = false;
										} else {
											link += "&type="+types;
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
							</div>
						</div>
					</div>
				</div>
				<!-- #END# Custom builder -->
				<?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>

