
function geonames_get_country(code, $target)
{
    var url    = $_GEONAMES_URL + '/scripts/toolbox.php';
    var params = {
        get:          'country',
        country_code: code,
        wasuuup:      wasuuup()
    };
    
    $target.block(blockUI_smallest_params);
    $.get(url, params, function(response)
    {
        $target.unblock();
        $target.html(response).toggleClass('state_active', true);
    });
}

function geonames_get_region(country_code, region_code, $target)
{
    var url    = $_GEONAMES_URL + '/scripts/toolbox.php';
    var params = {
        get:          'region',
        country_code: country_code,
        region_code:  region_code,
        wasuuup:      wasuuup()
    };
    
    $target.block(blockUI_smallest_params);
    $.get(url, params, function(response)
    {
        $target.unblock();
        $target.html(response).toggleClass('state_active', true);
    });
}

function geonames_get_city(geonames_id, $target)
{
    var url    = $_GEONAMES_URL + '/scripts/toolbox.php';
    var params = {
        get:     'city',
        id:      geonames_id,
        wasuuup: wasuuup()
    };
    
    $target.block(blockUI_smallest_params);
    $.get(url, params, function(response)
    {
        $target.unblock();
        $target.html(response).toggleClass('state_active', true);
    });
}

function geonames_open_country_selector(trigger)
{
    __geonames_open_selector(trigger, '#geonames_country_selector', 'countries');
}

function geonames_open_region_selector(trigger)
{
    __geonames_open_selector(trigger, '#geonames_region_selector', 'regions');
}

function geonames_open_city_selector(trigger)
{
    __geonames_open_selector(trigger, '#geonames_city_selector', 'cities');
}

function __geonames_open_selector(trigger, selector, list)
{
    var $trigger = $(trigger);
    var $group   = $trigger.closest('.geonames_group');
    var $dialog  = $(selector);
    
    $dialog.data('group', $group);
    $dialog.find('.filter input').val('');
    $dialog.find('.options').html('');
    
    var url = sprintf(
        '%s/scripts/toolbox.php?render_list=%s&country_code=%s&region_code=%s&city_id=%s&wasuuup=%s',
        $_GEONAMES_URL,
        list,
        encodeURI( $group.find('.geonames_target[data-geonames-scope="country"] .geonames_id').val() ),
        encodeURI( $group.find('.geonames_target[data-geonames-scope="region"]  .geonames_id').val() ),
        encodeURI( $group.find('.geonames_target[data-geonames-scope="city"]    .geonames_id').val() ),
        wasuuup()
    );
    $.blockUI(blockUI_default_params);
    var $xhr = $.get(url, function(response) {
        
        $.unblockUI();
        
        if( response.indexOf('<error>') >= 0 )
        {
            throw_notification(response, 'error');
            
            return;
        }
        
        $dialog.find('.options').html(response);
        show_discardable_dialog(selector);
        
    }).fail(function($xhr, textStatus, errorThrown) {
        
        $.unblockUI();
        throw_notification(sprintf('%s %s', $xhr.status, errorThrown), 'error');
        
    }).timeout = 20000;
}

function filter_geonames_list(trigger)
{
    var $input   = $(trigger);
    var $dialog  = $input.closest('.geonames_selector_dialog');
    var value    = $input.val().trim().toLowerCase();
    
    $input.val( value );
    if( value === '' )
    {
        $dialog.find('.options .option').show();
        
        return;
    }
    
    $dialog.find('.options .option').each(function()
    {
        var $this = $(this);
        var text  = $this.text().toLowerCase();
        if( text.indexOf(value) >= 0 ) $this.show(); else $this.hide();
    })
}

function reset_geonames_filter(trigger)
{
    var $trigger = $(trigger);
    var $input   = $trigger.closest('.filter').find('input').val('');
    $input.trigger('change');
}

function set_geonames_selected_item(trigger)
{
    var $trigger  = $(trigger);
    var item_id   = $trigger.attr('data-id');
    var item_name = $trigger.find('.name').text();
    var $dialog   = $trigger.closest('.geonames_selector_dialog');
    var $group    = $dialog.data('group');
    var scope     = $dialog.attr('data-geonames-scope');
    
    var $field  = $group.find(sprintf('.geonames_target[data-geonames-scope="%s"]', scope));
    var prev_id = $field.find('input.geonames_id').val();
    console.log( 'scope: %s / previous:%s / new:%s / name:%s ', scope, prev_id, item_id, item_name );
    
    $field.find('.geonames_id').val(item_id);
    $field.find('.geonames_name').text(item_name).toggleClass('state_active', true);
    
    if( prev_id !== item_id )
    {
        var caption;
        
        switch(scope)
        {
            case 'country':
                
                caption = $group.find('.geonames_target[data-geonames-scope="region"]').attr('data-none-defined-caption');
                $group.find('.geonames_target[data-geonames-scope="region"] .geonames_id').val('');
                $group.find('.geonames_target[data-geonames-scope="region"] .geonames_name').html(caption).toggleClass('state_active', false);
                
                caption = $group.find('.geonames_target[data-geonames-scope="city"]').attr('data-none-defined-caption');
                $group.find('.geonames_target[data-geonames-scope="city"]   .geonames_id').val('');
                $group.find('.geonames_target[data-geonames-scope="city"]   .geonames_name').html(caption).toggleClass('state_active', false);
                break;
                
            case 'region':
                
                caption = $group.find('.geonames_target[data-geonames-scope="city"]').attr('data-none-defined-caption');
                $group.find('.geonames_target[data-geonames-scope="city"]   .geonames_id').val('');
                $group.find('.geonames_target[data-geonames-scope="city"]   .geonames_name').html(caption).toggleClass('state_active', false);
                break;
                
        }
    }
    
    $dialog.dialog('close');
}

function reset_geonames_selections(trigger)
{
    var $trigger = $(trigger);
    var $group   = $trigger.closest('.geonames_group');
    
    var caption;
    
    caption = $group.find('.geonames_target[data-geonames-scope="country"]').attr('data-none-defined-caption');
    $group.find('.geonames_target[data-geonames-scope="country"] .geonames_id').val('');
    $group.find('.geonames_target[data-geonames-scope="country"] .geonames_name').html(caption).toggleClass('state_active', false);
    
    caption = $group.find('.geonames_target[data-geonames-scope="region"]').attr('data-none-defined-caption');
    $group.find('.geonames_target[data-geonames-scope="region"]  .geonames_id').val('');
    $group.find('.geonames_target[data-geonames-scope="region"]  .geonames_name').html(caption).toggleClass('state_active', false);
    
    caption = $group.find('.geonames_target[data-geonames-scope="city"]').attr('data-none-defined-caption');
    $group.find('.geonames_target[data-geonames-scope="city"]    .geonames_id').val('');
    $group.find('.geonames_target[data-geonames-scope="city"]    .geonames_name').html(caption).toggleClass('state_active', false);
}

$(document).ready(function()
{
    var keys = ['country_selector', 'region_selector', 'city_selector'];
    
    for(var i in keys)
    {
        var key = keys[i];
        var url = sprintf( '%s/scripts/toolbox.php?render_dialog=%s&wasuuup=%s', $_GEONAMES_URL, key, wasuuup() );
        $.get(url, function(response)
        {
            if( response.indexOf('<error>') >= 0 )
            {
                throw_notification(response, 'error');
                
                return;
            }
            
            $('body').append( response );
        });
    }
});
