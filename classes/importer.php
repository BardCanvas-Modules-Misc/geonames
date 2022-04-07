<?php
namespace hng2_modules\geonames;

use hng2_tools\cli;

# Manual downloading procedure:
# cd public_html/data/tmp
# wget http://download.geonames.org/export/dump/allCountries.zip                              -O geonames-countries.zip
# wget http://download.geonames.org/export/dump/admin1CodesASCII.txt                          -O geonames-admin1_codes.txt
# wget http://download.geonames.org/export/dump/admin2Codes.txt                               -O geonames-admin2_codes.txt
# wget http://download.geonames.org/export/dump/alternateNamesV2.zip                          -O geonames-altnames.zip
# wget http://download.geonames.org/export/zip/allCountries.zip                               -O geonames-postal_codes.zip
# wget https://raw.githubusercontent.com/datasets/country-codes/master/data/country-codes.csv -O geonames-extras.csv
# Once the files are downloaded, you can go to the page and try integrating them one by one.

class importer
{
    private $db_host = "";
    private $db_user = "";
    private $db_pass = "";
    private $db_db   = "";
    private $db_port = "";
    
    public function __construct()
    {
        global $DATABASES;
        
        $this->db_host = $DATABASES[0]["host"];
        $this->db_user = $DATABASES[0]["user"];
        $this->db_pass = $DATABASES[0]["pass"];
        $this->db_db   = defined("GEONAMES_SHARED_DB") ? GEONAMES_SHARED_DB : $DATABASES[0]["db"];
        $this->db_port = $DATABASES[0]["port"];
    }
    
    private $sources_per_table = array(
        "countries"    => "https://download.geonames.org/export/dump/allCountries.zip",
        "admin1_codes" => "https://download.geonames.org/export/dump/admin1CodesASCII.txt",
        "admin2_codes" => "https://download.geonames.org/export/dump/admin2Codes.txt",
        "altnames"     => "https://download.geonames.org/export/dump/alternateNamesV2.zip",
        "postal_codes" => "https://download.geonames.org/export/zip/allCountries.zip",
        "extras"       => "https://raw.githubusercontent.com/datasets/country-codes/master/data/country-codes.csv",
    );
    
    # All amounts here are 1% below calculated sizes.
    private $sizes_per_table = array(
        "countries"    => 342.000,
        "admin1_codes" =>   0.125,
        "admin2_codes" =>   2.100,
        "altnames"     => 147.000,
        "postal_codes" =>  13.000,
        "extras"       =>   0.126,
    );
    
    public function import_table($table)
    {
        global $config, $modules;
        $current_module = $modules["geonames"];
        
        $ext  = end(explode(".", $this->sources_per_table[$table]));
        $file = "{$config->datafiles_location}/tmp/geonames-{$table}.{$ext}";
        
        cli::$output_file         = "{$config->logfiles_location}/geonames-{$table}.log";
        cli::$output_to_file_only = true;
        
        if( is_file("$file.downloading") || is_file("$file.integrating") )
        {
            echo str_replace("\n", "<br>", file_get_contents(cli::$output_file));
            
            return;
        }
        
        @unlink(cli::$output_file);
        
        $download = true;
        if( is_file($file) )
        {
            $size = filesize($file);
            if( $size < $this->sizes_per_table[$table] * 1000 * 1000 )
            {
                cli::write( "{$current_module->language->messages->file_stale}\n" );
                unlink($file);
            }
            else
            {
                cli::write( "{$current_module->language->messages->file_exists}\n" );
                $download = false;
            }
        }
        
        if( $download )
        {
            touch("$file.downloading");
            $this->fetch_source($table, $file);
            unlink("$file.downloading");
        }
        
        touch("$file.integrating");
        switch($table)
        {
            case "countries":
                $this->integrate_countries();
                break;
            case "admin1_codes":
                $this->integrate_admin1_codes();
                break;
            case "admin2_codes":
                $this->integrate_admin2_codes();
                break;
            case "altnames":
                $this->integrate_altnames();
                break;
            case "postal_codes":
                $this->integrate_postal_codes();
                break;
            case "extras":
                $this->integrate_extras();
                break;
        }
        unlink("$file.integrating");
        
        cli::write( "@end!" );
    }
    
    private function fetch_source($table, $file)
    {
        global $modules;
        $current_module = $modules["geonames"];
        
        $url   = $this->sources_per_table[$table];
        $start = time();
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->downloading} [", $table) );
        
        try
        {
            $this->download($url, $file);
        }
        catch(\Exception $e)
        {
            /** @noinspection PhpFormatFunctionParametersMismatchInspection */
            cli::write(
                sprintf("\n{$current_module->language->messages->download_error}\n", $e->getMessage())
            );
            
            if( filesize($file) < 1000 ) @unlink($file);
            
            die();
        }
        
        $size = round(filesize($file) / 1024 / 1024, 1);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("] {$current_module->language->messages->download_ok}\n", $size, $secs) );
    }
    
    private function integrate_countries()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $zipfile  = "{$path}/geonames-countries.zip";
        $textfile = "{$path}/allCountries.txt";
        $start    = time();
        
        if( is_file($textfile) )
            cli::write("{$current_module->language->messages->already_unzipped}\n");
        else
            $this->unzip($zipfile, $textfile);
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' ignore into table geonames_countries_temp
            fields terminated by '\\t' lines terminated by '\\n'
            ( geoname_id, name, ascii_name, @dummy, latitude, longitude, feature_class, feature_code, country_code, @dummy,
              admin1_code, admin2_code, admin3_code, admin4_code, @dummy, @dummy, @dummy, timezone, @dummy )
        ";
        
        $database->exec("truncate geonames_countries_temp");
        $query_file = "{$path}/countries.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        try
        {
            /** @noinspection SqlInsertValues */
            $database->exec("
                insert ignore into geonames_countries
                select * from geonames_countries_temp
                where feature_class in ('A', 'P')
            ");
        }
        catch(\Exception $e)
        {
            cli::write("{$e->getMessage()}\n");
            
            die();
        }
        
        $res  = $database->query("select count(*) as `count` from geonames_countries");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        unlink($zipfile);
        $settings->set("modules:geonames.last_countries_update", date("Y-m-d H:i:s"));
    }
    
    private function integrate_admin1_codes()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $textfile = "{$path}/geonames-admin1_codes.txt";
        $start    = time();
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' replace into table geonames_admin1_codes
            fields terminated by '\\t' lines terminated by '\\n'
        ";
        
        $query_file = "{$path}/admin1_codes.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        $res  = $database->query("select count(*) as `count` from geonames_admin1_codes");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        $settings->set("modules:geonames.last_admin1_codes_update", date("Y-m-d H:i:s"));
    }
    
    private function integrate_admin2_codes()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $textfile = "{$path}/geonames-admin2_codes.txt";
        $start    = time();
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' replace into table geonames_admin2_codes
            fields terminated by '\\t' lines terminated by '\\n'
        ";
        
        $query_file = "{$path}/admin2_codes.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        $res  = $database->query("select count(*) as `count` from geonames_admin2_codes");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        $settings->set("modules:geonames.last_admin2_codes_update", date("Y-m-d H:i:s"));
    }
    
    private function integrate_altnames()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $zipfile  = "{$path}/geonames-altnames.zip";
        $textfile = "{$path}/alternateNamesV2.txt";
        $start    = time();
        
        if( is_file($textfile) )
            cli::write("{$current_module->language->messages->already_unzipped}\n");
        else
            $this->unzip($zipfile, $textfile);
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' replace into table geonames_altnames
            fields terminated by '\\t' lines terminated by '\\n'
            ( altname_id, geoname_id, iso_language, altname, is_preferred, is_short, @dummy, is_historic, @dummy, @dummy )
        ";
        
        $query_file = "{$path}/altnames.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        $res  = $database->query("select count(*) as `count` from geonames_altnames");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        unlink($zipfile);
        unlink("{$path}/iso-languagecodes.txt");
        $settings->set("modules:geonames.last_altnames_update", date("Y-m-d H:i:s"));
    }
    
    private function integrate_postal_codes()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $zipfile  = "{$path}/geonames-postal_codes.zip";
        $textfile = "{$path}/geonames-postal_codes/allCountries.txt";
        $start    = time();
        
        if( is_file($textfile) )
            cli::write("{$current_module->language->messages->already_unzipped}\n");
        else
            $this->unzip($zipfile, $textfile);
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' replace into table geonames_postal_codes
            fields terminated by '\\t' lines terminated by '\\n'
            ( country_code, postal_code, name,
              admin1_name, admin1_code, admin2_name, admin2_code, admin3_name, admin3_code,
              latitude, longitude, @dummy )
        ";
        
        $query_file = "{$path}/postal_codes.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        $res  = $database->query("select count(*) as `count` from geonames_postal_codes");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        rmdir(dirname($textfile));
        unlink($zipfile);
        $settings->set("modules:geonames.last_postal_codes_update", date("Y-m-d H:i:s"));
    }
    
    private function integrate_extras()
    {
        global $config, $modules, $database, $settings;
        $current_module = $modules["geonames"];
        
        set_time_limit(0);
        
        $path     = "{$config->datafiles_location}/tmp";
        $textfile = "{$path}/geonames-extras.csv";
        $start    = time();
        
        cli::write("{$current_module->language->messages->integrating_data} ");
        $query = "
            load data local infile '$textfile' replace into table geonames_extras character set utf8
            fields terminated by ',' optionally enclosed by '\"' lines terminated by '\\n'
            ignore 1 lines
            ( @dummy, dial_prefix, @dummy, @dummy, @dummy, @dummy, @dummy, @dummy, @dummy, 
              country_code, @dummy, @dummy, @dummy, @dummy, @dummy, @dummy, 
              @dummy, @dummy, currency_name, @dummy, 
              @dummy, @dummy, currency_symbol, 
              @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, @dummy, 
              @dummy, @dummy, @dummy, @dummy, @dummy, @dummy, languages, geoname_id, 
              name, @dummy )
        ";
        
        $query_file = "{$path}/extras.sql";
        file_put_contents($query_file, $query);
        $cmd = "mysql -u '{$this->db_user}' -p'{$this->db_pass}' -h '{$this->db_host}' -P '{$this->db_port}' "
             . "      {$this->db_db} < '$query_file' 2>&1";
        passthru($cmd, $res);
        if( ! empty($res) )
        {
            cli::write("\nMySQL aborted the operation.\n");
            
            die();
        }
        unlink($query_file);
        
        # Sanitization
        $database->exec("delete from geonames_extras where name = ''");
        
        $res  = $database->query("select count(*) as `count` from geonames_extras");
        $row  = $database->fetch_object($res);
        $cnt  = number_format($row->count);
        $secs = time() - $start;
        
        /** @noinspection PhpFormatFunctionParametersMismatchInspection */
        cli::write( sprintf("{$current_module->language->messages->integration_finished}\n", $secs, $cnt) );
        
        unlink($textfile);
        $settings->set("modules:geonames.last_extras_update", date("Y-m-d H:i:s"));
    }
    
    private function download($url, $filename)
    {
        global $config;
        
        set_time_limit(0);
        
        $dirname = dirname($filename);
        if( ! is_dir($dirname) )
        {
            if( ! @mkdir($dirname, 0777, true) ) throw new \Exception("Cannot create $dirname");
            
            @chmod($dirname, 0777);
        }
        
        $config->globals["@geonames:downloading_file_handler"] = fopen($filename, 'w+');
        $config->globals["@geonames:downloaded_file_size"]     = 0;
        $config->globals["@geonames:downloaded_mb"]            = 0;
        
        $user_agent = $_SERVER["HTTP_USER_AGENT"];
        if( empty($user_agent) ) $user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/99.0.4844.84 Safari/537.36";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $config->globals["@geonames:downloading_file_handler"]);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function(/** @noinspection PhpUnusedParameterInspection */ $cp, $data) {
            global $config;
            $len  = fwrite($config->globals["@geonames:downloading_file_handler"], $data);
            
            $config->globals["@geonames:downloaded_file_size"] += $len;
            
            $dloaded_mb = floor($config->globals["@geonames:downloaded_file_size"] / 1024 / 1024);
            if( $dloaded_mb > 0 && $dloaded_mb != $config->globals["@geonames:downloaded_mb"] ) cli::write( " • " );
            $config->globals["@geonames:downloaded_mb"] = $dloaded_mb;
            
            return $len;
        });
        
        curl_exec($ch);
        cli::write( " • " );
        fclose($config->globals["@geonames:downloading_file_handler"]);
        unset($config->globals["@geonames:downloading_file_handler"]);
        chmod($filename, 0777);
        
        if( curl_error($ch) ) throw new \Exception( curl_error($ch) );
        
        curl_close($ch);
    }
    
    private function unzip($zipfile, $textfile)
    {
        global $modules;
        $current_module = $modules["geonames"];
        
        $path = dirname($textfile);
        $zip  = new \ZipArchive;
        $res  = $zip->open($zipfile);
        if( ! $res )
        {
            /** @noinspection PhpFormatFunctionParametersMismatchInspection */
            cli::write(sprintf("{$current_module->language->messages->cannot_open_zip}\n", basename($zipfile)));
            
            die();
            
        }
        else
        {
            cli::write("{$current_module->language->messages->unzipping} ");
            if( ! $zip->extractTo($path) )
            {
                cli::write("{$current_module->language->messages->cannot_unzip}\n");
                
                die();
            }
            $size = number_format(filesize($textfile) / 1024 / 1024, 2);
            /** @noinspection PhpFormatFunctionParametersMismatchInspection */
            cli::write(sprintf("{$current_module->language->messages->unzipped}\n", $size));
            
            chmod($textfile, 0777);
            $zip->close();
        }
    }
}
