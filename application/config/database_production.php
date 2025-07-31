<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

$db['default'] = array(
	'dsn'	=> '',
	'hostname' => $_ENV['DB_HOST'] ?? 'mysql.railway.internal',
	'username' => $_ENV['DB_USER'] ?? 'root',
	'password' => $_ENV['DB_PASSWORD'] ?? 'tenvsjMAjkGkHHHupLrvTqlsvssZkUGK',
	'database' => $_ENV['DB_NAME'] ?? 'railway',
	'dbdriver' => 'mysqli',
	'dbprefix' => '',
	'pconnect' => FALSE,
	'db_debug' => FALSE, // Disable debug in production
	'cache_on' => FALSE,
	'cachedir' => '',
	'char_set' => 'utf8',
	'dbcollat' => 'utf8_general_ci',
	'swap_pre' => '',
	'encrypt' => FALSE,
	'compress' => FALSE,
	'stricton' => FALSE,
	'failover' => array(),
	'save_queries' => FALSE // Disable query saving in production
); 