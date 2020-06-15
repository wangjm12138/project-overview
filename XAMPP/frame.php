<?php
//ini_set('display_errors', 'On');
$project = $_GET["project"];
$type = $_GET["type"];
$data = $output = [];
$index = 0;
require_once('getcolor.php');
$order = array("Rejected" => 1, "Approved" => 2, "Previously Approved" => 3, "Textual Change" => 4, "Proposal" => 5, "Info Pending" => 6, "Ready For Estimation" => 7, "Draft" => 8, "Closed" => 9, "Completed" => 10, "Resolved" => 11, "In Testing" => 12, "In Progress" => 13, "Reopened" => 14, "Open" => 15, "Pending Platform (Only In Project)" => 16, "On-Hold" => 17, "Approved For Implementation (Pl)" => 18, "Committed" => 19, "Committed In Pi" => 20, "Committed In Pi (Pl)" => 21, "Submitted (Pl)" => 22, "Stretch In Pi (Pl)" => 23, "Approved For Implementation" => 24, "Proposal (Pl)" => 25, "In Review" => 26);

if(isset($_GET["name"])) { 
	$testplan = $_GET["name"];
}

if ($type == "designspec" || $type== "testapproval" || $type == "features" || $type == "requirements" || $type == "defects" || $type == "userstories" || $type == "changes") {
	//Get data
	if (isset($_GET["team"]) OR isset($_GET["rel"])) {
		$opt1 = false;
		$query = "";
		if (isset($_GET["team"])) {
			$team = explode(",", $_GET["team"]);
			$query .= "WHERE team IN ('" . implode("','", $team) . "') " ;
			$opt1 = false;
		}
		if (isset($_GET["rel"])) {
			$rel = explode("," , $_GET["rel"]);
			if ($opt1) {
				$query .= "WHERE rel IN ('" . implode("','", $rel) . "') ";
			} else {
				$query .= "AND rel IN ('" . implode("','", $rel) . "') ";
			}
		}
		$results = $file_db->query("SELECT status, date, sum(count) as count FROM $type $query GROUP BY status, date ORDER BY date ASC, status ASC");
	} else {
		$results = $file_db->query("SELECT status, date, sum(count) as count FROM $type GROUP BY status, date ORDER BY date ASC, status ASC");
	}
	if ($results != null) {
		foreach ($results as $row) {
			$sqldata[ucwords($row["status"])][$row["date"]] = $row["count"];
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
	
	//Sort the array by $order
	uksort($sqldata, function($a, $b) use($order) {
		return $order[$a] > $order[$b];
	});
	
	//Main Plot
	foreach ($sqldata as $st => $status) {
		$pdata = [];
		$color = getColorJama($st);
		foreach ($status as $date => $count) {
			$pdata[] = "{x: moment('$date').valueOf(), y: $count, drilldown: '".str_replace(" ", "", $st)."'}";
		}
		$index += 1;
		$output[] = "{name:'$st', color: '$color', data: [".implode( ', ', $pdata )."], index: $index}";
	}
} elseif ($type == "testmaturity") {
	if (isset($_GET["name"])){
		//Get data for plot
		$results = $file_db->query("SELECT status, executionDate as date, count(*) as count FROM testcases WHERE testplan_id= '$testplan' GROUP BY status, executionDate ORDER by executionDate ASC");
		foreach ($results as $row) {
			$sqldata[$row["status"]][$row["date"]] = $row["count"];
		}
		array_multisort(array_values($sqldata), SORT_ASC, array_keys($sqldata), SORT_ASC, $sqldata);
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
} elseif ($type == "testplan") {
	if (isset($_GET["name"])){
		//Get data for plot
		$results = $file_db->query("SELECT * FROM tests WHERE testplan_name like '$testplan' OR testplan_id= '$testplan' ORDER BY date ASC");
		
		$order = array("PASSED" => 1, "FAILED" => 2, "BLOCKED" => 3, "NOT_RUN" => 4, "SCHEDULED" => 5, "NOT_SCHEDULED" => 6, "INPROGRESS" => 7);
		foreach ($results as $row) {
			if (!isset($sqldata[$row["status"]][$row["date"]])) {
				$sqldata[$row["status"]][$row["date"]] = $row["count"];
			} else {
				$sqldata[$row["status"]][$row["date"]] += $row["count"];
			}
		}
		//Sorts the array after the given key in $order[]
		uksort($sqldata, function($a, $b) use($order) {
			return $order[$a] > $order[$b];
		});
		foreach (array_reverse($sqldata) as $st => $status) {
			$pdata = [];
			$stfixed = ucwords(strtolower(str_replace("_", " ", $st)));
			$color = getColorJama($stfixed);
			foreach ($status as $dt => $date) {
				$pdata[] = "{x: moment('$dt').valueOf(), y: $date}";
			}
			$index += 1;
			$output[] = "{ name: '$stfixed', color: '$color', data: [".implode( ', ', $pdata )."], index: $index }";
		}
		//Get test plan id
		$testplan_id = $file_db->query("SELECT testplan_id FROM tests WHERE testplan_name like '$testplan' OR testplan_id= '$testplan' LIMIT 1");
		foreach ($testplan_id as $row) {
			$testplan_id = $row["testplan_id"];
		}
	}
} elseif ($type == "coverage") {

	
	if (isset($_GET["rel"]) OR isset($_GET["status"])) {
		if (isset($_GET["status"])) {
			buildSelect("status");
		}
		if (isset($_GET["rel"])) {
			buildSelect("rel");
		}
		$results = $file_db->query("SELECT * FROM $type WHERE $select ORDER BY status ASC, date ASC");
		
		if (empty($results)) {
			echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
			exit();
		}
		$temp = $file_db->query("SELECT MAX(date) as date from $type WHERE $select")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
	} else {
		$results = $file_db->query("SELECT team, sum(covered) as covered, sum(expected) as expected, date FROM coverage group by date, team ORDER by date ASC");
		$temp = $file_db->query("SELECT MAX(date) as date from $type")->fetch_assoc()["date"];
		$lastupdated = date('j. F, Y', strtotime($temp));
	}
	
	foreach ($results as $row) {
		//Main data
		$sqldata[$row["team"]][$row["date"]] = ["covered" => $row["covered"], "expected" => $row["expected"]];
	}
	
	//Get the current coverage
	$expected = $file_db->query("SELECT sum(expected) as sum FROM coverage GROUP BY date ORDER by date DESC LIMIT 1")->fetch_assoc()["sum"];
	$covered = $file_db->query("SELECT sum(covered) as sum FROM coverage GROUP BY date ORDER by date DESC LIMIT 1")->fetch_assoc()["sum"];
	$currentcov = $covered . "/" . $expected;
	
	array_multisort(array_keys($sqldata), SORT_ASC, array_values($sqldata), SORT_ASC, $sqldata);
	
	foreach ($sqldata as $tm => $team) {
		$pdata = [];
		foreach ($team as $dt => $date) {
			if ($date["expected"] != 0) {
				$y = $date["covered"] / $date["expected"] * 100;
			} else {
				$y = 0;
			}
			if ($y > 100) {
				$y = 100;
			}
			$pdata[] = "{x: moment('$dt').valueOf(), actual: " .$date["covered"]. ", expected: " .$date["expected"]. ", y: $y}";
		}
		$color = getColor();
		$output[] = "{ name: '$tm', data: [".implode( ', ', $pdata )."], color: '$color'}";
	}
	
	if (empty($output)) {
		echo "<script type='text/javascript'>alert('No valid data found with the provided filter(s)');history.go(-1);</script>";
		exit();
	}
}
?>

<head>
	<script src="/js/jquery.min.js"></script>
	<script src="/js/moment.min.js"></script>
	<script src="/js/highcharts.js"></script>
	<title><?php echo $title; echo " - "; echo $type; ?></title>
</head>
<body>
	<?php if ($type == "designspec" || $type== "testapproval" || $type == "features" || $type == "requirements" || $type == "defects" || $type == "userstories") : ?>
	<a href="." target="_parent">
		<div id="container" style="width: 100%; height: 240px; margin: 0 auto"></div>
	</a>
    <script>
	Highcharts.setOptions({
		global: {
			useUTC: false
		}
	});
	var chart = Highcharts.chart('container', {
		chart: {
			type: 'area'
		},  
		credits: {
		  enabled: false
		},
		title:{
			text: null
		},
		xAxis: {
			type: 'datetime',
			tickmarkPlacement: 'on',
			ordinal: false,
			title: {
				enabled: false
			},
		},
		yAxis: {
			allowDecimals: false,
			reversedStacks: false,
			title: {
				text: null
			}
		},
		tooltip: {
			split: false,
			shared: true,
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
		series: [<?php echo implode(', ', $output); ?>]
	});
    </script>
	<?php elseif ($type == "coverage") : ?>
	<a href="." target="_parenta">
		<div id="container" style="width: 100%; height: 240px; margin: 0 auto"></div>
	</a>
	<script>
	Highcharts.setOptions({
		global: {
			useUTC: false
		}
	});
	var chart = Highcharts.chart('container', {
		  chart: {
			type: 'area'
		  },  
		  credits: {
			enabled: false
		  },
		  title: {
			text: null
		  },
		  tooltip: {
			shared: true,
			pointFormat: '{series.name}: <b>{point.actual} / {point.expected}</b><br/>',
			xDateFormat: '%A, %b %e, %Y'
		  },
		  xAxis: {
			type: 'datetime',
		  },
		  yAxis: {
			allowDecimals: false,
			ordinal: false,
			title: {
				text: null
			},
			labels: {
				formatter: function() {
					return this.value+"%";
				}
			},
			min: 0,
			max: 100
		  },
		  plotOptions: {
			series: {
			  marker: {
				enabled: false
			  }
			}
		  },
		  series: [<?php echo implode(', ', $output); ?>]
	});
	</script>
	<?php elseif ($type == "testplan") : ?>
	<a href="../<?php echo $testplan_id;?>" target="_parenta">
		<div id="container" style="width: 100%; height: 240px; margin: 0 auto"></div>
	</a>
    <script>
	Highcharts.setOptions({
		global: {
			useUTC: false
		}
	});
	var chart = Highcharts.chart('container', {
		chart: {
			type: 'area'
		},  
		credits: {
			enabled: false
		},
		title:{
			text: null
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
			title: {
				text: null
			}
		},
		tooltip: {
			shared: true,
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
		series: [<?php echo implode(', ', $output); ?>]
	});	
    </script>
	<?php elseif ($type == "testmaturity") : ?>
	<a href="." target="_parenta">
		<div id="container" style="width: 100%; height: 240px; margin: 0 auto"></div>
	</a>
	<script>
	Highcharts.setOptions({
		global: {
			useUTC: false
		}
	});
	var chart = Highcharts.chart('container', {
		chart: {
			type: 'column',
			zoomType: 'x'
		},  
		credits: {
			enabled: false
		},
		title:{
			text: null
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
			shared: true,
			xDateFormat: '%A, %b %e, %Y'
		},
		plotOptions: {
			column: {
				stacking: 'normal',
				dataLabels: {
					enabled: true
				}
			}
		},
		series: [<?php echo implode(', ', $output); ?>]
	});
	</script>
	<?php endif; ?>
</body>