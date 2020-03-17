<?php
/**
 * Content top notifications
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

if( ! $account->has_admin_rights_to_module("geonames") ) return;

$date = $settings->get("modules:geonames.last_update");
if( empty($date) ) $date = "0000-00-00 00:00:00";

if( $date >= date("Y-m-d H:i:s", strtotime("now - 6 months")) ) return;

$dismiss_until = $settings->get("modules:geonames.dismiss_updates_warning_until");
if( ! empty($dismiss_until) && date("Y-m-d H:i:s") < $dismiss_until ) return;

echo "
    <div class='framed_content state_ko' style='margin-top: 0;'>
        <a href='{$this_module->get_url()}'><i class='fa fa-warning fa-fw'></i>{$this_module->language->messages->updates_required}</a>
    </div>
";
