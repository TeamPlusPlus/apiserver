<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file endpoints/live.php - live stream redirect
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/apiserver
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Include core
require('../core.php');

// Check if a project is given
if(!trim($_GET['project'])) {
	// No, this is an invalid request
	$response = new APIResponse('default');
	$response->send();
}

// Check if the project is currently live
$cacheIsFresh = false;
$data = array();
if(file_exists(ROOT_CACHE . '/' . $_GET['project'] . '/live.ser')) {
	// There is a cached response
	$serialized = file_get_contents(ROOT_CACHE . '/' . $_GET['project'] . '/live.ser');
	$data = unserialize($serialized);
	
	// Check if the cache is not older than 20 seconds
	$cacheIsFresh = time() < $data['time'] + 20;
}

if(!$cacheIsFresh) {
	// No cached response, request from the Icecast server
	$headers = @get_headers('http://live.plusp.lu:8000/' . $_GET['project'], 1);
	
	// If the Icecast server is down, this will not be an array
	if(!is_array($headers)) $headers = array();
	
	// Build an array with all important information
	$available = isset($headers[0]) && strpos($headers[0], '200 OK') !== false;
	$matches = array();
	if(isset($headers['icy-name'])) preg_match('/#(?<episode>[0-9]+?) \((?<topic>.*?)\)/', $headers['icy-name'], $matches);
	
	$data = array(
		'time' => time(),
		'available' => $available,
		'episode' => (isset($matches['episode']))? (int)$matches['episode'] : null,
		'topic' => (isset($matches['topic']))? $matches['topic'] : null
	);
	
	// Write to cache
	file_put_contents(ROOT_CACHE . '/' . $_GET['project'] . '/live.ser', serialize($data));
}

// Response according to live stream availability
if($data['available']) {
	$response = new APIResponse('live', 200, array(
		'live' => 'true',
		'url' => '"http://live.plusp.lu/' . $_GET['project'] . '"',
		'project' => $_GET['project'],
		'episode' => ($data['episode'])? $data['episode'] : 'null',
		'topic' => ($data['topic'])? '"' . $data['topic'] . '"' : 'null',
	));
	$response->send();
} else {
	$response = new APIResponse('live', 200, array(
		'live' => 'false',
		'url' => 'false',
		'project' => $_GET['project'],
		'episode' => 'null',
		'topic' => 'null'
	));
	$response->send();
}
