<?php
namespace hng2_modules\geonames;

use hng2_repository\abstract_repository;

class geonames extends abstract_repository
{
    protected $row_class       = 'hng2_modules\geonames\geonames_record';
    protected $table_name      = 'geonames_countries';
    protected $key_column_name = 'geoname_id';
    
    public function save($record) { throw new \Exception("Method not implemented"); }
    
    public function validate_record($record) { throw new \Exception("Method not implemented"); }
    
    /**
     * @param $id
     *
     * @return geonames_record|null
     * @throws \Exception
     */
    public function get($id)
    {
        global $object_cache;
        
        if( $object_cache->exists($this->table_name, $id) )
            return $object_cache->get($this->table_name, $id);
        
        $where = array($this->key_column_name => $id);
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($where, 1, 0, "");
        
        if( count($rows) == 0 ) return null;
        
        $this->prepare_rows($rows);
        $item = $rows[0];
        
        $object_cache->set($this->table_name, $id, $item);
        return $item;
    }
    
    /**
     * @param array  $where
     * @param int    $limit
     * @param int    $offset
     * @param string $order
     * 
     * @return geonames_record[]
     * @throws \Exception
     */
    public function find($where, $limit, $offset, $order)
    {
        /** @var geonames_record[] $rows */
        $rows = parent::find($where, $limit, $offset, $order);
        if( count($rows) == 0 ) return array();
        
        $this->prepare_rows($rows);
        
        return $rows;
    }
    
    
    
    /**
     * @param string $table
     *
     * @return int
     * @throws \Exception
     */
    public function get_table_count($table)
    {
        global $database;
        
        $res = $database->query("select count(*) as `count` from geonames_{$table}");
        $row = $database->fetch_object($res);
        
        return $row->count;
    }
    
    /**
     * @param string $code
     * 
     * @return geonames_record|null
     * @throws \Exception
     */
    public function get_country_by_code($code)
    {
        $filter = array(
            "feature_class" => "A",
            "feature_code"  => "PCLI",
            "country_code"  => $code
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 1, 0, "geoname_id asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        return $rows[0];
    }
    
    /**
     * Returns an associative array of countries
     * where the key is the country code (alpha 2)
     * 
     * @param array  $codes_list
     * @param string $order
     * 
     * @return geonames_record[]
     * @throws \Exception
     */
    private function find_countries($codes_list = array(), $order = "name asc")
    {
        $filter = array(
            "feature_class" => "A",
            "feature_code"  => "PCLI"
        );
        
        if( ! empty($codes_list) ) $filter[] = "country_code in ('" . implode("', '", $codes_list) . "')";
        
        /** @var geonames_record[] $rows */
        $rows   = parent::find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $return = array();
        foreach($rows as $row) $return[$row->country_code] = $row;
        
        return $return;
    }
    
    /**
     * Returns an associative array of regions in two dimensions: by country code and admin1_code
     *
     * @param string $country_code
     * @param array  $admin1_codes
     * @param string $order
     * 
     * @return geonames_record[][]
     * @throws \Exception
     */
    private function find_regions($country_code, $admin1_codes = array(), $order = "name asc")
    {
        $filter = array(
            "country_code"  => $country_code,
            "feature_class" => "A",
            "feature_code"  => "ADM1"
        );
        
        if( ! empty($admin1_codes) ) $filter[] = "admin1_code in ('" . implode("', '", $admin1_codes) . "')";
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $return = array();
        foreach($rows as $row) $return[$row->country_code][$row->admin1_code] = $row;
        
        return $return;
    }
    
    /**
     * @param string $country_code
     * 
     * @return geonames_record|null
     * @throws \Exception
     */
    public function get_region_by_code($country_code, $region_code)
    {
        $filter = array(
            "country_code"  => $country_code,
            "feature_class" => "A",
            "feature_code"  => "ADM1",
            "admin1_code"   => $region_code
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 1, 0, "geoname_id asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        return $rows[0];
    }
    
    /**
     * Returns an associative array of alternate names by geoname_id
     * 
     * @param array $geoname_ids
     * 
     * @return array
     * @throws \Exception
     */
    private function get_alternate_names($geoname_ids)
    {
        global $language, $database;
        
        $iso = empty($language) ? "en" : $language->info->iso;
        
        foreach( $geoname_ids as &$id) $id = "'$id'";
        $geoname_ids = implode(", ", $geoname_ids);
        
        $res = $database->query("
            select geoname_id, altname
            from   geonames_altnames
            where  geoname_id in ($geoname_ids)
            and    ( (iso_language = '$iso') or (iso_language = '' ) )
            group by altname
            order by is_short desc, is_preferred desc, iso_language desc, altname asc
        ");
        if( $database->num_rows($res) == 0 ) return array();
        
        $return = array();
        while($row = $database->fetch_object($res))
            if( ! isset($return[$row->geoname_id]) )
                $return[$row->geoname_id] = $row->altname;
        
        return $return;
    }
    
    
    
    /**
     * Calculate the distance between two points
     * https://www.mullie.eu/geographic-searches/
     * 
     * @param number $lat1 Latitude in decimanls
     * @param number $lon1 longitude in decimalls
     * @param number $lat2 Latitude in decimanls
     * @param number $lon2 longitude in decimalls
     * @param string $unit M|K (Miles, Kilometers) - defaults to Miles
     * 
     * @return float
     */
    public function get_distance($lat1, $lon1, $lat2, $lon2, $unit = "M")
    {
        // convert latitude/longitude degrees for both coordinates
        // to radians: radian = degree * π / 180
        $lat1 = deg2rad($lat1);
        $lon1 = deg2rad($lon1);
        $lat2 = deg2rad($lat2);
        $lon2 = deg2rad($lon2);
        
        // calculate great-circle distance
        $distance = acos(sin($lat1) * sin($lat2) + cos($lat1) * cos($lat2) * cos($lon1 - $lon2));
        
        // distance in human-readable format:
        // earth's radius in km = ~6371
        return $distance * ($unit == "K" ? 6371 : 3958);
    }
    
    /**
     * Get the bounding box around the given distance of the given coords.
     * https://www.mullie.eu/geographic-searches/
     * 
     * @param number $lat
     * @param number $lon
     * @param number $distance
     * @param string $unit M|K (Miles, Kilometers) - defaults to Miles
     * 
     * @return array [lat1, lon1, lat2, lon2]
     */
    public function get_surrounding_area($lat, $lon, $distance, $unit = "M")
    {
        // earth's radius = ~6371 km / ~3958 miles
        $radius = $unit == "K" ? 6371 : 3958;
        
        // latitude boundaries
        $maxlat = $lat + rad2deg($distance / $radius);
        $minlat = $lat - rad2deg($distance / $radius);
        
        // longitude boundaries (longitude gets smaller when latitude increases)
        $maxlon = $lon + rad2deg($distance / $radius / cos(deg2rad($lat)));
        $minlon = $lon - rad2deg($distance / $radius / cos(deg2rad($lat)));
        
        return array($minlat, $minlon, $maxlat, $maxlon);
    }
    
    /**
     * Returns a collection of places within the specified coordinates
     * 
     * @param number $lat1 Latitude in decimanls
     * @param number $lon1 longitude in decimalls
     * @param number $lat2 Latitude in decimanls
     * @param number $lon2 longitude in decimalls
     * @param int    $limit
     * @param int    $offset
     * 
     * @return geonames_record[]
     * @throws \Exception
     */
    public function list_within_area($lat1, $lon1, $lat2, $lon2, $limit = 0, $offset = 0)
    {
        $filter = array(
            "latitude  between $lat1 and $lat2",
            "longitude between $lon1 and $lon2",
            "feature_class in ('P')",
            "feature_code  in ('PPL', 'PPLS', 'PPLX')"
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, $limit, $offset, 'name asc');
        
        if( empty($rows) ) return array();
        
        $this->prepare_rows($rows);
        
        return $rows;
    }
    
    /**
     * Returns an associative array of countries by alpha2 code
     * 
     * @param string $order
     *
     * @return geonames_record[]
     * @throws \Exception
     */
    public function list_countries($order = "name asc")
    {
        $filter = array(
            "feature_class" => "A",
            "feature_code"  => "PCLI"
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $this->prepare_rows($rows);
        
        $return = array();
        foreach($rows as $row) $return[$row->country_code] = $row;
        
        return $return;
    }
    
    /**
     * Returns an associative array of regions by admin1_code
     * 
     * @param string $country_code
     * @param string $order
     *
     * @return geonames_record[]
     * @throws \Exception
     */
    public function list_country_regions($country_code, $order = "name asc")
    {
        $filter = array(
            "country_code"  => $country_code,
            "feature_class" => "A",
            "feature_code"  => "ADM1"
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $this->prepare_rows($rows);
        
        $return = array();
        foreach($rows as $row) $return[$row->admin1_code] = $row;
        
        return $return;
    }
    
    /**
     * Returns an associative area of all cities of a region/state
     * 
     * @param string $country_code
     * @param string $region_code
     * @param string $order
     * 
     * @return geonames_record[]
     * @throws \Exception
     */
    public function list_region_cities($country_code, $region_code, $order = "name asc")
    {
        $filter = array(
            "country_code"  => $country_code,
            "admin1_code"   => $region_code,
            "feature_class" => "A",
            "feature_code"  => "ADM2"
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $this->prepare_rows($rows);
        
        $return = array();
        foreach($rows as $row) $return[$row->geoname_id] = $row;
        
        return $return;
    }
    
    /**
     * Adds dynamic properties to entity collections:
     * - $_country_record
     * - $_region_record
     * - $_short_name
     * 
     * @param geonames_record[] $rows By reference
     * 
     * @throws \Exception
     */
    private function prepare_rows(&$rows)
    {
        if( empty($rows) ) return;
        
        # 
        # Inits
        # 
        
        $country_codes   = array();
        $country_regions = array();
        $all_geoname_ids = array();
        
        foreach($rows as $row)
        {
            $all_geoname_ids[$row->geoname_id]     = true;
            $country_codes[]                       = $row->country_code;
            $country_regions[$row->country_code][] = $row->admin1_code;
        }
        
        $countries = $this->find_countries($country_codes);
        $regions   = array();
        foreach($country_codes as $code)
        {
            $codes = $country_regions[$code];
            $rrows = $this->find_regions($code, $codes);
            
            if( empty($rrows) ) continue;
            
            foreach($rrows[$code] as $rrow) $all_geoname_ids[$rrow->geoname_id] = true;
            $regions = array_merge($regions, $rrows);
        }
        
        $all_geoname_ids = array_keys($all_geoname_ids);
        $altnames = $this->get_alternate_names($all_geoname_ids);
        
        #
        # Main loop
        #
        
        foreach($rows as $row)
        {
            $row->_short_name = $altnames[$row->geoname_id];
            if( empty($row->_short_name) ) $row->_short_name = $row->name;
            
            if( $row->feature_class == "A" && $row->feature_code == "PCLI" ) continue;
            
            $row->_country_record              = $countries[$row->country_code];
            $row->_country_record->_short_name = $altnames[$row->_country_record->geoname_id];
            if( empty($row->_country_record->_short_name) )
                $row->_country_record->_short_name = $row->_country_record->name;
            
            if( $row->feature_class == "A" && $row->feature_code == "ADM1" ) continue;
            
            if( isset($regions[$row->country_code][$row->admin1_code]) )
            {
                $row->_region_record              = $regions[$row->country_code][$row->admin1_code];
                $row->_region_record->_short_name = $altnames[$row->_region_record->geoname_id];
                if( empty($row->_region_record->_short_name) )
                    $row->_region_record->_short_name = $row->_region_record->name;
            }
        }
        
        reset($rows);
    }
    
    /**
     * @param string $country_code
     * @param string $region_code
     * @param string $city_name
     * 
     * @return geonames_record|null
     * @throws \Exception
     */
    public function find_city($country_code, $region_code, $city_name)
    {
        $filter = array(
            "country_code"  => $country_code,
            "admin1_code"   => $region_code,
            "feature_class" => "A",
            "feature_code"  => "ADM2",
            "name like '" . addslashes($city_name) . "%'"
        );
        
        /** @var geonames_record[] $rows */
        $rows = parent::find($filter, 1, 0, "name asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        
        return current($rows);
    }
}
