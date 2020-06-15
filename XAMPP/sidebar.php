<?php
$path = $config["path"];
$project = $_GET["project"];
$allprojects = [];
$reqspec = $testdef = $testexc = $defects = "";

if ($jamaid == 0) {
	$reqspec = "<li>
							<a href='#'>Features</a>
						</li>
						<li>
							<a href='#'>Design Specifications</a>
						</li>
						<li>
							<a href='#'>Requirements</a>
						</li>
						<li>
							<a href='#'>User Stories</a>
						</li>
						<li>
							<a href='#'>Change Requests</a>
						</li>";

	$testdef = "<li>
							<a href='#'>Test Coverage of Requirements</a>
						</li>
						<li>
							<a href='#'>Test Case Approval</a>
						</li>";

	$testexc = "<li>
							<a href='#'>Test Plans</a>
						</li>
						<li>
							<a href='#'>Test Maturity</a>
						</li>
						<li>
							<a href='#'>Test of Features</a>
						</li>
						<li>
							<a href='#'>Test of Requirements</a>
						</li>";
	if (isset($jira) and ($bug == true)) {
		$defects = "<li><a href='/$project/plot?type=Bug' target='_self'>Bug Status (Jira)</a></li> \n <li><a href='/$project/defectTrend' target='_self'>Bug Trend (Jira)</a></li>";
	} else {
		$defects = "<li><a href='#' target='_self'>Bug Status (Jira)</a></li>";
	};
	
} else {
	// Create (connect to) SQLite database in file
	if (file_exists('C:\xampp\mysql\data\\'.$jamaid)) {
		$servername = "localhost";
		$username = "root";
		$password = "jabra2020";
		$dbname = $jamaid;

		// Create connection
		$file_db = new mysqli($servername, $username, $password, $dbname);
		// Check connection
		if ($file_db->connect_error) {
			die("Connection failed: " . $file_db->connect_error);
		}
		
		$results = $file_db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = SCHEMA() AND TABLE_NAME NOT LIKE '%all%'");
		$data = array();
		foreach ($results as $row) {
			$data[] = $row["TABLE_NAME"];
		}
		
		if (in_array("features", $data)) {
			$reqspec .= "<li>
								<a href='/$project/jama/features' title='Show trend for all Features for this project'  target='_self'>Features</a>
							</li>";
		} else {
			$reqspec .= "<li>
								<a href='#'>Features</a>
							</li>";
		}
		if (in_array("designspec", $data)) {
			$reqspec .= "
							<li>
								<a href='/$project/jama/designspec' title='Show trend for all Design Specifications for this project'  target='_self'>Design Specifications</a>
							</li>";
		} else {
			$reqspec .= "
							<li>
								<a href='#'>Design Specifications</a>
							</li>";

		}
		if (in_array("requirements", $data)) {
			$reqspec .= "
							<li>
								<a href='/$project/jama/requirements' title='Show trend for all Requirements for this project'  target='_self'>Requirements</a>
							</li>";
		} else {
			$reqspec .= "
							<li>
								<a href='#'>Requirements</a>
							</li>";
		}
		if (in_array("userstories", $data)) {
			$reqspec .= "
							<li>
								<a href='/$project/jama/userstories' title='Show trend for all User Stories for this project'  target='_self'>User Stories</a>
							</li>";
		} else {
			$reqspec .= "
							<li>
								<a href='#'>User Stories</a>
							</li>";
		}
		if (in_array("changes", $data)) {
			$reqspec .= "
							<li>
								<a href='/$project/jama/changes' title='Show trend for all change requests for this project'  target='_self'>Change Requests</a>
							</li>";
		} else {
			$reqspec .= "
							<li>
								<a href='#'>Change Requests</a>
							</li>";
		}
		if (in_array("coverage", $data)) {
			$testdef .= "<li>
								<a href='/$project/jama/coverage' target='_self'>Test Coverage of Requirements</a>
							</li>";
		} else {
			$testdef .= "<li>
								<a href='#'>Test Coverage of Requirements</a>
							</li>";
		}
		if (in_array("testapproval", $data)) {
			$testdef .= "
							<li>
								<a href='/$project/jama/testapproval' target='_self'>Test Case Approval</a>
							</li>";
		} else {
			$testdef .= "
							<li>
								<a href='#'>Test Case Approval</a>
							</li>";
		}
		if (in_array("tests", $data)) {
			$testexc .= "<li>
								<a href='/$project/jama/testplan' target='_self'>Test Plans</a>
							</li>";
		} else {
			$testexc .= "<li>
								<a href='#'>Test Plans</a>
							</li>";
		}
		if (in_array("testcases", $data)) {
			$testexc .= "<li>
								<a href='/$project/jama/testmaturity' target='_self'>Test Maturity</a>
							</li>";
		} else {
			$testexc .= "
							<li>
								<a href='#'>Test Maturity</a>
							</li>";
		}
		if (in_array("feattest", $data)) {
			$testexc .= "<li>
								<a href='/$project/jama/feattest' target='_self'>Test of Features</a>
							</li>";
		} else {
			$testexc .= "
							<li>
								<a href='#'>Test of Features</a>
							</li>";
		}
		if (in_array("reqtest", $data)) {
			$testexc .= "<li>
								<a href='/$project/jama/reqtest' target='_self'>Test of Requirements</a>
							</li>";
		} else {
			$testexc .= "
							<li>
								<a href='#'>Test of Requirements</a>
							</li>";
		}
		if ($jira) {
			$defects = "<li>
								<a href='/$project/plot?type=Bug' target='_self'>Bug Status (Jira)</a>
							</li>
							<li>
								<a href='/$project/defectTrend' target='_self'>Bug Trend (Jira)</a>
							</li>";
		}
		if (in_array("defects", $data)) {
			$defects .= "<li>
								<a href='/$project/jama/defects' target='_self'>Bug Status (Jama)</a>
							</li>";
		} else {
			$defects = "<li>
								<a href='#'>Bug Status (Jira)</a>
							</li>
							<li>
								<a href='#'>Bug Status (Jama)</a>
							</li>
							<li>
								<a href='#'>Bug Trend (Jira)</a>
							</li>";
		}
	} else {
		$reqspec = "<li>
								<a href='#'>Features</a>
							</li>
							<li>
								<a href='#'>Design Specifications</a>
							</li>
							<li>
								<a href='#'>Requirements</a>
							</li>
							<li>
								<a href='#'>User Stories</a>
							</li>
							<li>
								<a href='#'>Change Requests</a>
							</li>";

		$testdef = "<li>
								<a href='#'>Test Coverage of Requirements</a>
							</li>
							<li>
								<a href='#'>Test Case Approval</a>
							</li>";

		$testexc = "<li>
								<a href='#'>Test Plans</a>
							</li>
							<li>
								<a href='#'>Test Maturity</a>
							</li>
							<li>
								<a href='#'>Test of Features</a>
							</li>
							<li>
								<a href='#'>Test of Requirements</a>
							</li>";
		$defects = "<li>
								<a href='#'>Error Trend</a>
							</li>";
	}
}
?>
    <section>
        <aside id="leftsidebar" class="sidebar">
            <!-- Menu -->
            <div class="menu" onmousedown="return false">
                <ul class="list">
                    <li class="header">MAIN NAVIGATION</li>
					<?php if (isset($_GET["platform"])) : ?>
					<a href="/<?php echo $project; ?>">
						<i class="material-icons">home</i>
						<span>Dashboard</span>
					</a>
					<?php else : ?>
                    <li class="active">
                        <a href="/<?php echo $project; ?>">
                            <i class="material-icons">home</i>
                            <span>Dashboard</span>
                        </a>
                    </li>
					<?php endif; ?>
                    <li>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "features" or $_GET["type"] == "designspec" or $_GET["type"] == "requirements" or $_GET["type"] == "userstories" or $_GET["type"] == "changes")) : ?>
                    <li class="active">
					<?php endif; ?>
                        <a href="javascript:void(0);" class="menu-toggle">
                            <i class="material-icons">build</i>
                            <span>Requirements & spec</span>
                        </a>
                        <ul class="ml-menu">
                            <?php echo $reqspec; ?>
                        </ul>

					<?php if (isset($_GET["type"]) and ($_GET["type"] == "features" or $_GET["type"] == "designspec" or $_GET["type"] == "requirements" or $_GET["type"] == "userstories" or $_GET["type"] == "changes")) : ?>
                    </li>
					<?php endif; ?>
					</li>
                    <li>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "coverage" or $_GET["type"] == "testapproval")) : ?>
                    <li class="active">
					<?php endif; ?>
                        <a href="javascript:void(0);" class="menu-toggle">
                            <i class="material-icons">content_paste</i>
                            <span>Test Definitions</span>
                        </a>
                        <ul class="ml-menu">
                            <?php echo $testdef; ?>
                        </ul>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "coverage" or $_GET["type"] == "testapproval")) : ?>
                    </li>
					<?php endif; ?>
                    </li>
                    <li>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "testplan" or $_GET["type"] == "testmaturity")) : ?>
                    <li class="active">
					<?php endif; ?>
                        <a href="javascript:void(0);" class="menu-toggle">
                            <i class="material-icons">assessment</i>
                            <span>Test Executions</span>
                        </a>
                        <ul class="ml-menu">
                            <?php echo $testexc; ?>
                        </ul>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "testplan" or $_GET["type"] == "testmaturity")) : ?>
                    </li>
					<?php endif; ?>
                    </li>
                    <li>
					<?php if (isset($_GET["type"]) and ($_GET["type"] == "defects" or $_GET["type"] == "Bug")) : ?>
                    <li class="active">
					<?php endif; ?>
                        <a href="javascript:void(0);" class="menu-toggle">
                            <i class="material-icons">bug_report</i>
                            <span>Defects</span>
                        </a>
                        <ul class="ml-menu">
                            <?php echo $defects; ?>
                        </ul>
                    </li>
					<li class="header">LINKS</li>
                    <li>
                        <a href="/">
                            <span>Projects overview</span>
                        </a>
                    </li>
<?php if ($jira) : ?>
                    <li>
                        <a href="<?php echo $config["jira"] ."/projects/". $project; ?>/issues">
                            <span>Jira</span>
                        </a>
                    </li>
<?php endif; ?>
<?php if ($jamaid) : ?>
                    <li>
                        <a href="<?php echo $config["jama"]."#/projects/".$jamaid; ?>/dashboard">
                            <span>Jama</span>
                        </a>
                    </li>
<?php endif; ?>
                </ul>
            </div>
            <!-- #Menu -->
            <!-- Footer -->
            <div class="legal">
                <div class="copyright">
                    <a>GN AUDIO</a>
                </div>
            </div>
            <!-- #Footer -->
        </aside>
    </section>