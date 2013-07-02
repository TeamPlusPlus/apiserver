<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file endpoints/tasks/build.php - build task (publish new episode)
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/api
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

/**
 * BuildTask
 * 
 * "Build" publishes episodes and creates the cache files.
 * 
 * @endpoint /task/build/$project:$episode
 */
class BuildTask extends Task {
	
	// Authentication
	public $auth = array(
		"vis7mac" => "5e610539a70b88e4b951e0fe29e1466c",
		"auphonic" => "927428b279a7cc98f150ad7d938b050c"
	);
	
	// Endpoints
	public $endpoints = array('episode' => '$project:$episode');
	
	/**
	 * Check if everything is OK
	 * 
	 * @param stdClass     $args    Additional arguments
	 * @param string       $user    The user authenticated
	 *
	 * @return bool/string $success If everything is OK (string with error message)
	 */
	public function check($args, $user) {
		// Check basic validity
		if(!isset($args[0])) {
			return 'Project name is missing';
		} else if(!preg_match('{[a-z]+}', $args[0])) {
			return 'Project name is invalid';
		} else if(!isset($args[1])) {
			return 'Episode name is missing';
		} else if(!preg_match('{[1-9]+}', $args[1])) {
			return 'Episode name is invalid';
		}
		
		// Check if project and episode exist
		if(!is_dir(ROOT_FILES . '/' . $args[0])) {
			return 'The project \'' . $args[0] . '\' does not exist';
		} else if(glob(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.*') == array()) {
			return 'The episode \'' . $args[0] . '/' . $args[1] . '\' does not exist';
		}
		
		// Check for metadata file
		if(!file_exists(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.json')) {
			return 'The metadata file does not exist.';
		} else if(!json_decode(file_get_contents(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.json'))) {
			return 'The metadata file is invalid.';
		}
		
		// Check if all mentioned files exist
		$json = json_decode(file_get_contents(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.json'), true);
		foreach($json['output_files'] as $file) {
			// Skip metadata file
			if($file['ending'] == 'json') continue;
			
			// Does it exist?
			if(!file_exists(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.' . $file['ending'])) {
				return 'The file \'' . $args[1] . '.' . $file['ending'] . '\' mentioned in the metadata file does not exist';
			}
			
			// Does the checksum match?
			if(md5_file(ROOT_FILES . '/' . $args[0] . '/' . $args[1] . '.' . $file['ending']) != $file['checksum']) {
				return 'The file \'' . $args[1] . '.' . $file['ending'] . '\' is corrupt';
			}
		}
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
		// Build the episode
		$files = $this->buildEpisode($args[0], $args[1]);
		
		// Check if the project cache folder already exists
		if(!is_dir(ROOT_CACHE . '/' . $args[0])) {
			mkdir(ROOT_CACHE . '/' . $args[0]);
		}
		
		// Write files
		foreach($files as $name => $json) {
			if(!@file_put_contents(ROOT_CACHE . '/' . $name, $json)) {
				return new APIResponse('task', 500, array(
					'endpoints' => json_encode($this->endpoints),
					'task' => 'build',
					'desc' => 'There was an error writing the file \'' . $name . '\'.'
				));
			}
		}
		
		// Trigger the API client
		$handle = curl_init('http://' . $args[0] . '.plusp.lu/apitrigger');
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_exec($handle);
		
		// Check if it returns the correct HTTP status
		$status = curl_getinfo($handle, CURLINFO_HTTP_CODE);
		if($status != 201) {
			return new APIResponse('task', 500, array(
				'endpoints' => json_encode($this->endpoints),
				'task' => 'build',
				'desc' => 'The HTTP status for triggering the project\'s update page (http://' . $args[0] . '.plusp.lu/apitrigger) was ' . $status . ' (expected 201).'
			));
		}

		curl_close($handle);
		
		return new APIResponse('task', 201, array(
			'endpoints' => json_encode($this->endpoints),
			'task' => 'build',
			'desc' => 'Successfully published \'' . $args[0] . '/' . $args[1] . '\'.'
		));
	}
	
	/**
	 * Build one episode
	 * 
	 * @param string            $project  Additional arguments
	 * @param string            $episode  The user authenticated
	 *
	 * @return array                      All generated cache files (PATH => JSON)
	 */
	private function buildEpisode($project, $episode) {
		$basename = ROOT_FILES . '/' . $project . '/' . $episode;
		
		// Get metadata from file
		$json = json_decode(file_get_contents(ROOT_FILES . '/' . $project . '/' . $episode . '.json'), true);
		
		// Get chapter data
		$chapters = $this->chapters($json['chapters']);
		
		// Analyze episode files
		$fileNames = glob(ROOT_FILES . '/' . $project . '/' . $episode . '.*');
		$files = array('media' => array(), 'meta' => array(), 'cover' => array(), 'other' => array());
		foreach($fileNames as $file) {
			$analyzed = $this->analyze($file, $project, $episode);
			
			$files[$analyzed['type']][$analyzed['extension']] = $analyzed['data'];
		}
		
		// Build episode file
		$episodeResponse = $this->apicall('episode', 200, array(
			'project' => $project,
			'episode' => $episode,
			'chapters' => json_encode($chapters),
			'duration' => (int)$json['length'],
			'infos' => array(
				'album' => $json['metadata']['album'],
				'track' => $json['metadata']['track'],
				'title' => $json['metadata']['title'],
				'subtitle' => $json['metadata']['subtitle'],
				'summary' => $json['metadata']['summary'],
				'publisher' => $json['metadata']['publisher'],
				'artist' => $json['metadata']['artist'],
				'title' => $json['metadata']['title'],
				'license' => array(
					'name' => $json['metadata']['license'],
					'url' => $json['metadata']['url'],
				),
				'url' => $json['metadata']['url'],
				'year' => $json['metadata']['year'],
				'genre' => $json['metadata']['genre'],
				'tags' => json_encode($json['metadata']['tags'])
			),
			'files' => array(
				'media' => json_encode($files['media']),
				'meta' => json_encode($files['meta']),
				'cover' => json_encode($files['cover']),
				'other' => json_encode($files['other'])
			)
		), $project . '/' . $episode);
		
		// Build episode overview
		$episodes = $this->combinefromfile(ROOT_CACHE . '/' . $project . '/index.json', 'episodes', array(
			$episode => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $project . '/' . $episode
		));
		
		$projectResponse = $this->apicall('project', 200, array(
			'project' => $project,
			'episodes' => json_encode($episodes)
		), $project);
		
		// Build episode data overview ("all")
		$currentEpisode = json_decode($episodeResponse->body, true);
		unset($currentEpisode['status']);
		
		$episodesData = $this->combinefromfile(ROOT_CACHE . '/' . $project . '/all.json', 'episodes', array(
			$episode => $currentEpisode
		));
		
		$episodesResponse = $this->apicall('episodes', 200, array(
			'project' => $project,
			'episodes' => json_encode($episodesData)
		), $project . '/all');
		
		// Project listing
		$projects = $this->combinefromfile(ROOT_CACHE . '/index.json', 'projects', array(
			$project => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $project
		));
		
		$projectsResponse = new APIResponse('projects', 200, array(
			'projects' => json_encode($projects)
		), '');
		
		return array(
			'index.json' => $projectsResponse->body,
			$project . '/index.json' => $projectResponse->body,
			$project . '/all.json' => $episodesResponse->body,
			$project . '/' . $episode . '.json' => $episodeResponse->body,
		);
	}
	
	/**
	 * Reduce chapter data to existing data
	 * 
	 * @param array $in The original chapter data
	 *
	 * @return array    The reduced chapters
	 */
	private function chapters($in) {
		$chapters = array();
		foreach($in as $chapter) {
			$chapterData = array(
				'start' => $chapter['start'],
				'title' => $chapter['title']
			);
			
			if($chapter['url'] != '') $chapterData['url'] = $chapter['url'];
			if($chapter['image'] != '') $chapterData['image'] = $chapter['image'];
			
			$chapters[] = $chapterData;
		}
		
		return $chapters;
	}
	
	/**
	 * Combine new data with existing data from a cache file
	 * 
	 * @param string $file The file path to open from
	 * @param string $key  The key of the file JSON to add stuff to
	 * @param array  $add  Items to add
	 *
	 * @return array       The resulting combined data array
	 */
	private function combinefromfile($file, $key, $add) {
		$data = array();
		if(file_exists($file)) {
			$json = json_decode(file_get_contents($file), true);
			$data = $json[$key];
		}
		
		return $add + $data;
	}
	
	/**
	 * Create an API call and check if it is fine
	 * 
	 * @param string       $template API template
	 * @param int          $status   HTTP status
	 * @param array        $data     The template data
	 * @param string       $uri      The custom URI
	 *
	 * @return APIResponse           The API response if it worked
	 */
	private function apicall($template, $status, $data, $uri) {
		$response = new APIResponse($template, $status, $data, $uri);
		
		// Check if it successfully generated the JSON
		if($response->status != 200) {
			$data = json_decode($response->body, true);
			
			$errorResponse = new APIResponse('task', 500, array(
				'endpoints' => json_encode($this->endpoints),
				'task' => 'build',
				'desc' => $data['description']
			));
			$errorResponse->send();
		}
		
		return $response;
	}
	
	/**
	 * Analyze a file
	 * 
	 * @param string $file    The file path
	 * @param string $project Project name
	 * @param string $episode Episode name
	 *
	 * @return array          File metadata
	 */
	private function analyze($file, $project, $episode) {
		// Custom file extension based data
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		
		$custom = array();
		switch($extension) {
			case 'mp3':
			case 'm4a':
			case 'ogg':
			case 'opus':
				$type = 'media';
				break;
			case 'psc':
			case 'json':
				$type = 'meta';
				break;
			case 'png':
				$type = 'cover';
				
				// Get image dimensions
				$imageData = getimagesize($file);
				$dimensions = (is_array($imageData)) ? array($imageData[0], $imageData[1]) : null;
				
				$custom = array(
					'dimensions' => $dimensions,
					'url'        => 'http://' . $_SERVER['HTTP_HOST'] . '/files/' . $project . '/' . $episode . '.' . $extension
				);
				
				break;
			default:
				$type = 'other';
		}
		
		$data = array_merge(array(
		  'url'  => 'http://' . $_SERVER['HTTP_HOST'] . '/' . $project . '/' . $episode . '.' . $extension,
			'size' => filesize($file)
		), $custom);
		
		return array(
			'type' => $type,
			'extension' => $extension,
			'data' => $data
		);
	}
}

// Request params
return '$project:$episode';