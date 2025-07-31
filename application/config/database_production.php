<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| DATABASE CONNECTIVITY SETTINGS FOR PRODUCTION
| -------------------------------------------------------------------
| This file contains the settings needed to access your database in production.
| It uses environment variables for security.
|
| -------------------------------------------------------------------
*/

$active_group = 'default';
$query_builder = TRUE;

// Get database configuration from environment variables
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'scms_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASSWORD') ?: '';

$db['default'] = array(
	'dsn'	=> '',
	'hostname' => $db_host . ':' . $db_port,
	'username' => $db_user,
	'password' => $db_pass,
	'database' => $db_name,
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => FALSE, // Set to FALSE in production
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => FALSE // Set to FALSE in production for better performance
); 