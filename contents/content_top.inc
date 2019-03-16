<?php
/**
 * Content top notifications
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */

$date = $settings->get("modules:geonames.last_update");
if( empty($date) ) $date = "0000-00-00 00:00:00";

if( $date >= date("Y-m-d H:i:s", strtotime("now - 3 months")) ) return;

echo "
    <div class='framed_content state_ko' style='margin-top: 0;'>
        <a href='{$this_module->get_url()}'><i class='fa fa-warning fa-fw'></i>{$this_module->language->messages->updates_required}</a>
    </div>
";
