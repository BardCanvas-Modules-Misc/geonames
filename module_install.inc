<?php
/**
 * Module installation file
 *
 * @package    BardCanvas
 * @subpackage geonames
 * @author     Alejandro Caballero - lava.caballero@gmail.com
 * 
 * Trailing vars:
 * @var string $module_install_action install, uninstall, enable, disable
 * @var array  $messages
 * @var array  $errors
 * @var module $this_module
 * @var module $current_module
 */

use hng2_base\module;

$tables = array(
    
    # Source: http://download.geonames.org/export/dump/allCountries.zip ~ 342M
    # This one is for importing the file:
    "geonames_countries_temp" => "
        create table if not exists geonames_countries_temp (
            
            geoname_id    int unsigned  not null default 0,
            name          varchar(200) not null default '',
            ascii_name    varchar(200) not null default '',
            latitude      float        not null default 0,
            longitude     float        not null default 0,
            feature_class char(1)      not null default '',
            feature_code  varchar(10)  not null default '',
            country_code  varchar(2)   not null default '',
            admin1_code   varchar(20)  not null default '',
            admin2_code   varchar(20)  not null default '',
            admin3_code   varchar(20)  not null default '',
            admin4_code   varchar(20)  not null default '',
            timezone      varchar(40)  not null default ''
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    # This is the good one:
    "geonames_countries" => "
        create table if not exists geonames_countries (
            
            geoname_id    int unsigned  not null default 0,
            name          varchar(200) not null default '',
            ascii_name    varchar(200) not null default '',
            latitude      float        not null default 0,
            longitude     float        not null default 0,
            feature_class char(1)      not null default '',
            feature_code  varchar(10)  not null default '',
            country_code  varchar(2)   not null default '',
            admin1_code   varchar(20)  not null default '',
            admin2_code   varchar(20)  not null default '',
            admin3_code   varchar(20)  not null default '',
            admin4_code   varchar(20)  not null default '',
            timezone      varchar(40)  not null default '',
            
            primary key               ( geoname_id ),
            index   by_name           ( name(10) ),
            index   all_countries     ( feature_class, feature_code, country_code ),
            index   country_regions   ( country_code, feature_class, feature_code, admin1_code ),
            index   country_cities    ( country_code, feature_class, feature_code, admin1_code, admin2_code ),
            index   by_coords         ( latitude, longitude )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    
    # Source: http://download.geonames.org/export/dump/admin1CodesASCII.txt ~ 135K
    "geonames_admin1_codes" => "
        create table if not exists geonames_admin1_codes (
            
            code       varchar(20)  not null default '',
            name       varchar(200) not null default '',
            ascii_name varchar(200) not null default '',
            geoname_id int unsigned not null default 0,
            
            primary key ( code )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    
    # Source: http://download.geonames.org/export/dump/admin2Codes.txt ~ 2.2M
    "geonames_admin2_codes" => "
        create table if not exists geonames_admin2_codes (
            
            code       varchar(50)  not null default '',
            name       varchar(200) not null default '',
            ascii_name varchar(200) not null default '',
            geoname_id int unsigned not null default 0,
            
            primary key ( code )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    
    # Source: http://download.geonames.org/export/dump/alternateNamesV2.zip ~ 148M
    "geonames_altnames" => "
        create table if not exists geonames_altnames (
            
            altname_id   int unsigned     not null default 0,
            geoname_id   bigint unsigned  not null default 0,
            iso_language varchar(10)      not null default '',
            altname      varchar(255)     not null default '',
            is_preferred tinyint unsigned not null default 0,
            is_short     tinyint unsigned not null default 0,
            is_historic  tinyint unsigned not null default 0,
            
            primary key     ( altname_id ),
            index   by_id   ( geoname_id ),
            index   by_name ( altname(10), iso_language )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    
    # Sources: http://download.geonames.org/export/zip/allCountries.zip ~ 14M
    #          http://download.geonames.org/export/zip/GB_full.csv.zip ~ 13M
    #          http://download.geonames.org/export/zip/NL_full.csv.zip ~ 3.3M
    "geonames_postal_codes" => "
        create table if not exists geonames_postal_codes (
            
            country_code varchar(2)   not null default '',
            postal_code  varchar(20)  not null default '',
            name         varchar(180) not null default '',
            admin1_name  varchar(100) not null default '',
            admin1_code  varchar(20)  not null default '',
            admin2_name  varchar(100) not null default '',
            admin2_code  varchar(20)  not null default '',
            admin3_name  varchar(100) not null default '',
            admin3_code  varchar(20)  not null default '',
            latitude     float        not null default 0,
            longitude    float        not null default 0,
            
            primary key       ( country_code, postal_code, name ),
            index   by_coords ( latitude, longitude ),
            index   by_names  ( country_code, admin1_name(10), admin2_name(10) )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
    
    # Website: https://datahub.io/core/country-codes
    # Source: https://raw.githubusercontent.com/datasets/country-codes/master/data/country-codes.csv
    "geonames_extras" => "
        create table if not exists geonames_extras (
            
            geoname_id      int unsigned not null default 0,  # Geoname ID
            country_code    varchar(2)   not null default '', # ISO3166-1-Alpha-2
            name            varchar(200) not null default '', # CLDR display name
            dial_prefix     varchar(10)  not null default '', # Dial
            currency_symbol varchar(16)  not null default '', # ISO4217-currency_alphabetic_code
            currency_name   varchar(100) not null default '', # ISO4217-currency_name
            languages       varchar(255) not null default '', # Languages
            
            primary key             ( geoname_id ),
            index   by_country_code ( country_code ),
            index   by_name         ( name ),
            index   by_dial_prefix  ( dial_prefix )
            
        ) engine=InnoDB default charset=utf8mb4 collate='utf8mb4_unicode_ci'
    ",
);

$init_settings = array(
    "modules:geonames.about"                    => "",
    "modules:geonames.last_update"              => "",
    "modules:geonames.last_countries_update"    => "",
    "modules:geonames.last_admin1_codes_update" => "",
    "modules:geonames.last_admin2_codes_update" => "",
    "modules:geonames.last_altnames_update"     => "",
    "modules:geonames.last_postal_codes_update" => "",
    "modules:geonames.last_extras_update"       => "",
);

if( $module_install_action == "install" )
{
    foreach($tables as $table_name => $query)
    {
        try
        {
            $database->exec($query);
            $messages[] = replace_escaped_vars($language->install_messages->table_created_ok, '{$table_name}', $table_name);
        }
        catch( \Exception $e )
        {
            $errors[] = replace_escaped_vars(
                $language->install_messages->table_created_ko,
                array( '{$table_name}', '{$error}' ),
                array(   $table_name,     $e->getMessage()  )
            );
            
            return;
        }
    }
    
    # foreach($init_settings as $key => $val) $settings->set($key, $val);
    
    return;
}

if( $module_install_action == "uninstall" )
{
    foreach($tables as $table_name => $query)
    {
        try
        {
            $database->exec("DROP TABLE if exists {$table_name}");
            $messages[] = replace_escaped_vars($language->install_messages->table_deleted_ok, '{$table_name}', $table_name);
        }
        catch( \Exception $e )
        {
            $errors[] = replace_escaped_vars(
                $language->install_messages->table_deleted_ko,
                array( '{$table_name}', '{$error}' ),
                array(   $table_name,     $e->getMessage() )
            );
        }
    }
    
    foreach($init_settings as $key => $val) $settings->set($key, "");
    
    return;
}
