<?php
namespace hng2_modules\geonames;

use hng2_repository\abstract_record;

class geonames_record extends abstract_record
{
    public $geoname_id   ; # int unsigned  not null default 0,
    public $name         ; # varchar(200) not null default '',
    public $ascii_name   ; # varchar(200) not null default '',
    public $latitude     ; # float        not null default 0,
    public $longitude    ; # float        not null default 0,
    public $feature_class; # char(1)      not null default '',
    public $feature_code ; # varchar(10)  not null default '',
    public $country_code ; # varchar(2)   not null default '',
    public $admin1_code  ; # varchar(20)  not null default '',
    public $admin2_code  ; # varchar(20)  not null default '',
    public $admin3_code  ; # varchar(20)  not null default '',
    public $admin4_code  ; # varchar(20)  not null default '',
    public $timezone     ; # varchar(40)  not null default '',
    
    // NOTE: These are dynamically added, must be removed when exporting for database insertion!
    
    /**
     * @var string
     */
    public $_short_name;
    
    /**
     * @var geonames_record
     */
    public $_country_record;
    
    /**
     * @var geonames_record
     */
    public $_region_record;
    
    public function set_new_id() { throw new \Exception("Method not implemented"); }
}
