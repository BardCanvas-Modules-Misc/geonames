<?php
/**
 * Accounts header buttons
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
if( empty($date) ) $date = "0000-00-00 00:00:00";

$alerted = false;
if( stristr($_SERVER["PHP_SELF"], $this_module->get_url()) === false )
{
    
    if( $date >= date("Y-m-d H:i:s", strtotime("now - 3 months")) ) return;
    else $alerted = true;
}

if( $date < date("Y-m-d H:i:s", strtotime("now - 3 months")) ) $alerted = true;

$added_class   = stristr($_SERVER["PHP_SELF"], $this_module->get_url()) !== false ? "current" : "";
$menu_icon     = $alerted ? "fa-warning" : "fa-globe";
$alerted_class = $alerted ? "alerted" : "";

$template->add_menu_item(
    "xxxxxxxxxxxxxxxx",
    "
    <a class='main_menu_item pseudo_link highlighted $alerted_class $added_class'
          data-module='{$this_module->name}' href='{$this_module->get_url()}'
          title='{$this_module->language->open_maintenance_page}'>
          <i class='fa {$menu_icon} fa-fw'></i> {$this_module->language->menu_caption}
    </a>
    ",
    910
);
