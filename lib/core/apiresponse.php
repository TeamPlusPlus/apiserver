<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file apiresponse.php - API result JSON builder
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/apiserver
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

/**
 * APIResponse
 * 
 * API result JSON builder
 *
 * @endpoint /
 * @endpoint /$project
 * @endpoint /$project/$episode
 * @endpoint /$project/all
 * @endpoint /task/$task/$infos
 */
class APIResponse {
	
	// HTTP status code
	public $status = 200;
	
	// Result body
	public $body = '';
	
	// Result type
	public $type = 'application/json';
	
	// The URI to the file
	public $uri = '';
	
	/**
	 * Constructor
	 * 
	 * @param string $template The template to use
	 * @param int    $status   HTTP status code
	 * @param array  $data     Data to fill in
	 * @param string $uri      The URI to the file
	 */
	public function __construct($template = null, $status = 200, $data = array(), $path = null) {
		if($template == null) return;
		
		$this->uri = (is_string($path))? $path : static::uri();
		
		// Get path of template to use
		try {
			$templateFile = $this->template($template, $status);
		} catch(Exception $e) {
			$this->exception($e);
		}
		
		// Open file
		$templateFileContents = file_get_contents($templateFile);
		
		// Parse it and add data
		$this->status = $status;
		try {
			$this->body = $this->parse($templateFileContents, $status, $data);
		} catch(Exception $e) {
			$this->exception($e);
		}
	}
	
	/**
	 * Template
	 *
	 * Get path of template to use
	 * 
	 * @param string $template The template to use
	 * @param int    $status   HTTP status code
	 *
	 * @return string          The path to the template
	 */
	private function template($template, $status) {
		// Possible files
		$paths = array(
			$status . '_' . $template . '.json',
			$template . '.json',
			'default.json'
		);
		
		foreach ($paths as $path) {
			if(file_exists(ROOT_TEMPLATES . '/' . $path)) return ROOT_TEMPLATES . '/' . $path;
		}
		
		// No template at all
		throw new Exception('Fatal error: Fallback template was not found.');
	}

	/**
	 * Parse
	 *
	 * Parse a template and insert snippets
	 * 
	 * @param string $template The template JSON to use
	 * @param int    $status   HTTP status code
	 * @param array  $data     Data to fill in
	 *
	 * @return string          The resulting JSON
	 */
	private function parse($template, $status, $data) {
		// Add basic variables
		$data = array_merge(array(
			'status' => array(
				'code' => $status,
				'desc' => $this->status($status)
			),
			'uri' => $this->uri,
			'host' => $_SERVER['HTTP_HOST']
		), $data);
		
		// Include snippets
		$template = preg_replace_callback('/§{(.+?)}/', function($matches) {
			$filename = ROOT_SNIPPETS . '/' . $matches[1] . '.json';
			if(file_exists($filename)) {
				// Include the snippet
				return file_get_contents($filename);
			} else {
				// Throw an error
				throw new Exception('Fatal error: Snippet "' . $matches[1] . '" was not found');
			}
		}, $template);
		
		// Parse variables
		$template = $this->parseVars($template, $data);
		
		// Parse JSON to check validity
		$data = json_decode($template, true);
		if($data === null) {
			// Failed parsing JSON
			throw new Exception('Fatal error: Error creating valid JSON');
		}
		
		// Return JSON again
		return json_encode($data, JSON_NUMERIC_CHECK | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	
	/**
	 * Parse vars
	 *
	 * Insert variables
	 *
	 * @param string $template The template JSON to use
	 * @param array  $data     Data to fill in
	 * @param string $path     Variable path for recursion
	 *
	 * @return string          The resulting JSON
	 * 
	 */
	private function parseVars($template, $data, $path = '') {
		foreach($data as $name => $string) {
			if(is_array($string)) {
				// Recursion
				$template = $this->parseVars($template, $string, $path . $name . ':');
				continue;
			}
			
			// Insert variables
			$template = str_replace('%{' . $path . $name . '}', $string, $template);
		}
		
		return $template;
	}
	
	/**
	 * Send
	 *
	 * Print the result and exit
	 * 
	 */
	public function send() {
		// Set status
		http_response_code($this->status);
		
		// Set content type to JSON
		header("Content-Type: {$this->type}");
		
		// Echo body
		echo $this->body;
		
		// Exit
		exit(0);
	}
	
	/**
	 * Exception
	 *
	 * Handle an exception and output something useful to the UI
	 *
	 * @param Exception $e The exception to handle
	 */
	public function exception($e) {
		try {
			$response = new APIResponse('exception', 500, array(
				'desc' => $e->getMessage()
			));
			$response->send();
		} catch(Exception $e2) {
			$this->body = $e2->getMessage();
			$this->type = 'text/plain';
			$this->status = 500;
		}
	}
	
	/**
	 * URI
	 *
	 * Get the URI of the request
	 * 
	 * @return string The URI
	 */
	static function uri() {
		return rtrim(substr($_SERVER['REQUEST_URI'], 1), '/');
	}
	
	/**
	 * Status
	 *
	 * Convert status code to description
	 * 
	 */
	private function status($code) {
		switch ($code) {
			case 100: return 'Continue';
			case 101: return 'Switching Protocols';
			case 200: return 'OK';
			case 201: return 'Created';
			case 202: return 'Accepted';
			case 203: return 'Non-Authoritative Information';
			case 204: return 'No Content';
			case 205: return 'Reset Content';
			case 206: return 'Partial Content';
			case 300: return 'Multiple Choices';
			case 301: return 'Moved Permanently';
			case 302: return 'Moved Temporarily';
			case 303: return 'See Other';
			case 304: return 'Not Modified';
			case 305: return 'Use Proxy';
			case 400: return 'Bad Request';
			case 401: return 'Unauthorized';
			case 402: return 'Payment Required';
			case 403: return 'Forbidden';
			case 404: return 'Not Found';
			case 405: return 'Method Not Allowed';
			case 406: return 'Not Acceptable';
			case 407: return 'Proxy Authentication Required';
			case 408: return 'Request Time-out';
			case 409: return 'Conflict';
			case 410: return 'Gone';
			case 411: return 'Length Required';
			case 412: return 'Precondition Failed';
			case 413: return 'Request Entity Too Large';
			case 414: return 'Request-URI Too Large';
			case 415: return 'Unsupported Media Type';
			case 500: return 'Internal Server Error';
			case 501: return 'Not Implemented';
			case 502: return 'Bad Gateway';
			case 503: return 'Service Unavailable';
			case 504: return 'Gateway Time-out';
			case 505: return 'HTTP Version not supported';
			default:  return '';
  	}
	}
}
