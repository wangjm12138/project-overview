<?php
$config = parse_ini_file("config.ini");
$path = $config["path"];

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

// Select all active projects
$results = $file_db->query("SELECT * FROM projects WHERE status='Active' ORDER by name ASC");
// Close file db connection
$file_db = null;
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>Active projects</title>
	<link rel="shortcut icon" href="/css/favicon.ico">
    <!--     Fonts and icons     -->
    <link href="/css/font-awesome.min.css" rel="stylesheet">
    <link href='/css/fonts/material-icons.css' rel='stylesheet' type='text/css'>

	<meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />

    <!-- Bootstrap core CSS     -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="/js/jquery.min.js"></script>
  <script src="/js/bootstrap.min.js"></script>

    <!--  Material Dashboard CSS    -->
    <link href="css/material-dashboard.css" rel="stylesheet"/>
	<style>
	input[type=text] {
		width: 130px;
		-webkit-transition: width 0.4s ease-in-out;
		transition: width 0.4s ease-in-out;
	}

	/* When the input field gets focus, change its width to 100% */
	input[type=text]:focus {
		width: 100%;
	}
	</style>
	<script>
	$(document).ready(function(){
	  $("#input").on("keyup", function() {
		var value = $(this).val().toLowerCase();
		$("#table tr").filter(function() {
		  $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
		});
	  });
	});
	</script>
</head>
<body>
	<div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header" data-background-color="grey">
                            <h2 class="title">Active projects</h2>
                            <p class="category">Data extracted from Jira & Jama</p>
                        </div>
                        <div class="card-content table-responsive">
							<center><input type="text" class="form-control" id="input" name="search" placeholder="Search for project.."></center>
                            <table class="table table-hover table-bordered table-striped">
                                <thead>
								<tr>
									<th><h4>Project name</h4></th>
									<th><h4>Project details</h4></th>
									<th><h4>Open project in Jira</h4></th>
									<th><h4>Open project in Jama</h4></th>
								</tr>
                                </thead>
                                <tbody id="table">
								<?php foreach ($results as $row) {
									echo "
									<tr>
										<td>".$row['name']."</td>
										";
									echo ($row['keyy']) ? "<td><a href=".$row['keyy'].">Project details</a></td>
										<td><a href=".$config["jira"]."/projects/". $row['keyy'] ."/issues/>Link to Jira</a></td>
										" : "<td><a href=".$row['jama'].">Project details</a></td>
										<td>Project not found in Jira</td>" ;
									echo ($row['jama'] == 0) ? "<td>Project not found in Jama</td>" : "<td><a href=".$config["jama"]."#/projects/".$row['jama'].">Link to Jama</a></td>";
									echo "
									</tr>";
								}?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>