<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
require_once('getcolor.php'); //required for generating or finding the right color for the series
$path = $config["path"];
$project = $_GET["project"];
$index = 0;


$type = $_GET["type"]; //Get url argument "type"
$projectname = $_GET["project"];

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


$arr = [];

if (substr($project, 0, 2) == "20") {
	$temp = $file_db->query("SELECT jama FROM projects WHERE jama = '$project'");
} else {
	$temp = $file_db->query("SELECT jama FROM projects WHERE keyy = '$project'");
}

foreach ($temp as $row) {
	array_push($arr, $row["jama"]);
}
$jamaid = $arr[0];
$jira = $project;
$file_db->close();


$dbname = $jamaid;
// Create connection
$file_db = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($file_db->connect_error) {
    die("Connection failed: " . $file_db->connect_error);
}

$drilloutput = $output = $sqldata = $selrel = [];

if(isset($_GET["id"])) { 
	$id = $_GET["id"];
}
if(isset($_GET["type"])) { 
	$type = $_GET["type"];
}

$results = $file_db ->query("SELECT * FROM $type ORDER BY status ASC, date ASC");

function buildSelect($type) {
	global $select;
	if (strlen($select) == 0) {
		$select .= "$type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	} else {
		$select .= "AND $type IN ('" . implode("','", explode(",", $_GET[$type])) . "') ";
	}
}

if (isset($_GET["rel"])) {
		if (isset($_GET["rel"])) {
			buildSelect("rel");
		}
		$results = $file_db ->query("SELECT * FROM $type WHERE $select ORDER BY status ASC, date ASC");
	}

$order = array("Passed" => 1, "Failed" => 2, "Incomplete Testing" => 3, "Missing Test Coverage" => 4);
	
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
	}

array_multisort(array_values($releasedata), SORT_ASC, array_keys($releasedata), SORT_ASC, $releasedata);

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
	
foreach ($sqldata as $st => $status) {
	$pdata = [];
	$color = getColorJama($st);
	foreach ($status as $date => $count) {
		$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."'}";
	}
	$index += 1;
	$output[] = "{name:'$st', color: '$color', data: [".implode( ', ', $pdata )."], index: $index}";
}

foreach ($releasedata as $st => $status) {
		foreach ($status as $rl => $rel) {
			$pdata = [];
			foreach ($rel as $date => $count) {
				$pdata[] = "{x: moment('$date').valueOf(), y: $count}";
			}
			$color = getColorJama($rl);
			$drilloutput[] = "{type: 'area', id: '".str_replace(" ", "", $st)."', name: '$rl', color: '$color', data: [".implode( ', ', $pdata )."]}";
		}
	}
	
if ($type == "feattest") {
	$typename = "Features";
} else {
	$typename = "Requirements";
}
	
//Get all available releases
	$temp = $file_db->query("SELECT DISTINCT rel FROM $type");
	foreach ($temp as $row) {
		if (!in_array(ucwords($row["rel"]), $selrel)) {
			array_push($selrel, ucwords($row["rel"]));
		}
	}
	
$proj_name = strtolower($projectname);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

    <title><?php echo ucwords($proj_name); ?>, project details</title>
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


</body>
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
								<li class="active"><a href="#feattest" data-toggle="tab"><i class="material-icons">trending_up</i> TREND</a></li>
								<li><a href="#table" data-toggle="tab"><i class="material-icons">ic_list</i> TABLE</a></li>
							</ul>
							
							<!-- Tab panes -->
							<div class="tab-content">
							
								<div role="tabpanel" class="tab-pane fade in active chart" id="feattest">
									<div id="container" style="width:100%; height: 600px; margin: 0 auto"></div>
									
									<div class="row clearfix">
									<script>
										$('body').on('click','#submit-request-estimation',function(){
											var releases = "";
											
											$('select[name="rel"] option:selected').each(function(){
												releases += encodeURIComponent($(this).attr('value'))+",";
											});
											releases = releases.replace(/,\s*$/, "");
											
											link = window.location.href.split('?')[0]+"?";
											opt = true;
											if (releases.length >= 1) {
												if (opt) {
													link += "rel="+releases;
													opt = false;
												} else {
													link += "&rel="+releases;
												}
											}
											
											window.location.href = link;
										});
									</script>
										<div class="col-md-4">
											<p><b>Release</b></p>
											<select name="rel" class="form-control show-tick" multiple id="rel">
												<?php foreach ($selrel as $rel) { 
												echo "
												<option value='$rel'>$rel</option>";}?>
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
											<tr>
												<th>Jama ID</th>
												<th>Name</th>
												<th>Test Status</th>
												<th>Release</th>
												<?php if($type == "reqtest") : ?>
												<th>Team</th>
												<?php endif ; ?>
											</tr>
											</thead>
											<tfoot>
											<tr>
												<th>Jama ID</th>
												<th>Name</th>
												<th>Test Status</th>
												<th>Release</th>
												<?php if($type == "reqtest") : ?>
												<th>Team</th>
												<?php endif ; ?>
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
var title = "<?php echo $typename; ?>";

Highcharts.chart('container', {
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
					chart.setTitle({ text: "Test of <?php echo $typename; ?>" }),
					title = point.series.name;
				} else if (i >= 2) {
					chart.setTitle({ text: "Test of <?php echo $typename; ?>"});
				}
				chart.applyDrilldown();
			},
			drillup: function(e) {
				i--;
				if (i == 1) {
					chart.setTitle({ text: "Test of <?php echo $typename; ?>" });
				} else {
					chart.setTitle({ text: "Test of <?php echo $typename; ?>" }),
					i = 0;
				}
			}
		}
	},
	credits: false,
	title: {
		text: "Test of <?php echo $typename; ?>"
	},
	subtitle: {
		text: ''
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
			text: "<?php echo $typename; ?>"
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
					echo "'team': '".$_GET["team"]."'";
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
			<?php if($type == "reqtest") : ?>
			{'data' : 'team'},
			<?php endif ; ?>
		],
		"columnDefs": [
			{
				"render": function ( data, type, row ) {
					return "<a href='<?php echo $config["jama"]."#/items/"?>"+row['id']+"<?php echo "?projectId=$jamaid" ?>' target='blank' title='Open <?php echo $type ?> in Jama'>"+row['uniqueid']+"</a>";
				},
				"targets": [0]
			},
			<?php if ($type == "features" or $type == "feattest") : ?>
			{
				"render": function ( data, type, row ) {
					return "<a href='relation?id="+row['id']+"&type=feat' target='blank' title='Show relationship for this feature'>"+row['name']+"</a>";
				},
				"targets": [1]
			},
			<?php elseif ($type == "requirements" or $type == "reqtest") : ?>
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
		],
		rowCallback: function(row, data, index){
			status = data['status'].charAt(0).toUpperCase() + data['status'].slice(1);
			if (status == 'Failed'){
				$(row).find('td:contains('+status+')').css({'background': 'red', 'color': 'white'});
			} else if (status == 'Passed'){
				$(row).find('td:contains('+status+')').css({'background': 'green', 'color': 'white'});
			} else if (status == 'Incomplete testing'){
				$(row).find('td:contains('+status+')').css({'background': 'grey', 'color': 'white'});
			} else if (status == 'Missing test coverage'){
				$(row).find('td:contains('+status+')').css({'background': 'yellow', 'color': 'black'});
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