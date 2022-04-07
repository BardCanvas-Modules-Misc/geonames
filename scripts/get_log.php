<?php
include "../../config.php";
include "../../includes/bootstrap.inc";
header("Content-Type: text/plain; charset=utf-8");

if( ! $account->_exists ) throw_fake_401();
if( $account->state != "enabled" ) throw_fake_501();
if( ! $account->has_admin_rights_to_module("geonames") ) throw_fake_401();

$log_file = trim(stripslashes($_GET["file"]));

if( empty($log_file) ) die("No file specified");
if( strtolower(end(explode(".", $log_file))) != "log" ) die("Invalid log file specified");

$file_path = "{$config->logfiles_location}/{$log_file}";
if( ! is_file($file_path) ) throw_fake_404();

$fh = fopen($file_path, "r");
fpassthru($fh);
fclose($fh);
