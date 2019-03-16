<?php
/**
 * Pre-rendering calculations
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 *             
 * @var account  $account
 * @var settings $settings
 * @var config   $config
 * @var template $template
 */

use hng2_base\account;
use hng2_base\config;
use hng2_base\settings;
use hng2_base\template;

if( ! $account->_exists ) return;
if( ! $account->has_admin_rights_to_module("geonames") ) return;

$date = $settings->get("modules:geonames.last_update");
if( ! empty($date) ) return;

$dates = array(
    $settings->get("modules:geonames.last_countries_update"),
    $settings->get("modules:geonames.last_admin1_codes_update"),
    $settings->get("modules:geonames.last_admin2_codes_update"),
    $settings->get("modules:geonames.last_altnames_update"),
    $settings->get("modules:geonames.last_postal_codes_update"),
    $settings->get("modules:geonames.last_extras_update"),
);

$earliest = min($dates);
if( empty($earliest) ) return;

$latest = max($dates);
$settings->set("modules:geonames.last_update", $latest);
