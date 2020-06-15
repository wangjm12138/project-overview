<?php
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
// Get all projects
$allprojects = $file_db->query("SELECT * FROM projects where status='Active' ORDER by name ASC");
?>
    <nav class="navbar">
        <div class="container-fluid" onmousedown="return false">
            <div class="navbar-header">
                <a href="javascript:void(0);" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar-collapse" aria-expanded="false"></a>
                <a href="javascript:void(0);" class="bars"></a>
                <a class="navbar-brand" href="#"><?php echo ucwords(strtolower($projectname)); ?>, project details</a>
            </div>
            <div class="collapse navbar-collapse" id="navbar-collapse">
                <ul class="nav navbar-nav navbar-right">
                    <!-- Projects -->
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button">
                            <b>Selected Project:</b> <?php echo ucwords(strtolower($projectname)); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="header">ACTIVE PROJECTS</li>
                            <li class="body">
                                <ul class="menu">
								<?php foreach($allprojects as $row) {
										echo "
										<li>
										";
										if ($row["keyy"]) {
											echo "	<a href='/".$row['keyy']."'>";
										} else {
											echo "	<a href='/".$row['jama']."'>"; 
										}
										echo "
												<div class='menu-info'>
													<h4>".$row['name']."</h4>
												</div>
											</a>
										</li>";
								}?>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <!-- #END# PROJECTS -->
                </ul>
            </div>
        </div>
    </nav>