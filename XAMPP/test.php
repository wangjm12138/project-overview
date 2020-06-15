<?php
//ini_set('display_errors', 'On');
require_once('getcolor.php');
if(isset($_GET["name"])) { 
	$testplan = $_GET["name"];
}
if(isset($_GET["status"])) { 
	require 'testcases.php';
	exit();
}

//Used for removing the sync alert message after 10 seconds
if (isset($_SESSION["refresh"])) {
	$duration = 10; 
	$current_time = time(); 
	if(isset($_SESSION["discard"])){  
		if(((time() - $_SESSION['discard']) > $duration)){ 
			session_unset();
			session_destroy();
		} 
	}
}

if (isset($_POST["refresh"])) {
	$result = exec("$pypath/python.exe $path/Refresh.py $jamaid testplan $testplan");
	echo $result;
	exit();
}

$output = [];
$order = array("PASSED" => 1, "FAILED" => 2, "BLOCKED" => 3, "NOT_RUN" => 4, "INPROGRESS" => 5, "SCHEDULED" => 6, "NOT_SCHEDULED" => 7);

if ($type == "testmaturity") {
	$testplans = $file_db->query("SELECT testplan_id, testplan_name FROM testcases GROUP BY testplan_id, testplan_name ORDER BY testplan_name");
	//If testplan id is provided in the url
	if (isset($_GET["name"])){
		$stmt = $file_db->query("SELECT rel FROM testcases WHERE testplan_id='$testplan' GROUP BY rel");
		$releases = [];
		foreach ($stmt as $row) {
			if (!in_array($row["rel"], $releases)) {
				array_push($releases, $row["rel"]);
			}
		}
		//If release filter is provided in the url
		if (isset($_GET["rel"])) {
			$rel = "'". implode("','", explode("," , $_GET["rel"])) ."'";
			$results = $file_db->query("SELECT status, executionDate as date, count(*) as count FROM testcases WHERE testplan_id=$testplan AND rel IN ($rel) GROUP BY status, executionDate ORDER by executionDate ASC");
			//Get the latest run date
			$temp = $file_db->query("SELECT MAX(executionDate) as date from testcases WHERE testplan_id=$testplan AND rel IN ($rel)");
			$lastupdated = date('j. F, Y', strtotime($temp->fetch()['date']));
			//Get earliest date
			$temp = $file_db->query("SELECT min(executionDate) as date FROM testcases WHERE testplan_id='$testplan'AND rel IN ($rel) AND executionDate != 0 LIMIT 1");
			foreach ($temp as $row) {
				$earliest = $row['date'];
			}
		} else {
			//Get latest test run
			$stmt = $file_db->query("SELECT min(executionDate) as date FROM testcases WHERE testplan_id='$testplan' AND executionDate != 0 LIMIT 1");
			foreach ($stmt as $row) {
				$earliest = $row['date'];
			}
			//Get data
			$results = $file_db->query("SELECT status, executionDate as date, count(*) as count FROM testcases WHERE testplan_id='$testplan' GROUP BY status, executionDate ORDER by executionDate ASC");
			//Get the latest run date
			$stmt = $file_db->query("SELECT MAX(executionDate) as date from testcases WHERE testplan_id='$testplan' LIMIT 1");
			foreach ($stmt as $row) {
				$stmt = $row['date'];
			}
			$lastupdated = date('j. F, Y', strtotime($stmt));
			if ($lastupdated == "1. January, 1970") {
				$lastupdated = "N/A";
			}
		}
		//Get testplan name
		$stmt = $file_db->query("SELECT testplan_name FROM testcases WHERE testplan_id= '$testplan' LIMIT 1");
		foreach ($stmt as $row) {
				$testname = $row["testplan_name"];
			}
			
		foreach ($results as $row) {
			if ($row["date"] == 0) {
				if ($earliest == 0) {
					$date = date("Y-m-d");
				} else {
					$date = $earliest;
				}
			} else {
				$date = $row["date"];
			}
			$sqldata[$row["status"]][$date] = $row["count"];
		}
		//Sorts the array
		array_multisort(array_values($sqldata), SORT_ASC, array_keys($sqldata), SORT_ASC, $sqldata);
		
		foreach ($sqldata as $st => $status) {
			$pdata = [];
			$stfixed = ucwords(strtolower(str_replace("_", " ", $st)));
			$color = getColorJama($stfixed);
			foreach ($status as $dt => $date) {
				if (($st == "SCHEDULED" or $st == "NOT_SCHEDULED" or $st == "NOT_RUN") AND ($dt == $earliest)) {
					$pdata[] = "{x: moment('$dt').valueOf(), y: $date, url: '$st/0'}";
				} else {
					$pdata[] = "{x: moment('$dt').valueOf(), y: $date, url: '$st/$dt'}";
				}
			}
			$index += 1;
			$output[] = "{name: '$stfixed', color: '$color', data: [".implode( ', ', $pdata )."], index: $index}";
		}
	}
} elseif ($type == "testplan") {
	$testplans = $file_db->query("SELECT testplan_id, testplan_name, testplan_status FROM tests GROUP BY testplan_id, testplan_name, testplan_status ORDER BY testplan_name");
	if (isset($_GET["name"])){
		//Get all releases
		$statement = $file_db->query("SELECT rel FROM testcases WHERE testplan_id= '$testplan' GROUP BY rel");
		
		$releases = [];
		foreach ($statement as $row) {
			if (!in_array($row["rel"], $releases)) {
				array_push($releases, $row["rel"]);
			}
		}
		//Get name
		$testname = $file_db->query("SELECT testplan_name FROM tests WHERE testplan_id= '$testplan' LIMIT 1");
		foreach ($testname as $row) {
			$testname = $row['testplan_name'];
		}
		
		//If release filter is provided in the url
		if (isset($_GET["rel"])) {
			$rel = explode("," , $_GET["rel"]);
			$results = $file_db->query("SELECT * FROM tests WHERE testplan_id=$testplan AND rel IN ('" . implode("','", $rel) . "')");
			//Get the latest run date
			$temp = $file_db->query("SELECT MAX(date) as date from tests WHERE testplan_id=$testplan AND rel IN ('" . implode("','", $rel) . "')");
			$lastupdated = date('j. F, Y', strtotime($temp->fetch()['date']));
			$totalpassed = "SELECT count(*) as count FROM testcases WHERE testplan_id=:testplan AND status='PASSED' AND rel IN ('" . implode("','", $rel) . "')";
			$totalfailed = "SELECT count(*) as count FROM testcases WHERE testplan_id=:testplan AND status='FAILED' AND rel IN ('" . implode("','", $rel) . "')";
			$totalblocked = "SELECT count(*) as count FROM testcases WHERE testplan_id=:testplan AND status='BLOCKED' AND rel IN ('" . implode("','", $rel) . "')";
			$totaltest = "SELECT count(*) as count FROM testcases WHERE testplan_id=:testplan AND rel IN ('" . implode("','", $rel) . "')";
		} else {
			//Get data for plot
			$results = $file_db->query("SELECT * FROM tests WHERE testplan_id='$testplan' ORDER BY date ASC");
			//Get the latest run date
			$stmt = $file_db->query("SELECT MAX(date) AS date FROM tests WHERE testplan_id='$testplan'");
			foreach ($stmt as $row) {
				$stmt = $row['date'];
			}
			$lastupdated = date('j. F, Y', strtotime($stmt));
			$totalpassed = "SELECT count(*) as count FROM testcases WHERE testplan_id= '$testplan' AND status='PASSED'";
			$totalfailed = "SELECT count(*) as count FROM testcases WHERE testplan_id='$testplan' AND status='FAILED'";
			$totalblocked = "SELECT count(*) as count FROM testcases WHERE testplan_id='$testplan' AND status='BLOCKED'";
			$totaltest = "SELECT count(*) as count FROM testcases WHERE testplan_id='$testplan'";
		}

		//Save data from db to array
		foreach ($results as $row) {
			if (!isset($sqldata[$row["status"]][$row["date"]])) {
				$sqldata[$row["status"]][$row["date"]] = $row["count"];
			} else {
				$sqldata[$row["status"]][$row["date"]] += $row["count"];
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
		
		//Sorts the array after the given key in $order[]
		uksort($sqldata, function($a, $b) use($order) {
			return $order[$a] > $order[$b];
		});
		
		//Data needed to calculate maturity
		$stmt = $file_db->query($totalpassed);
		foreach ($stmt as $row) {
				$totalpassed = $row["count"];
			}
		
		$stmt = $file_db->query($totalfailed);
		foreach ($stmt as $row) {
			$totalfailed = $row["count"];
		}
		
		$stmt = $file_db->query($totalblocked);
		foreach ($stmt as $row) {
			$totalblocked = $row["count"];
		}

		$stmt = $file_db->query($totaltest);
		foreach ($stmt as $row) {
			$totaltest = $row["count"];
		}
		
		$totalexecuted = $totalpassed + $totalfailed + $totalblocked;

		if ($totalpassed > 0) { 
			$maturity = "<sup>$totalpassed</sup>/<sub>$totalexecuted</sub> =<b> " . round(floatval($totalpassed) / $totalexecuted * 100) . "%</b>";
		} else {
			$maturity = "<sup>$totalpassed</sup>/<sub>$totalexecuted</sub> = 0%";
		}
		
		if ($totalexecuted > 0) {
			$coverage = "<sup>$totalexecuted</sup>/<sub>$totaltest</sub> =<b> " . round(floatval($totalexecuted) / $totaltest * 100) . "%</b>";
		} else {
			$coverage = "<sup>$totalexecuted</sup>/<sub>$totaltest</sub> = 0%";
		}
		
		foreach ($sqldata as $st => $status) {
			$pdata = [];
			$stfixed = ucwords(strtolower(str_replace("_", " ", $st)));
			$color = getColorJama($stfixed);
			foreach ($status as $dt => $date) {
				$pdata[] = "{x: moment('$dt').valueOf(), y: $date}";
			}
			$index += 1;
			$output[] = "{name: '$stfixed', color: '$color', data: [".implode( ', ', $pdata )."], index: $index}";
		}
	}
}

if (isset($testname)) {
	$max_date = $file_db->query("SELECT max(date) as maxDate FROM tests");
	foreach($max_date as $row){$max_date = $row["maxDate"];}
	$currentTestplans = $file_db->query("SELECT status, sum(count) as count FROM tests WHERE testplan_name = '$testname' AND date = '$max_date' group by status");
	
	$sts = array();
	$count = array();
	foreach($currentTestplans as $row)
	{
		array_push($sts, $row["status"]);
		array_push($count, $row["count"]);
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
	<link href="/css/sweetalert.css" rel="stylesheet">
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
	<script src="/js/sweetalert.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
	<!-- Highcharts -->
    <script src="/js/highcharts.js"></script>
    <script src="/js/highcharts-more.js"></script>
    <script src="/js/exporting.js"></script>
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
			<div class="row">
				<?php if (!isset($_GET["name"])) : ?>
				<div class="col-md-14" onmousedown="return false">
					<div class="card">
						<div class="header">
							<h4><?php echo $projectname; ?> - Test plans</h4>
						</div>
						<div class="body">
							<!-- Nav tabs -->
							<?php if ($type != "testmaturity") : ?>
							<ul class="nav nav-tabs tab-nav-right" role="tablist">
								<li class="active"><a href="#active" data-toggle="tab"><i class="material-icons">unarchive</i> ACTIVE</a></li>
								<li><a href="#archived" data-toggle="tab"><i class="material-icons">archive</i> ARCHIVED</a></li>
							</ul>
							<?php endif; ?>
							
							<!-- Tab panes -->
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane fade in active" id="active">
									<table class="table">
										<thead class="text-primary">
										<th>Active Test plan</th>
										</thead>
										<tbody>
											<?php foreach($testplans as $raw): ?>
											<?php if (($type == "testplan" AND $raw["testplan_status"] == "Active") OR ($type == "testmaturity")) : ?>
											<tr>
												<td class="text-primary"><a href="<?php echo $type; ?>/<?php echo $raw["testplan_id"]; ?>"><?php echo $raw["testplan_name"]; ?></a></td>
											</tr>											
											<?php endif; ?>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
								<div role="tabpanel" class="tab-pane fade" id="archived">
									<table class="table">
										<thead class="text-primary">
										<th>Archived Test plan</th>
										</thead>
										<tbody>
											<?php foreach($testplans as $raw): ?>
											<?php if ($raw["testplan_status"] == "Archived") : ?>
											<tr>
												<td class="text-primary"><a href="<?php echo $type; ?>/<?php echo $raw["testplan_id"]; ?>"><?php echo $raw["testplan_name"]; ?></a></td>
											</tr>
											<?php endif; ?>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>								
							</div>
						</div>
					</div>
				</div>
				<?php else : ?>
				<div class="col-md-14">
					<div class="card">
						<div class="header">
						<?php if ($type == "testplan") : ?>
						<h4><?php echo $testname; ?> - Test Plan</h4>
						<?php else : ?>
						<h4><?php echo $testname; ?> - Test Maturity</h4>
						<?php endif ; ?>
						</div>
						<div class="body">
							<!-- Nav tabs -->
							<ul class="nav nav-tabs tab-nav-right" role="tablist">
								<li class="active"><a href="#trend" data-toggle="tab"><i class="material-icons">ic_trending_up</i> TREND</a></li>
								<li><a href="#table" data-toggle="tab"><i class="material-icons">ic_list</i> TABLE</a></li>
							</ul>
							<!-- Tab panes -->
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane fade in active" id="trend">
								<?php if ($type == "testplan") : ?>
									<div id="container" style="min-width: 310px; height: 600px; margin: 0 auto"></div>
									<p>
										<span style="float: left; width: 33%; text-align: right;"><b>Test Coverage</b> = <sup>Test cases executed</sup>/<sub>Total test </sub> = <?php echo $coverage; ?> </span>
										<span style="float: right; width: 33%; text-align: left;"><b>Test Maturity</b> = <sup>Test cases passed</sup>/<sub>Test cases executed </sub> = <?php echo $maturity; ?> </span>
									</p>
									<br>
								<?php elseif ($type == "testmaturity") : ?>
									<div id="container" style="min-width: 310px; height: 600px; margin: 0 auto"></div>
								<?php endif; ?>
								<?php if (count($releases) > 1) : ?>
								<script>
									$('body').on('click','#submit-request',function(){
										var rel = "";
										$('select[name="rel"] option:selected').each(function(){
											rel += encodeURIComponent($(this).attr('value'))+",";
										});
										rel = rel.replace(/,\s*$/, "");
										link = "<?php echo "https://$_SERVER[HTTP_HOST]".strtok($_SERVER['REQUEST_URI'],'&').""; ?>";
										if (rel.length >= 1) {
											link += "&rel="+rel;
										}
										window.location.href = link;
										
									});
								</script>
								<div class="row clearfix js-sweetalert">
									<div class="col-md-4">
										<p><b>Release</b></p>
										<select name="rel" class="form-control show-tick" multiple id="rel">
											<?php foreach ($releases as $rel) { 
											echo "
											<option value='$rel'>$rel</option>";}?>
										</select>
									</div>
									<br>
									<button id="submit-request" type="submit" class="btn btn-primary">
										<i class="material-icons">check</i>
										<span>APPLY FILTER(s)</span>
									</button>
									<button id="refresh" onclick="refresh()" type="submit" class="btn btn-primary">
										<i class="material-icons">refresh</i>
										<span>REFRESH DATA</span>
									</button>
									</button>
								</div>
								<?php else : ?>
								<div class="row clearfix js-sweetalert">
									<center>
										<button id="refresh" onclick="refresh()" type="submit" class="btn btn-primary">
											<i class="material-icons">refresh</i>
											<span>REFRESH DATA</span>
										</button>
									</center>
								</div>
								<?php endif; ?>
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
								</div>
								<div role="tabpanel" class="tab-pane fade" id="table">
									<div class="table-responsive">
										<table id="rawtable" class="table table-striped table-bordered wrap" width="100%">
											<thead>
												<th>Testcase ID</th>
												<th>Testcase name</th>
												<th>Status</th>
												<th>Release</th>
												<th>Testgroup</th>
												<th>Testcycle</th>
												<th>Execution date</th>
											</thead>
											<tfoot>
												<th>Testcase ID</th>
												<th>Testcase name</th>
												<th>Status</th>
												<th>Release</th>
												<th>Testgroup</th>
												<th>Testcycle</th>
												<th>Execution date</th>
											</tfoot>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
</body>
<?php if (isset($_GET["name"])) : ?>
<!-- ?php echo $output ? -->
<script>
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
                    swal({
                        title: "Error!",
                        text: "Can't establish connection to Jama. Please try again later",
                        type: "error"
                    });
                }
            },
            error: function(xhr, ajaxOptions, thrownError) {
                swal("Error!", "Can't establish connection to Jama. Please try again later", "error");
            }
        });
    });
});
$(document).ready(function() {
	
	$.fn.dataTable.ext.errMode = function(obj,param,err){
                var tableId = obj.sTableId;
                console.log('Handling DataTable issue of Table '+tableId);
        };
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
				'testplan': '<?php echo $testplan; ?>',
				<?php if (isset($_GET["rel"])) {
				echo "'rel': '".$_GET["rel"]."'";
				}?>
			}
		},
		"columns" : [
			{"data" : "uniqueid"},
			{"data" : "name"},
			{"data" : "status"},
			{"data" : "rel"},
			{"data" : "testgroup_name"},
			{"data" : "testcycle_name"},
			{"data" : "executionDate"}
		],
		"columnDefs": [
			{
				"render": function ( data, type, row ) {
					return "<a href='<?php echo $config["jama"]."#/testcases/"?>"+row["id"]+"<?php echo "?projectId=$jamaid" ?>' target='blank' title='Show testcase in Jama'>"+row["uniqueid"]+"</a>";
				},
				"targets": [0]
			},
			{
				"render": function ( data, type, row ) {
					return "<a href='../relation?id="+row['id']+"&type=tc' target='blank' title='Show all defects associated with this test case'>"+row['name']+"</a>";
				},
				"targets": [1]
			},
			{
				"render": function ( data, type, row ) {
					if (row["testcycle_id"] == "0") {
						return "";
					} else {
						return "<a href='<?php echo $config["jama"]."?projectId=$jamaid&docId="?>"+row["testcycle_id"]+"' target='blank' title='Show testcycle in Jama'>"+row["testcycle_name"]+"</a>";
					}
				},
				"targets": [5]
			},
			{
				"render": function ( data, type, row ) {
					if (row["executionDate"] == "0") {
						return "";
					} else {
						return row["executionDate"];
					}
				},
				"targets": [6]
			},
			{
				"render": function ( data, type, row ) {
					status = row["status"].toLowerCase();
					return status.charAt(0).toUpperCase() + status.slice(1);
				},
				"targets": [2]
			}
		],
		rowCallback: function(row, data, index){
			status = data["status"].toLowerCase();
			status = status.charAt(0).toUpperCase() + status.slice(1);
			if (status == 'Passed'){
				$(row).find('td:contains('+status+')').css({'background': 'green', 'color': 'white'});
			} else if (status == 'Failed'){
				$(row).find('td:contains('+status+')').css({'background': 'red', 'color': 'white'});
			} else if (status == 'Scheduled'){
				$(row).find('td:contains('+status+')').css({'background': 'grey', 'color': 'white'});
			} else if (status == 'Not_scheduled'){
				$(row).find('td:contains('+status+')').css({'background': 'blue', 'color': 'white'});
			} else if (status == 'Blocked'){
				$(row).find('td:contains('+status+')').css({'background': 'yellow', 'color': 'black'});
			} else if (status == 'Inprogress'){
				$(row).find('td:contains('+status+')').css({'background': 'orange', 'color': 'white'});
			}
		},
		conditionalPaging: true,
		responsive: true,
		dom: 'Bfrtip',
		stateSave: true,
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
<?php endif; ?>
<script>
Highcharts.setOptions({
	global: {
		useUTC: false
	}
});
<?php if (($type == "testplan") and isset($_GET["name"])) : ?>
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
	title: {
		text: '<?php echo $testname; ?>'
	},
	subtitle: {
		text: '<center><p><b>The data below indicates the current test case status for this testplan. The data is collected by going through each test case for the given test plan and check the latest test run result.</b><br>Last updated: <?php echo $lastupdated; ?></p></center> <br> -------------------------- <?php if($sts != null) : ?> <br><p>Most recent data:</p> <?php for ($i = 0; $i < sizeof($sts); $i++) {if ($sts[$i] == 'FAILED') {echo'<br><p><b> Failed: '. $count[$i]. '</b></p>';}if ($sts[$i] == 'SCHEDULED') {echo'<br><p><b> Scheduled: '. $count[$i]. '</b></p>';}if ($sts[$i] == 'PASSED') {echo'<br><p><b> Passed: '. $count[$i]. '</b></p>';}if ($sts[$i] == 'NOT_SCHEDULED') {echo'<br><p><b> Not scheduled: '. $count[$i]. '</b></p>';}if ($sts[$i] == 'BLOCKED') {echo'<br><p><b> Blocked: '. $count[$i]. '</b></p>';}if ($sts[$i] == 'INPROGRESS') {echo'<br><p><b> In progress: '. $count[$i]. '</b></p>';}} endif;?>'
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
			text: "Test cases"
		}
	},
	tooltip: {
		shared: true,
		split: true,
		xDateFormat: '%A, %b %e, %Y'
	},
	plotOptions: {
		area: {
			turboThreshold: 0,
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
	series: [<?php echo implode(', ', $output); ?>]
});
<?php elseif (($type == "testmaturity") and isset($_GET["name"])) : ?>
var chart = Highcharts.chart('container', {
	chart: {
		type: 'column',
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
		text: '<?php echo $testname; ?>'
	},
	subtitle: {
		text: "<center><p><b>The data below indicates the last execution dates for all the test cases in this this testplan. The data is collected by going through each test case for the given test plan and check the latest test run results. Below you will find when the test was executed and the latest test case status.</b><br>Last test execution: <?php echo $lastupdated; ?></p></center>"
	},
	xAxis: {
		type: 'datetime',
		title: {
			enabled: true
		}
	},
	yAxis: {
		min: 0,
		allowDecimals: false,
		title: {
			text: 'Test cases'
		}
	},
	legend: {
		enabled: true
	},
	tooltip: {
		shared: false
	},
	plotOptions: {
		column: {
			stacking: 'normal',
			dataLabels: {
				enabled: true
			}
		},
		series: {
			allowPointSelect: true,
			point : {
				events: {
					click: function() {
						window.open(location.href+"/"+this.options.url, '_blank')
					}
				}
			}
		}
	},
	series: [<?php echo implode(', ', $output); ?>]
});
<?php endif; ?>
</script>
</html>