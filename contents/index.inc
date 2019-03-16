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
                
                $.get(sprintf('%s/logs/geonames-%s.log', $_FULL_ROOT_PATH, table), function(response) {
                    
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
    
    $(document).ready(function() {
        refresh_all_tables();
    });
</script>

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
