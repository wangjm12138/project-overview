<?php
//ini_set('display_errors', 'On');
$status = $_GET["status"];
$date = $_GET["date"];
//Get testplan name
$stmt = $file_db->query("SELECT testplan_name FROM testcases WHERE testplan_id= '$testplan' LIMIT 1");
foreach ($stmt as $row) {
	$testname = $row["testplan_name"];
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
					<?php 
					if (isset($_GET["rel"])) {
						echo "'rel': '".$_GET["rel"]."',";
					}
					if (isset($_GET["status"])) {
						echo "'status': '".$_GET["status"]."',";
					}
					if (isset($_GET["date"])) {
						echo "'date': '".$_GET["date"]."',";
					}
					?>
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
						status = row["status"].toLowerCase()
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
			"iDisplayLength": 100,
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
				<div class="col-md-14">
					<div class="card">
						<div class="header">
						<h4><?php echo ucwords(strtolower(str_replace("_", " ", $status))) . " tests in " . $testname; ?></h4>
						</div>
						<div class="body">
							<div class="table-responsive">
								<table id="rawtable" class="table table-striped table-bordered wrap" width="100%">
									<thead>
									<tr>
										<th>Testcase ID</th>
										<th>Testcase name</th>
										<th>Status</th>
										<th>Release</th>
										<th>Testgroup</th>
										<th>Testcycle</th>
										<th>Execution date</th>
									</tr>
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
	</section>
</body>
</html>