#!/usr/bin/php
<?php
/* Render screenshots from Sitemap

	Script designed to be run at the OSX command line to grab the sitemap.xml from
	a provided website and use Google's Puppeteer to create image snapshots of each page 
	listed in the site map.
	
	Will automatically create subfolders based on the URL and reference.
	
	Use: ./renderFromSitemap.php -s[site URL] -r[reference (optional)] -a[alternative remote sitemap file name (optional)] -l[local sitemap file name (optional)] -t[number of concurrent capture threads (optional)]
	
	-s[siteURL] is required - the script will fail without it
	-r[reference] is optional - will be replaced with a timestamp if not supplied
	-a[alternative remote sitemap file name] is optional - the script will default to /sitemap.xml if not supplied
	-l[local sitemap file name] is optional - if present this will be used instead of the remote site map
	
	e.g. ./renderFromSitemap.php -shttps://dash.marketing/ -r2.5.2 -ldash.sitemap.xml -4

	Would use the local sitemap dash.sitemap.xml to capture screenshots of 4 pages at a time 
	from https://dash.marketing/ and place them in a created folder at ./images/dash.marketing/2.5.2 
	
	
	Written by Josh Curtis for Dash Media

	17 May 2017 - initial creation
	24 May 2017 - added multithreading of screen shot calls
	25 Oct 2017 - added the option to have a local sitemap after running into problems with Wordpress forbidding sitemap access
	6 Nov 2017 - made over to use Google's Puppeteer instead of the previous phantomJS and better handle command line variables
*/


//-- Configuration --//

// Default time zone
date_default_timezone_set("Australia/Adelaide");

// How long should the script wait for a return from the screen shot (in milliseconds - 10s = 10000). Note that this won't kill the Chrome process - just the chance of a valid return from the process calling it.
$threadTimeOut = 90000;

// How many simultaneous threads should be the default?
$defaultThreads = 6;

// How many simultaneous threads should be the most allowed?
$maxThreads = 8;

//-- End Configuration --//


$options = getopt("s:r::l::t::");

if(!isset($options["s"])) {
	exit ("\n\nFAILED: please check usage:\n\n./renderFromSitemap.php -s[site URL]\n\ne.g. ./renderFromSitemap.php -shttps://dashmedia.marketing/\n\n\n\n\n");
}


// Initialise variables
$rootPath = getcwd();
$siteURL = $options["s"];
$reference = isset($options["r"]) ? $options["r"] : date("Ymd-His");
$sitemapLocation = isset($options["a"]) ? $options["a"] : "sitemap.xml";
$sitemapLocal = isset($options["l"]) ? $options["l"] : false;
$numberOfThreads = isset($options["t"]) ? intval($options["t"]) : $defaultThreads;

// QC on number of threads to check it's not too low or too high
if($numberOfThreads <= 0) {
	echo "\n\nNot a valid number of threads.\nSetting number of threads to the default of $defaultThreads.\n\n";
	$numberOfThreads = $defaultThreads;
}
if($numberOfThreads > $maxThreads) {
	echo "\n\n$numberOfThreads threads is above the hardcoded limit of $maxThreads.\nSetting number of threads to the default of $defaultThreads.\nThe maximum number of threads can be changed in renderFromSitemap.php in the 'configuration' section.\n\n";
	$numberOfThreads = $defaultThreads;	
}


// Turn down error reporting and look to get the output showing immediately
error_reporting(E_ERROR);
ob_implicit_flush(true);
// Initialise output
echo "\n\n";


// Initialise queue manager
require('rollingcurlx.class.php');


// Add a trailing slash to the URL if it doesn't have one
if(substr($siteURL,-1) != "/") {
	$siteURL .= "/";
}

// Get the sitemap - if it fails exit with an error
if($sitemapLocal != "") {
	$siteMap = file_get_contents($sitemapLocal)
		or exit("\n\nFAILED: couldn't retrieve the local site map file at $sitemapLocal\nPlease check the file exists and try again.\n\n\n\n\n");
} else {
	$siteMap = file_get_contents($siteURL . $sitemapLocation)
		or exit("\n\nFAILED: couldn't retrieve site map at $siteURL$sitemapLocation\nPlease check the URL exists and try again.\n\n\n\n\n");
}

// We have all arguments and a sitemap - let's roll

// Check/create folder structure for image output
$projectPath = "images/" . str_replace("/","",substr($siteURL,strpos($siteURL,"://")+3));
if(!is_dir($projectPath."/".$reference)) {
	mkdir($projectPath."/".$reference,0777,TRUE);
}
if(!is_dir($projectPath."/".$reference."/code")) {
	mkdir($projectPath."/".$reference."/code",0777,TRUE);
}

// Convert the XML to an object
$siteMapObject = simplexml_load_string(trim($siteMap));

// Output reference variables
//$output = shell_exec('ls -lart');
$output = "\n\n" .
	"Script:       " . $argv[0] . "\n" .
	"Root path:    " . $rootPath . "\n\n" .
	"Site URL:     " . $siteURL . "\n" .
	"Reference:   " . $reference . "\n" .
	"Project path: " . $projectPath . "\n\n";

echo $output;

// Prepare the log file
$logFile = $projectPath . "/" . $reference . "/" . str_replace("/","",substr($siteURL,strpos($siteURL,"://")+3)). "-" . $reference . ".log.txt";
file_put_contents($logFile, $output, FILE_APPEND);

// Loop through the URLs and make the shell call to run the phantomJS render

// Initialise tracking variables
$count = 0;
$totalURLs = count($siteMapObject->url);
$startTime = time();

// Prepare the multithreader
$RCX = new RollingCurlX($numberOfThreads);
$RCX->setTimeout($threadTimeOut);

foreach($siteMapObject->url as $url) {
	$count++;
	
	$urlCall = "http://localhost/chromeCaller.php";
	$post_data = [
		'urlLocation' => urlencode($url->loc),
		'siteURL' => urlencode($siteURL),
		'count' => $count,
		'totalURLs' => $totalURLs,
		'projectPath' => $projectPath,
		'reference' => $reference,
		'startTime' => $startTime,
		'timeLimit' => $threadTimeOut
	];
	$options = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FAILONERROR => true,
		CURLOPT_RETURNTRANSFER => true,
	];

	$RCX->addRequest($urlCall, $post_data, 'returningOfficer', $user_data, $options, $headers);

}

// Prepare to count the returns
$returnCounter = 0;
$total = count($siteMapObject->url);

// Prepare output start
$initialisationStatement = "Started ordering screenshots. ";
if($total < $numberOfThreads) {
	$initialisationStatement .= "Total of $total shots requested.\n\n";
} else {
	$initialisationStatement .= "Total of $total shots requested $numberOfThreads at a time.\n\n";
}

file_put_contents($logFile, $initialisationStatement);
echo $initialisationStatement . "\n";
flush();

$RCX->execute();

function returningOfficer($response, $url, $request_info, $user_data, $time) {
/*	echo "Response    : $response\n";
	echo "URL         : $url\n";
	echo "Request info: <pre>";
	print_r($request_info);
	echo "\n";
	echo "User data   : $user_data\n";
	echo "Time        : $time\n\n"; */
	
	global $returnCounter,$totalURLs,$startTime,$logFile;
	
	$returnCounter++;

	// Perform some time reporting based on whether or not it's the last run
	$currentTime = time();
	$runTime = $currentTime-$startTime;

	$averageTime = $runTime/$returnCounter;
	$forecastRunTime = ($runTime/$returnCounter)*($totalURLs-$returnCounter);
	$forecastFinishTime = time() + $forecastRunTime;

	// Perform some time/status reporting based on whether or not it's the last run
	$output = "[$returnCounter of $totalURLs] ";
	$output .= $response;

	if($returnCounter == $totalURLs) {
		$output .= "\n\nAll done - total run time for $totalURLs pages was " . convertSecondsToHMS($runTime) . " with an average convert time of " . convertSecondsToHMS(round($runTime/$totalURLs)) . ".";
	} else {
		$output .= "Currently running for " . convertSecondsToHMS($runTime) . ". Averaging " . convertSecondsToHMS(round($averageTime)) . " per page. At this rate the site render is expected to finish in " . convertSecondsToHMS(round($forecastRunTime)) . " (at " . date("g:i:sa", $forecastFinishTime) . ")\n\n";
	}

	file_put_contents($logFile, $response.$output, FILE_APPEND);
	
	echo $output;
	flush();
}

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


//echo "Site map:\n" . $siteMap;

// Finalise output
echo "\n\n\n\n\n";
?>