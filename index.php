<?php
/**
 * GeoNames Maintenance Page
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * @var account  $account
 * @var template $template
 */

use hng2_base\account;
use hng2_base\template;

include "../config.php";
include "../includes/bootstrap.inc";
if( ! $account->_exists ) throw_fake_401();
if( ! $account->has_admin_rights_to_module("geonames") ) throw_fake_401();

$template->page_contents_include = "contents/index.inc";
$template->set_page_title($current_module->language->index_title);
$template->set("page_tag", "geonames");
include "{$template->abspath}/admin.php";
