<?php
$usedcolors = [];
function getColor() {
	$color = ('#' . dechex(rand(0x323232, 0xe5e5e5))); //Generate random number
	if (!isset($usedcolors[$color])){
		$color = ('#' . dechex(rand(0x323232, 0xe5e5e5))); //Generate random number
	} else {
		$usedcolors[$color];
	}
	return $color;
}

function getColorJama($status) {
	$color = getColor(); //Generate random color
	switch ($status) {
		case 'Approved':
		case 'Closed':
		case 'Closed Bug':
		case 'Passed':
			$color = "green";
			break;
		case 'Rejected':
		case 'Scheduled':
		case 'In Review Bug':
		case 'Incomplete Testing':
			$color = "grey";
			break;
		case 'Proposal':
		case 'Blocked':
		case 'Missing Test Coverage':
			$color = "yellow";
			break;
		case 'Draft':
		case 'Open':
		case 'Open Bug':
		case 'Failed':
			$color = "red";
			break;
		case 'Textual Change':
		case 'Resolved':
		case 'Resolved Bug':
			$color = "purple";
			break;
		case 'Info Pending':
		case 'In Testing':
		case 'In Testing Bug':
			$color = "orange";
			break;
		case 'Completed':
		case 'Not Run':
		case 'Not Scheduled':
			$color = "blue";
			break;
		case 'In Progress':
		case 'In Progress Bug':
			$color = "turquoise";
			break;
		case 'Reopened':
		case 'Reopened Bug':
			$color = "hotpink";
			break;
		case 'Closed Task':
			$color = "darkgreen";
			break;
		case 'In Review Task':
			$color = "lightgrey";
			break;
		case 'Open Task':
			$color = "darkred";
			break;
		case 'Resolved Task':
			$color = "brown";
			break;
		case 'In Testing Task':
			$color = "darkorange";
			break;
		case 'In Progress Task':
			$color = "darkturquoise";
			break;
		case 'Reopened Task':
			$color = "lightpink";
			break;
	}
	return $color;   
}
?>