<?php

/**
 * Team++ media API
 * An API to serve media files for our podcasts
 *
 * @file core.php - core
 *
 * @version 1.0
 * @author Lukas Bestle <http://lu-x.me>
 * @link https://github.com/TeamPlusPlus/api
 * @copyright Copyright 2013 Lukas Bestle
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

// Roots
define('ROOT',            realpath(__DIR__ . '/..'));
define('ROOT_LIB',        realpath(ROOT . '/lib'));
define('ROOT_THIRDPARTY', realpath(ROOT_LIB . '/thirdparty'));
define('ROOT_CORE',       realpath(ROOT_LIB . '/core'));
define('ROOT_ENDPOINTS',  realpath(ROOT_LIB . '/endpoints'));
define('ROOT_TASKS',      realpath(ROOT_ENDPOINTS . '/tasks'));
define('ROOT_TEMPLATES',  realpath(ROOT . '/templates'));
define('ROOT_SNIPPETS',   realpath(ROOT_TEMPLATES . '/snippets'));
define('ROOT_FILES',      realpath(ROOT . '/files'));
define('ROOT_CACHE',      realpath(ROOT . '/cache'));
define('ROOT_LOGS',       realpath(ROOT . '/logs'));

// Autoload classes
spl_autoload_register(function($class) {
	@include ROOT_CORE . '/' . strtolower($class) . '.php';
});