<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file endpoints/task.php - task runner
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/apiserver
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Include core
require('../core.php');

// Check if the task exists
$filename = ROOT_TASKS . '/' . $_GET['task'] . '.php';
if(file_exists($filename)) {
	// Include it
	require $filename;
	
	try {
		// Create a class instance of the task
		$className = ucfirst($_GET['task'] . 'Task');
		$task = new $className;
		
		// Check for correct authentication
		if($task->auth == array() || (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW']) && array_key_exists($_SERVER['PHP_AUTH_USER'], $task->auth) && $task->auth[$_SERVER['PHP_AUTH_USER']] == md5($_SERVER['PHP_AUTH_PW']))) {
			// Access
			$user = isset($_SERVER['PHP_AUTH_USER'])? $_SERVER['PHP_AUTH_USER'] : '';
			
			// Parse additional arguments
			$args = ($_GET['args'])? explode(':', $_GET['args']) : array();
			
			// Let the task check if everything is OK
			if(is_string($error = $task->check($args, $user))) {
				$response = new APIResponse('task', 400, array(
					'endpoints' => json_encode($task->endpoints),
					'task' => $_GET['task'],
					'desc' => 'There was an error running the task: \'' . $error . '\'.'
				));
				$response->send();
			}
			
			// Run the task
			$response = $task->run($args, $user);
			$response->send();
		} else {
			// No access
			$response = new APIResponse('task', 403, array(
				'endpoints' => json_encode($task->endpoints),
				'task' => $_GET['task'],
				'desc' => 'You are not allowed to run that task.'
			));
			$response->send();
		}
	} catch(Exception $e) {
		$response = new APIResponse();
		$response->exception($e);
	}
} else {
	// No task with that name
	
	// Check for possible input errors
	if(APIResponse::uri() != 'task' && 'task/' . $_GET['task'] != APIResponse::uri()) {
		// There were some illegal characters in there
		$response = new APIResponse('default', 501);
		$response->send();
	} else if($_GET['task'] == '') {
		// List all tasks
		$tasks = array();
		foreach(scandir(ROOT_TASKS) as $task) {
			// Skip folders
			if(is_dir(ROOT_TASKS . '/' . $task)) continue;
			
			// Get task name
			$taskName = pathinfo($task, PATHINFO_FILENAME);
			
			$tasks[$taskName] = 'http://' . $_SERVER['HTTP_HOST'] . '/task/' . $taskName;
		}
		
		$response = new APIResponse('tasks', 200, array(
			'tasks' => json_encode($tasks)
		));
		$response->send();
	} else {
		$response = new APIResponse('task', 404, array(
			'endpoints' => '{}',
			'task' => $_GET['task']
		));
		$response->send();
	}
}

/**
 * Task
 * 
 * The base class for all tasks
 * 
 * @endpoint /task/$task/$infos
 */
class Task {
	
	// Authentication
	public $auth = array();
	
	// Endpoints
	public $endpoints = array('base' => '');
	
	/**
	 * Check if everything is OK
	 * 
	 * @param stdClass     $args    Additional arguments
	 * @param string       $user    The user authenticated
	 *
	 * @return bool/string $success If everything is OK (string with error message)
	 */
	public function check($args, $user) {
		// All is OK if there's no check() method in the inheriting class.
		return true;
	}
	
	/**
	 * Run the task
	 * 
	 * @param array        $args  Additional arguments
	 * @param string       $user  The user authenticated
	 *
	 * @return APIResponse        The return value to the API requester
	 */
	public function run($args, $user) {
		// Error, task is invalid (inheriting class is not overwriting this method)
		return new APIResponse('task', 501, array(
			'task' => '',
			'desc' => 'There was an internal error: The run() method of this task was not defined.'
		));
	}
}
