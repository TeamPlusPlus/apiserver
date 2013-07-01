<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file endpoints/404.php - error thrower for API
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/api
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Include core
require('../core.php');

// Get URI
$uri = explode('/', rtrim(substr($_SERVER['REQUEST_URI'], 1), '/'));

// What type of 404?
switch($_GET['type']) {
	case 'file':
		// Episode file
		$file = pathinfo($uri[1]);
		
		// Check if episode is published
		if(!file_exists(ROOT_CACHE . '/' . $uri[0] . '/' . $file['filename'] . '.json')) {
			$response = new APIResponse('404_episode', 404, array(
				'project' => $uri[0],
				'episode' => $file['filename']
			));
			$response->send();
		} else {
			$response = new APIResponse('file', 404, array(
				'project' => $uri[0],
				'episode' => $file['filename'],
				'extension' => $file['extension']
			));
			$response->send();
		}
		break;
	case 'endpoint':
	default:
		if(isset($uri[1]) && !preg_match('{^[0-9]+$}', $uri[1])) {
			// No episode and no "all"
			$response = new APIResponse('default', 501);
			$response->send();
		} else if(isset($uri[1]) && is_dir(ROOT_FILES . '/' . $uri[0])) {
			// Episode
			
			// Check if project is set
			if($uri[0] == '') {
				$response = new APIResponse('default', 501);
				$response->send();
			} else {
				$response = new APIResponse('episode', 404, array(
					'project' => $uri[0],
					'episode' => $uri[1]
				));
				$response->send();
			}
		} else {
			// Project
			
			// Check if project name is valid
			if(!preg_match('{^[a-z]+$}', $uri[0])) {
				$response = new APIResponse('default', 501);
				$response->send();
			} else {
				$response = new APIResponse('project', 404, array(
					'project' => $uri[0]
				));
				$response->send();
			}
		}
}