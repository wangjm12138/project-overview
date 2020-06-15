<?php 
$config = parse_ini_file("config.ini");
$path = $config["path"];
/**************************************
* Create databases and                *
* open connections                    *
**************************************/

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

$jira = true;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	$name = $_POST["name"];
	$keyy = $_POST["keyy"];
	if ($keyy == "") {
		$jira = false;
		$keyy = $_POST["old_jama"];
	} else {
		$keyy = $_POST["keyy"];
	}
	$jama = $_POST["jama"];
	
	if (isset($_POST["submit"]) and $_POST["submit"] == "add") {
		$status = $_POST["status"];
		$query = "INSERT INTO projects (keyy, name, status, jama) VALUES (?,?,?,?)";
		$stmt = $file_db->prepare($query);
		$stmt->bind_param('sssi', $keyy, $name, $status, $jama);
		$stmt->execute();
		
	} else {
		if (isset($_POST["visble"])) {
			if ($_POST["visble"] == 1) {
				if ($jira) {
					$query = "UPDATE projects SET name=?, jama=?, status='Active' WHERE keyy=?";
				} else {
					$query = "UPDATE projects SET name=?, jama=?, status='Active' WHERE jama=?";
				}
			} else {
				if ($jira) {
					$query = "UPDATE projects SET name=?, jama=?, status='Inactive' WHERE keyy=?";
				} else {
					$query = "UPDATE projects SET name=?, jama=?, status='Inactive' WHERE jama=?";
				}
			}
		} else {
			$query = "UPDATE projects SET name=?, jama=? WHERE keyy=?";
		}
		$stmt = $file_db->prepare($query);
		$stmt->bind_param('sis', $name, $jama, $keyy);
		$stmt->execute();
	}
echo "Updated ".$_POST['name'];
exit();
}

$result = $file_db->query('SELECT * FROM projects ORDER by name ASC');
// Close file db connection
$file_db->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <title>All projects</title>
    <meta content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' name='viewport' />
    <meta name="viewport" content="width=device-width" />
	<link rel="shortcut icon" href="/css/favicon.ico">
    <!-- Bootstrap core CSS -->
	<link href="/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Css -->
    <link href="/css/style.css" rel="stylesheet">
    <!-- Fonts and icons -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700,300|Material+Icons" rel="stylesheet" type="text/css">
    <!-- Core JS Files -->
    <script src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script defer src="https://code.getmdl.io/1.3.0/material.min.js"></script>
<script>
function update (value){  
    $.ajax({
	   type: "POST",
	   url: "#",
	   data: $("#"+value+"").serialize(), // serializes the form's elements.
	   success: function(data)
	   {
		   alert(data); // show response from the php script.
	   }
    });
}
</script>
</head>
<body>
	<div class="container-fluid">
		<div class="row clearfix">
			<div class="col-md-12">
				<div class="card">
					<div class="body">
						<!-- Nav tabs -->
						<ul class="nav nav-tabs tab-nav-right" role="tablist">
							<li class="active">
								<a href="#update" data-toggle="tab">
									<i class="material-icons">ic_build</i> Update Overview
								</a>
							</li>
							<li>
								<a href="#add" data-toggle="tab">
									<i class="material-icons">ic_note_add</i> Add Project
								</a>
							</li>
						</ul>
						<!-- Tab panes -->
						<div class="tab-content">
							<div role="tabpanel" class="tab-pane fade in active" id="update">
								<div class="table-responsive">
								<table class="table">
									<span id="savednote"></span><br />
									<thead>
									<tr>
										<th>Project name</th>
										<th>Project key</th>
										<th>Jama</th>
										<th>Show/hide</th>
										<th></th>
									</tr>
									</thead>
									<tbody>
<?php foreach ($result as $row) : ?>
										<tr>
										<form action="#" method="POST" id="<?php if ($row["keyy"] != "") echo $row["keyy"]; else echo $row["jama"] ?>">
											<td><input type="text" name="name" value="<?php echo $row['name']; ?>"></td>
											<td><input type="text" name="keyy" value="<?php echo $row['keyy']; ?>" readonly></td>
											<td><input type="number" name="jama" value="<?php echo $row['jama']; ?>" id="jama"></td>
											<input type="hidden" name="old_jama" value="<?php echo $row['jama']; ?>" id="old_jama">
											<td><?php echo $row["status"] == "Active"?" <input type='radio' id='".$row["keyy"]."_show' name='visble' value=1 checked> <label for='".$row["keyy"]."_show'>Show</label> <input type='radio' id='".$row["keyy"]."_hide' name='visble' value=0 > <label for='".$row["keyy"]."_hide'>Hide</label>":"<input type='radio' id='".$row["keyy"]."_show' name='visble' value=1> <label for='".$row["keyy"]."_show'>Show</label> <input type='radio' id='".$row["keyy"]."_hide' name='visble' value=0 checked> <label for='".$row["keyy"]."_hide'>Hide</label>";?></td>
											<td><button name="submit" type="button" class="btn btn-primary" value="Update" onclick="update('<?php if ($row["keyy"] != "") echo $row["keyy"]; else echo $row["jama"] ?>')"><i class="material-icons">update</i><span>Update</span></button></td>
										</form>
										</tr>
<?php endforeach; ?>
									</tbody>
								</table>
								</div>
							</div>
							<div role="tabpanel" class="tab-pane fade" id="add">
								<form method="post" action="#" id="addProject">
									<div class="row clearfix">
										<div class="col-md-3">
											<p>
												<b>Project name</b>
											</p>
											<div class="input-group input-group-lg">
												<div class="form-line">
													<input type="text" name="name" class="form-control" placeholder="eg. Rio first wave">
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<p>
												<b>Jira Project Key</b>
											</p>
											<div class="input-group input-group-lg">
												<div class="form-line">
													<input type="text" name="keyy" class="form-control" placeholder="eg. RIO1">
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<p>
												<b>Jama Project Key</b>
											</p>
											<div class="input-group input-group-lg">
												<div class="form-line">
													<input type="number" name="jama" class="form-control" placeholder="eg. 20250">
												</div>
											</div>
										</div>
										<div class="col-md-3">
											<p>
												<b>Status</b>
											</p>
											<select name="status" class="form-control show-tick">
												<option value="Active">Active</option>
												<option value="Inactive">Inactive</option>
											</select>
										</div>
									</div>
									<center>
										<td><button name="submit" value="add" class="btn btn-default"><i class="material-icons">library_add</i> Add</button></td>
									</center>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>