<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file core.php - core
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/apiserver
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Check for a custom path configuration file
if(file_exists(__DIR__ . '/../../paths.php')) {
	include __DIR__ . '/../../paths.php';
}

// Roots
if(!defined('ROOT'))            define('ROOT',            realpath(__DIR__ . '/..'));
if(!defined('ROOT_LIB'))        define('ROOT_LIB',        realpath(ROOT . '/lib'));
if(!defined('ROOT_THIRDPARTY')) define('ROOT_THIRDPARTY', realpath(ROOT_LIB . '/thirdparty'));
if(!defined('ROOT_CORE'))       define('ROOT_CORE',       realpath(ROOT_LIB . '/core'));
if(!defined('ROOT_ENDPOINTS'))  define('ROOT_ENDPOINTS',  realpath(ROOT_LIB . '/endpoints'));
if(!defined('ROOT_TASKS'))      define('ROOT_TASKS',      realpath(ROOT_ENDPOINTS . '/tasks'));
if(!defined('ROOT_TEMPLATES'))  define('ROOT_TEMPLATES',  realpath(ROOT . '/templates'));
if(!defined('ROOT_SNIPPETS'))   define('ROOT_SNIPPETS',   realpath(ROOT_TEMPLATES . '/snippets'));
if(!defined('ROOT_FILES'))      define('ROOT_FILES',      realpath(ROOT . '/files'));
if(!defined('ROOT_CACHE'))      define('ROOT_CACHE',      realpath(ROOT . '/cache'));
if(!defined('ROOT_LOGS'))       define('ROOT_LOGS',       realpath(ROOT . '/logs'));

// Autoload classes
spl_autoload_register(function($class) {
	@include ROOT_CORE . '/' . strtolower($class) . '.php';
});