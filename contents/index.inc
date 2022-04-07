<?php
/**
 * GeoNames Maintenance Page
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 */
?>

<h1 class="clearfix">
    <button class="pull-right" onclick="location.href='<?= $current_module->get_url() ?>'">
        <i class="fa fa-refresh"></i>
        <?= $language->reload ?>
    </button>
    <?= $current_module->language->index_title ?>
</h1>

<script type="text/javascript">
    function update_geonames_table(trigger, import_mode)
    {
        var $trigger   = $(trigger);
        var $container = $trigger.closest('section');
        var $updater   = $container.find('.updater');
        var table      = $container.attr('data-table');
        var $target    = $container.find('.target');
        
        var url = sprintf(
            '<?= $current_module->get_url() ?>/scripts/toolbox.php?table=%s&wasuuup=%s',
            table, wasuuup()
        );
        
        $updater.prop('disabled', true);
        $updater.find('.normal').hide();
        $updater.find('.updating').show();
        
        if( import_mode ) url = url + '&import=true';
        
        var reloader_interval = null;
        
        $.get(url, function(response) {
            if( response.indexOf('@end!') > 0 )
            {
                response = response.replace('@end!', '');
                
                if( reloader_interval )
                {
                    clearInterval(reloader_interval);
                    
                    $updater.prop('disabled', false);
                    $updater.find('.updating').hide();
                    $updater.find('.normal').show();
                }
            }
            
            if( ! import_mode )
            {
                $updater.prop('disabled', false);
                $updater.find('.updating').hide();
                $updater.find('.normal').show();
            }
            
            response = $.trim(response);
            if( response !== '' ) $target.html( response );
        });
        
        if( import_mode )
        {
            $updater.prop('disabled', true);
            $updater.find('.normal').hide();
            $updater.find('.updating').show();
            
            $target.html(
                '<div class="greengo">' +
                '<i class="fa fa-spinner fa-pulse"></i> ' +
                '<?= $current_module->language->messages->importing_table ?>' +
                '</div>'
            );
            
            var reloader = function() {
                
                var url = $_FULL_ROOT_PATH + '/geonames/scripts/get_log.php?file=geonames-' + table + '.log';
                $.get(url, function(response) {
                    
                    if( response.indexOf('@end!') > 0 )
                    {
                        response = response.replace('@end!', '');
                        
                        if( reloader_interval )
                        {
                            clearInterval(reloader_interval);
                            
                            $updater.prop('disabled', false);
                            $updater.find('.updating').hide();
                            $updater.find('.normal').show();
                        }
                    }
                    
                    $target.html(response.replace(/\n/g, '<br>\n'))
                });
            };
            
            reloader_interval = setInterval(reloader, 5000);
        }
    }
    
    function refresh_all_tables()
    {
        $('.all_tables section h2 button').click();
    }
    
    function dismiss_update_warning()
    {
        var url = sprintf('<?= $current_module->get_url() ?>/scripts/toolbox.php?set_dmu=true&wasuuup=%s', wasuuup());
        $.blockUI(blockUI_default_params);
        $.get(url, function(response)
        {
            if( response !== 'OK' )
            {
                throw_notification(response, 'warning');
                $.unblockUI();
                
                return;
            }
            
            location.reload();
        });
    }
    
    $(document).ready(function() {
        refresh_all_tables();
    });
</script>

<?
$last_update = $settings->get("modules:geonames.last_update");
if( empty($last_update) ) $last_update = "0000-00-00 00:00:00";
if( $last_update < date("Y-m-d H:i:s", strtotime("now - 6 months")) )
{
    $dismiss_until = $settings->get("modules:geonames.dismiss_updates_warning_until");
    if( empty($dismiss_until) || date("Y-m-d H:i:s") >= $dismiss_until )
    {
        echo "<div class='framed_content state_highlight'>";
        echo "<i class='fa fa-warning'></i> ";
        echo replace_escaped_objects($current_module->language->messages->updates_required2, array(
            '{$last_update}'    => $last_update,
            '{$updated_ago}'    => time_elapsed_string($last_update),
            '{$dismiss_action}' => "dismiss_update_warning()",
        ));
        echo "</div>";
    }
}
?>

<div class="multicol cols-3 all_tables">
    
    <div class="col countries">
        <section data-table="countries">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->countries ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
    <div class="col admin1_codes">
        <section data-table="admin1_codes">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->admin1_codes ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
    <div class="col admin2_codes">
        <section data-table="admin2_codes">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->admin2_codes ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
    <div class="col altnames">
        <section data-table="altnames">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->altnames ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
    <div class="col postal_codes">
        <section data-table="postal_codes">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->postal_codes ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
    <div class="col extras">
        <section data-table="extras">
            <h2 class="clearfix">
                <button class="updater pull-right" onclick="update_geonames_table(this)">
                    <span class="normal">
                        <i class="fa fa-refresh"></i>
                        <?= $language->refresh ?>
                    </span>
                    <span class="updating" style="display: none;">
                        <i class="fa fa-spinner fa-pulse"></i>
                        <?= $current_module->language->updating ?>
                    </span>
                </button>
                
                <?= $current_module->language->extras ?>
            </h2>
            <div class="framed_content target">
                <i class="fa fa-spinner fa-pulse"></i> <?= $language->wait ?>
            </div>
        </section>
    </div>
    
</div>
