<?php
/**
 * GeoNames Toolbox
 * 
 * @noinspection PhpFormatFunctionParametersMismatchInspection
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * Helpers:
 * @param string "set_dmu=true"  Dismisses the updates warning and exits.
 * 
 * Table ops params:
 * @param string "import"        "true" for importing mode, requires "table".
 * @param string "table"         Name of the table to import or process.
 * 
 * Dialog selector renderer params:
 * @param string "render_dialog" country_selector | region_selector | city_selector
 * 
 * List renderer params:
 * @param string "render_list"   countries | regions | cities
 * @param string "country_code"  Country code (alpha-2) to pre-select or as base for regions/cities.
 * @param string "region_code"   Region code (admin1_code) to pre-select or base for regions/cities.
 * @param string "city_id"       Geonames id of the city to pre-select. Optional.
 * 
 * Getters:
 * @param string "get"          country | region | city
 * @param string "country_code" required for country and region
 * @param string "region_code"  required for region
 * @param string "id"           required for element
 */

use hng2_modules\geonames\geonames;
use hng2_modules\geonames\importer;

include "../../config.php";
include "../../includes/bootstrap.inc";

# if( ! $account->_exists ) throw_fake_401();

header("Content-Type: text/html; charset=utf-8");

$importer     = new importer();
$geonames     = new geonames();
$valid_tables = array("countries", "admin1_codes", "admin2_codes", "altnames", "postal_codes", "extras");

$records_per_table = array(
    "countries"     =>  5100000,
    "altnames"      => 14700000,
    "admin1_codes"  =>     3900,
    "postal_codes"  =>  1420000,
    "admin2_codes"  =>    44000,
    "extras"        =>      220,
);

#
# Dismiss updates warning (admins only).
#

if( $_GET["set_dmu"] == "true" )
{
    if( ! $account->_exists ) throw_fake_401();
    if( ! $account->has_admin_rights_to_module("geonames") ) throw_fake_401();
    
    $settings->set("modules:geonames.dismiss_updates_warning_until", date("Y-m-d H:i:s", strtotime("now + 3 months")));
    send_notification($account->id_account, "success", $current_module->language->messages->dmu_set);
    
    die("OK");
}

#
# Import mode (admins only)
#

if( $_GET["import"] == "true" && in_array($_GET["table"], $valid_tables) )
{
    if( ! $account->has_admin_rights_to_module("geonames") ) throw_fake_401();
    
    $importer->import_table($_GET["table"]);
    
    die();
}

#
# Table ops mode (admins only)
#

if( ! empty($_GET["table"]) )
{
    if( in_array($_GET["table"], $valid_tables) )
    {
        if( ! $account->has_admin_rights_to_module("geonames") ) throw_fake_401();
        
        $count = $geonames->get_table_count($_GET["table"]);
        if( $count == 0 )
        {
            die("
                <a class='critical pseudo_link' onclick='update_geonames_table(this, true)'>
                    <i class='fa fa-warning'></i>
                    {$current_module->language->messages->table_empty}
                </a>
            ");
        }
        else
        {
            $last_update = $settings->get("modules:geonames.last_{$_GET["table"]}_update");
            if( empty($last_update) ) $last_update = "0000-00-00 00:00:00";
            
            if( $last_update == "0000-00-00 00:00:00" && $count >= $records_per_table[$_GET["table"]] )
            {
                $last_update = date("Y-m-d H:i:s");
                $settings->set("modules:geonames.last_{$_GET["table"]}_update", $last_update);
            }
            
            if( $last_update < date("Y-m-d H:i:s", strtotime("now - 6 months")) )
            {
                die("
                    <a class='critical pseudo_link' onclick='update_geonames_table(this, true)'>
                        <i class='fa fa-warning'></i>
                        {$current_module->language->messages->table_requires_update}
                    </a>
                ");
            }
            else
            {
                die(replace_escaped_objects(
                    "<span class='greengo'><i class='fa fa-check'></i> {$current_module->language->messages->table_ok}</span>", array(
                        '{$last_update}' => $last_update,
                        '{$when}'        => time_elapsed_string($last_update),
                        '{$records}'     => number_format($count),
                    )
                ));
            }
        }
    }
    
    die("
        <div class='critical'>
            <i class='fa fa-warning'></i> {$current_module->language->messages->invalid_table_name}
        </div>
    ");
}

#
# Selection dialog renderer (everyone)
#

$valid_dialogs = array("country_selector", "region_selector", "city_selector");

if( ! empty($_GET["render_dialog"]) )
{
    if( ! in_array($_GET["render_dialog"], $valid_dialogs) )
        die(sprintf(
            "<error>{$current_module->language->messages->invalid_dialog_key}</error>", $_GET["render_dialog"]
        ));
    
    include __DIR__ . "/../contents/selector_dialogs/{$_GET["render_dialog"]}.inc";
    die();
}

#
# List renderer (everyone)
#

$valid_lists = array("countries", "regions", "cities");

if( ! empty($_GET["render_list"]) )
{
    if( ! in_array($_GET["render_list"], $valid_lists) )
        die(sprintf(
            "<error>{$current_module->language->messages->invalid_list}</error>", $_GET["render_list"]
        ));
    
    if( in_array($_GET["render_list"], array("regions", "cities")) && empty($_GET["country_code"]) )
        die("<error>{$current_module->language->messages->country_code_missing}</error>");
    
    if( $_GET["render_list"] == "cities" && empty($_GET["region_code"]) )
        die("<error>{$current_module->language->messages->region_code_missing}</error>");
    
    $rows = array();
    switch($_GET["render_list"])
    {
        case "countries":
            $rows = $geonames->list_countries();
            break;
        case "regions":
            $rows = $geonames->list_country_regions($_GET["country_code"]);
            break;
        case "cities":
            $rows = $geonames->list_region_cities($_GET["country_code"], $_GET["region_code"]);
            if( empty($rows) )
            {
                $region = $geonames->get_region_by_code($_GET["country_code"], $_GET["region_code"]);
                if( ! is_null($region) ) $rows = array($region);
            }
            
            break;
    }
    
    if( empty($rows) )
        die("<error>{$current_module->language->messages->no_records_found}</error>");
    
    $selected = "";
    $options  = array();
    foreach($rows as $row)
    {
        switch($_GET["render_list"])
        {
            case "countries": $option_key = $row->country_code; $selected = $_GET["country_code"]; break;
            case "regions":   $option_key = $row->admin1_code;  $selected = $_GET["region_code"];  break;
            case "cities":    $option_key = $row->geoname_id;   $selected = $_GET["city_id"];      break;
        }
        
        $option_name          = empty($row->_short_name) ? $row->name : $row->_short_name;
        $options[$option_key] = $option_name;
    }
    asort($options);
    
    foreach($options as $option_key => $option_name)
    {
        $class = $option_key == $selected ? "state_active" : "";
        echo "
            <div class='option framed_content $selected' data-id='$option_key'
                 onmouseover='$(this).toggleClass(\"state_hover\", true)'
                 onmouseout='$(this).toggleClass(\"state_hover\", false)'
                 style='margin: 5px 0 0 0; cursor: pointer;'
                 onclick='set_geonames_selected_item(this)'>
                <span class='name'>$option_name</span>
            </div>
        ";
    }
    
    die();
}

if( ! empty($_GET["get"]) )
{
    if( ! in_array($_GET["get"], array("country", "region", "city")) )
        die("<error>{$current_module->language->messages->invalid_get_key}</error>");
    
    if( $_GET["get"] == "place" && empty($_GET["id"]) )
        die("<error>{$current_module->language->messages->no_id_provided}</error>");
    
    if( $_GET["get"] == "region" && empty($_GET["region_code"]) )
        die("<error>{$current_module->language->messages->region_code_missing2}</error>");
    
    if( $_GET["get"] == "region" && empty($_GET["country_code"]) )
        die("<error>{$current_module->language->messages->country_code_missing2}</error>");
    
    if( $_GET["get"] == "city" )
    {
        $res = $geonames->get($_GET["id"]);
        
        if( is_null($res) ) die("<error>{$current_module->language->messages->city_not_found}</error>");
        
        die( $res->_short_name );
    }
    
    if( $_GET["get"] == "region" )
    {
        $res = $geonames->get_region_by_code($_GET["country_code"], $_GET["region_code"]);
        
        if( is_null($res) ) die("<error>{$current_module->language->messages->region_not_found}</error>");
        
        die( $res->_short_name );
    }
    
    if( $_GET["get"] == "country" )
    {
        $res = $geonames->get_country_by_code($_GET["country_code"]);
        
        if( is_null($res) ) die("<error>{$current_module->language->messages->country_not_found}</error>");
        
        die( $res->_short_name );
    }
}

#
# Error exit
#

echo $current_module->language->messages->empty_arguments;
