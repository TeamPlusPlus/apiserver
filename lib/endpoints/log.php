<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file endpoints/log.php - request logger
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/api
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Include core
require('../core.php');

// Get the request data
$project = $_GET['project'];
$episode = $_GET['episode'];
$extension = $_GET['extension'];

// Check if the episode is even published yet
if(!file_exists(ROOT_CACHE . '/' . $project . '/' . $episode . '.json')) {
	$response = new APIResponse('404_episode', 404, array(
		'project' => $project,
		'episode' => $episode
	));
	$response->send();
}

// Create the project directory if not existing
if(!is_dir(ROOT_LOGS . '/' . $project)) {
	mkdir(ROOT_LOGS . '/' . $project);
}

// Open the existing log file
$filename = ROOT_LOGS . '/' . $project . '/' . $episode . '.json';

if(file_exists($filename)) {
	$data = json_decode(file_get_contents($filename), true);
} else {
	// New file
	$data = array(
		'types' => array(),
		'times' => array(),
		'countries' => array(),
		'ips' => array()
	);
}

// Build an IP hash of the user
$ipHash = substr(md5($_SERVER['REMOTE_ADDR']), 0, 10);
if(!isset($data['ips'][$ipHash][$extension])) {
	// Add the new information
	
	// Type
	if(isset($data['types'][$extension])) {
		// There was already a visit
		$data['types'][$extension]++;
	} else {
		// No visit of that type
		$data['types'][$extension] = array();
		$data['types'][$extension] = 1;
	}
	
	// Visit time
	$hour = date('H') . 'h';
	if(isset($data['times'][$hour])) {
		// There was already a visit
		$data['times'][$hour]++;
	} else {
		// No visit at that hour
		$data['times'][$hour] = 1;
	}
	
	// Country
	require_once(ROOT_THIRDPARTY . '/Net_GeoIP/Net/GeoIP.php');
	$geoip = Net_GeoIP::getInstance(ROOT_THIRDPARTY . '/Net_GeoIP/data/GeoIP.dat');
	try {
		$country = $geoip->lookupCountryName($_SERVER['REMOTE_ADDR']);
		if($country == '') $country = 'Unknown';
	} catch(Exception $e) {
		$country = 'Unknown';	
	}
	
	if(isset($data['countries'][$country])) {
		// There was already a visit
		$data['countries'][$country]++;
	} else {
		// No visit from that country
		$data['countries'][$country] = 1;
	}
}

// Log hashed IP
if(isset($data['ips'][$ipHash][$extension])) {
	$data['ips'][$ipHash][$extension]++;
} else if(isset($data['ips'][$ipHash])) {
	$data['ips'][$ipHash][$extension] = 1;
} else {
	$data['ips'][$ipHash] = array($extension => 1);
}

// Check if the project folder exists
if(!is_dir(ROOT_LOGS . '/' . $project)) {
	mkdir(ROOT_LOGS . '/' . $project);
}

// Save the log file
file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));

// Redirect to destination
http_response_code(301);
header('Location: http://' . $_SERVER['HTTP_HOST'] . '/files/' . $project . '/' . $episode . '.' . $extension);
