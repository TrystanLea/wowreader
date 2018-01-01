<?php

// Script for reading historic wow.metoffice.gov.uk station data
// Part of the OpenEnergyMonitor.org project
// Licence: GPL
// Author: Trystan Lea

// Send data to emoncms account
$emoncmshost = "http://localhost/emoncms";
$apikey = "EMONCMS_APIKEY";

// Douglas Arms Bethesda, North Wales, UK
$siteid = 17538268;

// Emoncms logging requied by PHPFina
define('EMONCMS_EXEC', 1);
$log_filename = "phpfina.log"; $log_enabled = true; $log_level = 2;
require "EmonLogger.php";

// Posting data is faster direct rather than via emoncms api
// so we use the PHPFina engine file to post the data directly to /var/lib/phpfina
require "PHPFina.php";
$phpfina = new PHPFina(array("datadir"=>"/var/lib/phpfina/"));

// Start loading data from here
$start_datetime = "2016-01-01 00:00:00";
$start = strtotime($start_datetime);
$end = $start + (3600*24*5);

// Fetch emoncms feed list and sort by name
$feeds = json_decode(file_get_contents("$emoncmshost/feed/list.json?apikey=$apikey"));
$feeds_by_name = array();
foreach ($feeds as $feed) $feeds_by_name[$feed->name] = $feed;

// Make 100 consequative requests
for ($n=0; $n<100; $n++)
{
    // Convert timestamp to date
    $firstDate = date("Y-m-d", $start);
    $lastDate = date("Y-m-d", $end);
    
    // Construct query string
    $query = "https://wow.metoffice.gov.uk/observations/details/tableview/$siteid/export?firstDate=$firstDate&lastDate=$lastDate";
    print $query."\n";
    
    // Attempt request 3 times
    for ($r=0; $r<3; $r++) {
        $file = file_get_contents($query);
        $lines = explode("\n",$file);
        if (isset($lines[1])) break;
        sleep(1);
        print "retry\n";
    }
    
    print "rx: ".strlen($file)." bytes\n";
    
    // Read keys from 2nd line in file
    $keys = explode(",",trim($lines[1]));
    
    // Read through data lines
    for ($l=2; $l<count($lines); $l++)
    {
        $row = explode(",",trim($lines[$l]));
        $data = array();
        
        for ($i=0; $i<count($row); $i++) {
            // Filter key for tidier names (probably not needed)
            $key = preg_replace('/[^\p{N}\p{L}]/u','',$keys[$i]);
            $val = $row[$i];
            
            // Create key:value pairs for this line
            if ($val!="" && $key!="Id" && $key!="SiteId") {
                $data[$key] = $val;
            }
        }
        
        // Read the ReportDateTime and convert to timestamp
        if (isset($data["ReportDateTime"])) {
            $datetime = $data["ReportDateTime"];
            $timestamp = strtotime($datetime);
            print $datetime."\n";
            unset ($data["ReportDateTime"]);
            
            // Itterate through remaining data values
            // Write data to PHPFina feed 
            foreach ($data as $key=>$val) {
                $feedname = $siteid."_".$key;
                
                if (!isset($feeds_by_name[$feedname])) {
                    $result = json_decode(file_get_contents("$emoncmshost/feed/create.json?tag=$siteid&name=$feedname&datatype=1&engine=5&options=".'{"interval":1800}'."&apikey=$apikey"));
                    $feedid = $result->feedid;
                    $feeds_by_name[$feedname]->id = $feedid;
                    
                    // Set start date of phpfina feed to 2013 giving plenty of scope to request historic data
                    // Uncomment to use with a remote emoncms installation, not recommended 
                    // file_get_contents("$emoncmshost/feed/insert.json?id=$feedid&time=".strtotime("2013-01-01 00:00:00")."&value=0&apikey=$apikey");
                    $phpfina->post($feedid,strtotime("2013-01-01 00:00:00"),0);
                } else {
                    $feedid = $feeds_by_name[$feedname]->id;
                }
                
                $value = (float) $data[$key];
                // Uncomment to use with a remote emoncms installation, not recommended 
                // file_get_contents("$emoncmshost/feed/insert.json?id=$feedid&time=$timestamp&value=$value&apikey=$apikey");
                $phpfina->post($feedid,$timestamp,$value);
            }
        }
    }

    print "waiting...\n";
    // Dont poll wow api too fast
    sleep(5);
    
    // Advance request to the next 5 days
    $start = $start + (3600*24*6);
    $end = $start + (3600*24*5);
}


