<?php
// Makes the call to run the snapshot. Done from a separate PHP so we can multithread via curl_multi.

// Prepare variables for run
$urlLocation = urldecode($_REQUEST['urlLocation']);
$siteURL = urldecode($_REQUEST['siteURL']);
$count = $_REQUEST['count'];
$projectPath = $_REQUEST['projectPath'];
$reference = $_REQUEST['reference'];
$startTime = $_REQUEST['startTime'];
$totalURLs = $_REQUEST['totalURLs'];
$timeLimit = $_REQUEST['timeLimit'];
$logFile = $_REQUEST['logFile'];

$timeLimit = intval($timeLimit/1000);
$changeTime = set_time_limit($timeLimit);

//file_put_contents($logFile, $count . " - chromeCaller loaded asking for URL " . $siteURL . "\n\n", FILE_APPEND);

// Functions required
function convertSecondsToHMS($seconds) {
	$hours = 0;
	$minutes = 0;
	
	while ($seconds > 3600) {
		$hours++;
		$seconds = $seconds-3600;
	}
	
	while ($seconds > 60) {
		$minutes++;
		$seconds = $seconds - 60;
	}
	
	$time = "";
	if ($hours > 0) {
		$time .= $hours . "h ";
	}
	if ($minutes > 0) {
		$time .= $minutes . "m ";
	}
	$time .= $seconds . "s";
	
	return $time;
}

	
// Run the phantomjs call on a delay to let all assets come through
$imageFile = substr($urlLocation,strpos($urlLocation,"://")+3);
$imageFile = str_replace(array("/","(",")"),array("_","\(","\)"),$imageFile);
if(substr($imageFile,0,1) == "_") {
	$imageFile = substr($imageFile,1);
}
$imageFile = str_replace("(", "", $imageFile);
$imageFile = str_replace(")", "", $imageFile);

// Set a list of ignorable URL extensions - either call Chrome or report the exception
$ignoreFileExtensions = array(".doc",".ics",".pdf",".jpg",".mp3",".mp4",".ppt","pptx",".rss",".txt",".xls",".xml",".zip");
if(in_array(substr($urlLocation,-4),$ignoreFileExtensions)) {
	$output = "Current page: " . $urlLocation . "\n" . "URL is one of the following types: " . str_replace(".","",implode(",",$ignoreFileExtensions)) . " - no snapshot taking place\n";
} else if (file_exists($projectPath . "/" . $reference . "/" . $imageFile . ".png")) {
	$output = "File exists: " . $projectPath . "/" . $reference . "/" . $imageFile . ".png\nNo snapshot will be taken\n";
} else {
	// Make the puppeteer call

	// Following comment for debug purposes only
	//	$output = "File doesn't exist: " . $projectPath . "/" . $reference . "/" . $imageFile . ".png" . "\n" . "Snapshot SHOULD be taken\n";
	// Set output to the URL (for debugging)
	//$output = $urlLocation;
	
	$output = shell_exec("node puppeteer-screenshots.js -w 1920 -h 1920  --url=" . str_replace(array("(",")"),array("\(","\)"),$urlLocation) . " -p=" . $projectPath . "/" . $reference . "/ -f=" . $imageFile);
	
}

echo $output;
?>