<?php
/**
 * City selector
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */
?>

<div id="geonames_city_selector" class="geonames_selector_dialog" data-geonames-scope="city"
     style="display: none" title="<?= $current_module->language->select_city ?>">

    <div class="filter nowrap" style="position: relative; margin-bottom: 15px;">
        <i class="fa fa-times fa-fw" style="position: absolute; right: 0; top: 2px; cursor: pointer;"
           onclick="reset_geonames_filter(this)"></i>

        <input type="text" name="filter" style="width: 100%; padding-right: 24px"
               placeholder="<?= $current_module->language->filter_placeholder ?>"
               onkeyup="filter_geonames_list(this)" onchange="filter_geonames_list(this)">
    </div>
    
    <div class="options"></div>
    
</div>
