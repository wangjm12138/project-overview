<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
$path = $config["path"];
$select = "";

$project = $_GET["project"];

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
		$select .= "$type='".$_GET[$type]."' ";
	} else {
		$select .= "AND $type='".$_GET[$type]."' ";
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
	<script src="/js//material.min.js"></script>
	<!-- Plugins -->
    <script src="/js/bootstrap-select.min.js"></script>
    <script src="/js/jquery.slimscroll.min.js"></script>
    <script src="/js/waves.min.js"></script>
    <script src="/js/jquery.countTo.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
    <script src="/js/index.js"></script>
	
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
						<div class="header">
						<h4>Issues in <?php echo $projectname; ?></h4>
						</div>
						<div class="body">
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
	</section>
</body>
</html>