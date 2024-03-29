<?php
namespace hng2_modules\geonames;

use hng2_repository\abstract_repository;

class geonames extends abstract_repository
{
    protected $db_prefix       = "";
    protected $row_class       = 'hng2_modules\geonames\geonames_record';
    protected $table_name      = 'geonames_countries';
    protected $key_column_name = 'geoname_id';
    
    public function __construct()
    {
        parent::__construct();
        
        if( defined("GEONAMES_SHARED_DB") ) $this->db_prefix = GEONAMES_SHARED_DB . ".";
    }
    
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
        
        $id = $id + 0;
        
        if( $object_cache->exists($this->table_name, $id) )
            return $object_cache->get($this->table_name, $id);
        
        $where = array($this->key_column_name => $id);
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($where, 1, 0, "");
        
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
        $rows = $this->mod_find($where, $limit, $offset, $order);
        if( count($rows) == 0 ) return array();
        
        $this->prepare_rows($rows);
        
        return $rows;
    }
    
    /**
     * @param array  $where
     * @param int    $limit
     * @param int    $offset
     * @param string $order
     * 
     * @return geonames_record[]
     * 
     * @throws \Exception
     */
    protected function mod_find($where, $limit, $offset, $order)
    {
        global $database;
        
        if( ! empty($limit)  && ! is_numeric($limit)  ) return array();
        if( ! empty($offset) && ! is_numeric($offset) ) return array();
        if( is_numeric($limit)  && $limit  < 0 ) return array();
        if( is_numeric($offset) && $offset < 0 ) return array();
        
        $query_where = "";
        if( ! empty($where) ) $query_where = "where " . $this->convert_where($where);
        
        $order_by = "";
        if( ! empty($order) ) $order_by = "order by {$order}";
        
        $limit_by = "";
        if($limit > 0 || $offset > 0 ) $limit_by = "limit $limit offset $offset";
        
        $table = empty($this->db_prefix) ? $this->table_name : ($this->db_prefix . $this->table_name);
        
        if( empty($this->additional_select_fields) )
        {
            $query = "
                select * from $table
                $query_where
                $order_by
                $limit_by
            ";
        }
        else
        {
            $all_fields = array_merge(
                array("`{$this->table_name}`.*"),
                $this->additional_select_fields
            );
        
            $all_fields_string = implode(",\n                  ", $all_fields);
            $query = "
                select
                  $all_fields_string
                from $table
                $query_where
                $order_by
                $limit_by
            ";
        }
        
        # echo "<pre>$query</pre>";
        $this->last_query = $query;
        $res = $database->query($query);
        
        if( $database->num_rows($res) == 0 ) return array();
        
        $return = array();
        while($row = $database->fetch_object($res))
        {
            $class = $this->row_class;
            $return[] = new $class($row);
        }
        
        return $return;
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
        
        $res = $database->query("select count(*) as `count` from {$this->db_prefix}geonames_{$table}");
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
        global $mem_cache;
        
        $mem_key = "@geonames:get_country_by_code:$code";
        $mem_ttl = 60 * 5;
        $res = $mem_cache->get($mem_key);
        if( ! empty($res) ) return $res;
        
        $filter = array(
            "feature_class" => "A",
            "feature_code"  => "PCLI",
            "country_code"  => addslashes($code)
        );
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 1, 0, "geoname_id asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        $mem_cache->set($mem_key, $rows[0], 0, $mem_ttl);
        
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
        global $mem_cache;
        
        $filter = array(
            "feature_class" => "A",
            "feature_code"  => "PCLI"
        );
        
        if( ! empty($codes_list) ) $filter[] = "country_code in ('" . implode("', '", $codes_list) . "')";
        
        $f_hash  = md5(implode(",", $codes_list) . "/" . $order);
        $mem_key = "@geonames:find_countries.$f_hash";
        $mem_ttl = 60 * 5;
        $res = $mem_cache->get($mem_key);
        if( ! empty($res) ) return $res;
        
        /** @var geonames_record[] $rows */
        $rows   = $this->mod_find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $return = array();
        foreach($rows as $row) $return[$row->country_code] = $row;
        $mem_cache->set($mem_key, $return, 0, $mem_ttl);
        
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
        global $mem_cache;
        
        $filter = array(
            "country_code"  => $country_code,
            "feature_class" => "A",
            "feature_code"  => "ADM1"
        );
        
        if( ! empty($admin1_codes) ) $filter[] = "admin1_code in ('" . implode("', '", $admin1_codes) . "')";
        
        $f_hash  = md5($country_code . "/" . implode(",", $admin1_codes) . "/" . $order);
        $mem_key = "@geonames:find_regions.$f_hash";
        $mem_ttl = 60 * 5;
        $res = $mem_cache->get($mem_key);
        if( ! empty($res) ) return $res;
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 0, 0, $order);
        if( empty($rows) ) return array();
        
        $return = array();
        foreach($rows as $row) $return[$row->country_code][$row->admin1_code] = $row;
        $mem_cache->set($mem_key, $return, 0, $mem_ttl);
        
        return $return;
    }
    
    /**
     * @param string $country_code
     * @param string $region_code
     * 
     * @return geonames_record|null
     * @throws \Exception
     */
    public function get_region_by_code($country_code, $region_code)
    {
        global $mem_cache;
        
        $mem_key = "@geonames:get_region_by_code:$country_code,$region_code";
        $mem_ttl = 60 * 5;
        $res = $mem_cache->get($mem_key);
        if( ! empty($res) ) return $res;
        
        $filter = array(
            "country_code"  => addslashes($country_code),
            "feature_class" => "A",
            "feature_code"  => "ADM1",
            "admin1_code"   => addslashes($region_code)
        );
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 1, 0, "geoname_id asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        $mem_cache->set($mem_key, $rows[0], 0, $mem_ttl);
        
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
        global $language, $database, $mem_cache;
        
        $iso = empty($language) ? "en" : $language->info->iso;
        
        $f_hash  = md5($iso . "/" . implode(",", $geoname_ids));
        $mem_key = "@geonames:get_alternate_names.$f_hash";
        $mem_ttl = 60 * 5;
        $res = $mem_cache->get($mem_key);
        if( ! empty($res) ) return $res;
        
        foreach( $geoname_ids as &$id) $id = "'$id'";
        $geoname_ids = implode(", ", $geoname_ids);
        
        $res = $database->query("
            select geoname_id, altname
            from   {$this->db_prefix}geonames_altnames
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
        
        $mem_cache->set($mem_key, $return, 0, $mem_ttl);
        
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
        $rows = $this->mod_find($filter, $limit, $offset, 'name asc');
        
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
        $rows = $this->mod_find($filter, 0, 0, $order);
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
            "country_code"  => addslashes($country_code),
            "feature_class" => "A",
            "feature_code"  => "ADM1"
        );
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 0, 0, $order);
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
            "country_code"  => addslashes($country_code),
            "admin1_code"   => addslashes($region_code),
            "feature_class" => "A",
            "feature_code"  => "ADM2"
        );
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 0, 0, $order);
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
            "country_code"  => addslashes($country_code),
            "admin1_code"   => addslashes($region_code),
            "feature_class" => "A",
            "feature_code"  => "ADM2",
            "name like '" . addslashes($city_name) . "%'"
        );
        
        /** @var geonames_record[] $rows */
        $rows = $this->mod_find($filter, 1, 0, "name asc");
        if( empty($rows) ) return null;
        
        $this->prepare_rows($rows);
        
        return current($rows);
    }
    
    
    
    
    /**
     * @return array = [
     *                     "US" => (object) ["+1",  "United States"],
     *                     "MX" => (object) ["+52", "Mexico"],
     *                     "AR" => (object) ["+54", "Argentina"],
     *                 ]
     * @throws \Exception
     */
    public function get_country_dial_prefixes()
    {
        global $database;
        
        $res = $database->query("
            select country_code, dial_prefix, name
            from {$this->db_prefix}geonames_extras
            order by name
        ");
        
        $rows = array();
        while($row = $database->fetch_object($res))
            $rows[$row->country_code] = (object) array(
                "prefix" => "+" . $row->dial_prefix,
                "name"   => $row->name
            );
        
        return $rows;
    }
    
    /**
     * @param $country_code
     *
     * @return null|int
     * @throws \Exception
     */
    public function get_country_dial_prefix_by_code($country_code)
    {
        global $database;
        
        $res = $database->query("select dial_prefix from {$this->db_prefix}geonames_extras where country_code = '$country_code'");
        if( $database->num_rows($res) == 0 ) return null;
        
        $row = $database->fetch_object($res);
        return "+" . $row->dial_prefix;
    }
}
