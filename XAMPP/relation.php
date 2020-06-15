<?php
//ini_set('display_errors', 'On');
$config = parse_ini_file("config.ini");
$path = $config["path"];
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

// Check if project exists by using its jira or jama id 
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

if (file_exists('C:\xampp\mysql\data\\'.$jamaid)) { //check if the project has a database before connecting to avoid creating a new database 
	// Create connection
	$dbname = $jamaid;
	$file_db = new mysqli($servername, $username, $password, $dbname);

	if ($file_db->connect_error) {
		die("Connection failed: " . $file_db->connect_error);
	}

} else {
	echo "No data found";
	exit();
}

if(isset($_GET["id"])) { 
	$id = $_GET["id"];
}
if(isset($_GET["type"])) { 
	$type = $_GET["type"];
}
$treeData = "";
$pass = ["Completed", "Closed", "Approved", "PASSED", "Resolved"];
if ($type == "feat") {
	$results = $file_db->query("SELECT * FROM allfeatures WHERE id=$id");
	foreach ($results as $feat) {
		//Save feature id, name, status, downstreams
		$title = $feat["uniqueid"] . " - " . $feat["name"];
		$name = str_replace('"', "'", $feat["name"]);
		$requirement = $file_db->query("SELECT * FROM allrequirements WHERE upstream like '%$id%' ORDER BY name ASC");
		/**
		$complete = 1;
		foreach ($requirement as $req) {
			if (!in_array($req["status"], $pass)) {
				$complete = 0;
				break;
			}
		}
		**/
		$treeData = '"id": "'.$feat["id"].'", "uniqueid": "'.$feat["uniqueid"].'", "name": "'.$name.'", "status": "'.$feat["status"].'", "type": "Feature", "children": [';
		if (!empty($requirement)) {
			foreach ($requirement as $req) {
				//Save each requirement id, name, status, release, downstream
				$name = str_replace('"', "'", $req["name"]);
				$testcase = $file_db->query("SELECT * FROM testcases WHERE upstream like '%".$req["id"]."%' AND executionDate = (select max(executionDate) from testcases where upstream like '%".$req["id"]."%') ORDER BY name ASC");
				$treeData .= '{"id": "'.$req["id"].'", "uniqueid": "'.$req["uniqueid"].'", "name": "'.$name.'", "status": "'.$req["status"].'", "type": "Requirement", "parent": '.$id.', "children": [';
				if (!empty($testcase)) {
					foreach ($testcase as $tc) {
						//Save each TC id, name, status, release, downstream
						$name = str_replace('"', "'", $tc["name"]);
						$status = ucwords(strtolower(str_replace("_", " ", $tc["status"])));
						$defect = $file_db->query("SELECT * FROM alldefects WHERE upstream like '%".$tc["id"]."%' ORDER BY name ASC");
						$treeData .= '{"id": "'.$tc["id"].'", "uniqueid": "'.$tc["uniqueid"].'", "name": "'.$name.'", "status": "'.$status.'", "type": "Test case", "parent": '.$req["id"].', "children": [';
						if (!empty($defect)) {
							foreach ($defect as $def) {
								//Save each defect id, name, status
								$name = str_replace('"', "'", $def["name"]);
								$treeData .= '{"id": "'.$def["id"].'", "uniqueid": "'.$def["uniqueid"].'", "name": "'.$name.'", "status": "'.$def["status"].'", "type": "Defect", "parent": '.$tc["id"].'}, ';
							}
						}
						$treeData .= ']}, ';
					}
				}
				$treeData .= ']}, ';
			}
		}
		$treeData .= '],';
	}
} elseif ($type == "req") {
	$results = $file_db->query("SELECT * FROM allrequirements WHERE id = $id");
	foreach ($results as $req) {
		//Save each requirement id, name, status, release, downstream
		$title = $req["uniqueid"] . " - " . $req["name"];
		$name = str_replace('"', "'", $req["name"]);
		$treeData .= '"id": "'.$req["id"].'", "uniqueid": "'.$req["uniqueid"].'", "name": "'.$name.'", "status": "'.$req["status"].'", "type": "Requirement", "parent": '.$id.', "children": [';
		$testcase = $file_db->query("SELECT * FROM testcases WHERE upstream like '%".$req["id"]."%' AND executionDate = (select max(executionDate) from testcases where upstream like '%".$req["id"]."%') ORDER BY name ASC");
		if (!empty($testcase)) {
			foreach ($testcase as $tc) {
				//Save each TC id, name, status, release, downstream
				$name = str_replace('"', "'", $tc["name"]);
				$status = ucwords(strtolower(str_replace("_", " ", $tc["status"])));
				$treeData .= '{"id": "'.$tc["id"].'", "uniqueid": "'.$tc["uniqueid"].'", "name": "'.$name.'", "status": "'.$status.'", "type": "Test case", "parent": '.$req["id"].', "children": [';
				$defect = $file_db->query("SELECT * FROM alldefects WHERE upstream like '%".$tc["id"]."%' ORDER BY name ASC");
				if (!empty($defect)) {
					foreach ($defect as $def) {
						//Save each defect id, name, status
						$name = str_replace('"', "'", $def["name"]);
						$treeData .= '{"id": "'.$def["id"].'", "uniqueid": "'.$def["uniqueid"].'", "name": "'.$name.'", "status": "'.$def["status"].'", "type": "Defect", "parent": '.$tc["id"].'}, ';
					}
				}
				$treeData .= ']}, ';
			}
		}
		$treeData .= '], ';
	}	
} elseif ($type == "tc") {
	$results = $file_db->query("SELECT * FROM testcases WHERE id=$id AND executionDate = (select max(executionDate) from testcases where id=$id)");
	foreach ($results as $tc) {
		//Save each TC id, name, status, release, downstream
		$name = str_replace('"', "'", $tc["name"]);
		$status = ucwords(strtolower(str_replace("_", " ", $tc["status"])));
		$treeData .= '"id": "'.$tc["id"].'", "uniqueid": "'.$tc["uniqueid"].'", "name": "'.$name.'", "status": "'.$status.'", "type": "Test case", "children": [';
		$defect = $file_db->query("SELECT * FROM alldefects WHERE upstream like '%".$tc["id"]."%' ORDER BY name ASC");
		if (!empty($defect)) {
			foreach ($defect as $def) {
				//Save each defect id, name, status
				$name = str_replace('"', "'", $def["name"]);
				$treeData .= '{"id": "'.$def["id"].'", "uniqueid": "'.$def["uniqueid"].'", "name": "'.$name.'", "status": "'.$def["status"].'", "type": "Defect", "parent": '.$tc["id"].'}, ';
			}
		}
		$treeData .= '], ';
	}
}


?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />

    <title><?php echo $projectname . " - " . $title; ?></title>

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
    <!-- Custom Css -->
    <link href="/css/style.css" rel="stylesheet">
    <!-- AdminBSB Themes. -->
    <link href="/css/themes/theme-blue.min.css" rel="stylesheet" />
	
	<style>
	.dropdown-menu{
	max-height: 400px;
	overflow-y: auto;
	}
	.node text {
	  font: 10px sans-serif;
	}
	.link {
	  fill: none;
	  stroke: #ccc;
	  stroke-width: 2px;
	}
	div.tooltip {
		position: absolute;
		text-align: center;
		width: 150px;
		height: 70px;
		padding: 10px;
		font: 12px sans-serif;
		background: #ffff99;
		border: solid 1px #aaa;
		border-radius: 10px;
		pointer-events: none;
	}
	</style>

    <!--   Core JS Files   -->
    <script src="/js/jquery.min.js"></script>
	<script src="/js/bootstrap.min.js"></script>
	<script src="/js/material.min.js"></script>
	<!-- Plugins -->
    <script src="/js/jquery.slimscroll.min.js"></script>
    <script src="/js/waves.min.js"></script>
    <!-- Custom Js -->
    <script src="/js/admin.js"></script>
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
				<div class="col-12">
					<div class="card">
						<div class="header">
							<?php echo $title ?>
						</div>
						<div class="body">
							<!-- load the d3.js library -->	
							<script src="https://d3js.org/d3.v5.min.js"></script>
							<center>
							<button type="button" class="btn btn-warning waves-effect" onclick="collapseAll()">Collapse All</button>
							<button type="button" class="btn btn-success waves-effect" onclick="expandAll()">Expand All</button>
							</center>
							<div id="tree"></div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>
</body>
<script>
var treeData = {
    <?php echo $treeData; ?>
};
// Set the dimensions and margins of the diagram
var margin = {
        top: 20,
        right: 90,
        bottom: 30,
        left: 90
    },
    width = 970 - margin.left - margin.right,
    height = 700 - margin.top - margin.bottom;

// append the svg object to the body of the page
// appends a 'group' element to 'svg'
// moves the 'group' element to the top left margin
var svg = d3.select("#tree").append("svg")
    .attr("width", '100%')
    .attr("height", '700px')
    .attr('viewBox', '0 0 ' + Math.min(width, height) + ' ' + Math.min(width, height))
    .call(d3.zoom().on("zoom", function() {
        svg.attr("transform", d3.event.transform)
    }))
    .append("g")
    .attr("transform", "translate(" + Math.min(width, height) / 2 + "," + Math.min(width, height) / 2 + ")");

// Add tooltip div
var div = d3.select("body").append("div")
    .attr("class", "tooltip")
    .style("opacity", 1e-6);

var i = 0,
    duration = 750,
    root;

// declares a tree layout and assigns the size
var tree = d3.tree()
    .nodeSize([30, 30])
    .separation(function(a, b) {
        return a.parrent == b.parrent ? 1 : 1
    });

// Assigns parent, children, height, depth
root = d3.hierarchy(treeData, function(d) {
    return d.children;
});
root.x0 = height / 2;
root.y0 = 50;

// Collapse after the second level
if (root.children) {
    root.children.forEach(collapse)
}

update(root);

/*
 * Collapses the node d and all the children nodes of d
 * @param {node} d
 */
function collapse(d) {
    if (d.children) {
        d._children = d.children;
        d._children.forEach(collapse);
        d.children = null;
    }
}

/*
 * Collapses the node in the tree
 */
function collapseAll() {
    root.children.forEach(collapse);
    update(root);
}

/*
 * Expands the node d and all the children nodes of d
 * @param {node} d
 */
function expand(d) {
    if (d._children) {
        d.children = d._children;
        d._children = null;
    }
    if (d.children) {
        d.children.forEach(expand);
    }

}

/*
 * Expands all the nodes in the tree
 */
function expandAll() {
    root.children.forEach(expand);
    update(root);
}

// Creates a curved (diagonal) path from parent to the child nodes
function diagonal(s, d) {
    if (s != null && d != null) {
        return "M " + s.y + " " + s.x +
            " C " + ((s.y + d.y) / 2) + " " + s.x + "," +
            ((s.y + d.y) / 2) + " " + d.x + "," +
            " " + d.y + " " + d.x;
    }
}

// Toggle children on click.
function click(d) {
    if (d.children) {
        d._children = d.children;
        d.children = null;
    } else {
        d.children = d._children;
        d._children = null;
    }
    update(d);
}

function update(source) {
    // Assigns the x and y position for the nodes
    var treeData = tree(root);

    // Compute the new tree layout.
    var nodes = treeData.descendants(),
        links = treeData.descendants().slice(1);

    // Normalize for fixed-depth.
    nodes.forEach(function(d) {
        d.y = d.depth * 360
    });

    // ****************** Nodes section ***************************
    // Update the nodes...
    var node = svg.selectAll('g.node')
        .data(nodes, function(d) {
            return d.id || (d.id = ++i);
        });

    // Enter any new modes at the parent's previous position.
    var nodeEnter = node.enter().append('g')
        .attr('class', 'node')
        .attr("transform", function(d) {
            return "translate(" + source.y0 + "," + source.x0 + ")";
        })
        .on("click", click) //added mouseover function
        .on("mouseover", mouseover)
        .on("mousemove", function(d) {
            mousemove(d);
        })
        .on("mouseout", mouseout);

    function mouseover() {
        div.transition()
            .duration(300)
            .style("opacity", 1);
    }

    function mousemove(d) {
        div
            .html(d.data.uniqueid + "<br>" + "Status: <b>" + d.data.status + "</b><br>" + "Type: " + d.data.type + "<br>")
            .style("left", (d3.event.pageX) + "px")
            .style("top", (d3.event.pageY) + "px");
    }

    function mouseout() {
        div.transition()
            .duration(300)
            .style("opacity", 1e-6);
    }

    // Add Circle for the nodes
    nodeEnter.append('circle')
        .attr('class', 'node')
        .attr('r', 1e-6)
        .text(function(d) {
            return d.data.verdict
        });

    // Add labels for the nodes
    nodeEnter.append('text')
        .attr("class", "name")
        .attr("x", 10)
        .attr("dy", ".35em")
        .text(function(d) {
            return d.data.name;
        }); //.call(wrap, 200);

    // UPDATE
    var nodeUpdate = nodeEnter.merge(node);

    // Transition to the proper position for the node
    nodeUpdate.transition()
        .duration(duration)
        .attr("transform", function(d) {
            return "translate(" + d.y + "," + d.x + ")";
        });

    // Update the node attributes and style
    nodeUpdate.select('circle.node')
        .attr('r', 10)
        .style("fill", function(d) {
            if (d.data.status == "Approved" || d.data.status == "Closed" || d.data.status == "Passed") return "green";
            if (d.data.status == "Rejected") return "grey";
            if (d.data.status == "Proposal" || d.data.status == "Blocked") return "yellow";
            if (d.data.status == "Draft" || d.data.status == "Open" || d.data.status == "Failed") return "red";
            if (d.data.status == "Textual Change" || d.data.status == "Resolved") return "purple";
            if (d.data.status == "Info Pending" || d.data.status == "Inprogress" || d.data.status == "In Progress") return "orange";
            if (d.data.status == "Completed" || d.data.status == "In Testing" || d.data.status == "Not Run" || d.data.status == "Not Scheduled") return "blue";
            if (d.data.status == "Reopened") return "grey";
        })
        .attr('cursor', 'pointer');

    // Remove any exiting nodes
    var nodeExit = node.exit().transition()
        .duration(duration)
        .attr("transform", function(d) {
            return "translate(" + source.y + "," + source.x + ")";
        })
        .remove();

    // On exit reduce the node circles size to 0
    nodeExit.select('circle')
        .attr('r', 1e-6);

    // On exit reduce the opacity of text labels
    nodeExit.select('text')
        .style('fill-opacity', 1e-6);

    // ****************** links section ***************************

    // Update the links...
    var link = svg.selectAll('path.link')
        .data(links, function(d) {
            return d.id;
        });

    // Enter any new links at the parent's previous position.
    var linkEnter = link.enter().insert('path', "g")
        .attr("class", "link")
        .attr('d', function(d) {
            var o = {
                x: source.x,
                y: source.y
            }
            return diagonal(o, o)
        });

    // UPDATE
    var linkUpdate = linkEnter.merge(link);

    // Transition back to the parent element position
    linkUpdate.transition()
        .duration(duration)
        .attr('d', function(d) {
            return diagonal(d, d.parent)
        });

    // Remove any exiting links
    var linkExit = link.exit().transition()
        .duration(duration)
        .attr('d', function(d) {
            var o = {
                x: source.x,
                y: source.y
            }
            return diagonal(o, o)
        })
        .remove();

    // Store the old positions for transition.
    nodes.forEach(function(d) {
        d.x0 = d.x;
        d.y0 = d.y;
    });
}
</script>
</html>