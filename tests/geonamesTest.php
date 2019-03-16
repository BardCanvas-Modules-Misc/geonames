<?php
use hng2_db\db_controller;
use hng2_modules\geonames\geonames_record;
use hng2_tools\cli_colortags;

include "H:/WebDev Home/HNG2_CORE/classes/hng2_tools/cli.php";
include "H:/WebDev Home/HNG2_CORE/classes/hng2_tools/cli_colortags.php";
include "H:/WebDev Home/HNG2_CORE/classes/hng2_db/db_controller.php";
include "H:/WebDev Home/HNG2_CORE/classes/hng2_db/db_settings.php";
include "H:/WebDev Home/HNG2_CORE/classes/hng2_repository/abstract_repository.php";
include "H:/WebDev Home/HNG2_CORE/classes/hng2_repository/abstract_record.php";

include __DIR__ . "/../classes/geonames.php";
include __DIR__ . "/../classes/geonames_record.php";

class geonamesTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var \hng2_modules\geonames\geonames
     */
    private $geonames;
    
    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        global $database, $DATABASES, $language;
        
        parent::__construct($name, $data, $dataName);
        
        $language = (object) array(
            "info" => (object) array(
                "iso" => "en"
            )
        );
        
        $this->geonames = new hng2_modules\geonames\geonames();
        
        $DATABASES = array(
            array(
                "usage"  => "write",
                "host"   => "centos",
                "port"   => "3306",
                "user"   => "pma",
                "pass"   => "pma",
                "db"     => "bcfinancial_main",
            ),
        );
        
        $database = new db_controller();
    }
    
    public function testGet_list_countries()
    {
        echo "All countries:\n";
        
        $rows = $this->geonames->list_countries();
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    public function testGet_list_country_regions()
    {
        echo "\n\n";
        echo "All Mexico states:\n";
        
        $rows = $this->geonames->list_country_regions("MX");
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    public function testGet_get_region_cities()
    {
        echo "\n\n";
        echo "All cities of Tamaulipas, Mexico:\n";
        
        $rows = $this->geonames->list_region_cities("MX", "28");
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    public function testGet_surrounding_area()
    {
        $dist = 10;
    
        echo "\n\n";
        echo "Coords for the $dist KM surrounding area of Arboledas, Altamira, Tamaulipas, Mexico:\n";
        echo ">>> " . implode(", ", $this->geonames->get_surrounding_area(22.3907354, -97.9148279, $dist, "K") );
    }
    
    public function testGet_distance()
    {
        $dist   = 10;
        $coords = $this->geonames->get_surrounding_area(22.3907354, -97.9148279, $dist, "K");
        
        echo "\n\n";
        echo "Surrounding area in KM for the previous result:\n";
        echo ">>> " . $this->geonames->get_distance($coords[0], $coords[1], $coords[2], $coords[3], "K");
    }
    
    public function testGet_list_within_area()
    {
        $dist   = 5;
        $coords = $this->geonames->get_surrounding_area(22.3907354, -97.9148279, $dist, "K");
        
        echo "\n\n";
        echo "Places $dist KM around Arboledas, Altamira, Tamaulipas, Mexico:\n";
        echo ">>> " . implode(", ", $coords);
        echo " (" . round($this->geonames->get_distance($coords[0], $coords[1], $coords[2], $coords[3], "K")) . " KM²)\n";
    
        /**
         * @var geonames_record[] $rows
         */
        $rows = $this->geonames->list_within_area($coords[0], $coords[1], $coords[2], $coords[3]);
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    public function testGet_list_within_area2()
    {
        $dist   = 5;
        $coords = $this->geonames->get_surrounding_area(19.28333, -99.13333, $dist, "K");
        
        echo "\n";
        echo "Places $dist KM around 19.28333, -99.13333 (Mexico City center):\n";
        echo ">>> " . implode(", ", $coords);
        echo " (" . round($this->geonames->get_distance($coords[0], $coords[1], $coords[2], $coords[3], "K")) . " KM²)\n";
    
        /**
         * @var geonames_record[] $rows
         */
        $rows = $this->geonames->list_within_area($coords[0], $coords[1], $coords[2], $coords[3]);
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    public function testGet_list_within_area3()
    {
        $dist   = 10;
        $coords = $this->geonames->get_surrounding_area(26.08061, -98.28835, $dist, "K");
        
        echo "\n";
        echo "Places $dist KM around Reynosa, Tamaulipas, Mexico:\n";
        echo ">>> " . implode(", ", $coords);
        echo " (" . round($this->geonames->get_distance($coords[0], $coords[1], $coords[2], $coords[3], "K")) . " KM²)\n";
        
        /**
         * @var geonames_record[] $rows
         */
        $rows = $this->geonames->list_within_area($coords[0], $coords[1], $coords[2], $coords[3]);
        if( empty($rows) )
        {
            echo "    ! nothing found!\n";
            
            return;
        }
        
        foreach($rows as $row) $this->_render_row($row);
    }
    
    /**
     * @param geonames_record $row;
     */
    private function _render_row($row)
    {
        cli_colortags::write(sprintf(
            "    • %s, %s, %s (#%s %s:%s lat:%s lon:%s)\n",
            empty($row->_short_name) ? "<yellow>{$row->name}</yellow>" : $row->_short_name,
            $row->admin1_code == '00' || ! isset($row->_region_record) ? '-' : (
                empty($row->_region_record->_short_name)
                    ? "<yellow>{$row->_region_record->name}</yellow>"
                    : "{$row->_region_record->_short_name}"),
            ! isset($row->_country_record) ? "-" : (
                empty($row->_country_record->_short_name)
                    ? "<yellow>{$row->_country_record->name}</yellow>"
                    : "{$row->_country_record->_short_name}"),
            $row->geoname_id,
            $row->feature_class,
            $row->feature_code,
            $row->latitude,
            $row->longitude
        ));
    }
}
