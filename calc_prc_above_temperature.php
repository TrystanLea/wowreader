<?php

// Script for calculating 
// A: Hourly dry-bulb temperature equal or exceeded for 99.0% of the hours in a year
// B: Hourly dry-bulb temperature equal or exceeded for 99.6% of the hours in a year
// from outside temperature data in a PHPFina timeseries file
//
// Part of the OpenEnergyMonitor.org project
// Licence: GPL
// Author: Trystan Lea

$datadir = "/var/lib/phpfina/";
$feedid = 90;

$fh = fopen($datadir."$feedid.dat", 'rb');
$meta = get_meta($datadir,$feedid);

$hours_at = array();
$total_hours = 0;

// For all data points in the PHPFina file
for ($n=0; $n<$meta->npoints; $n++) {
    // Read a datapoint from the PHPFina data file
    $tmp = unpack("f",fread($fh,4));
    $val = $tmp[1];
    
    if (!is_nan($val)) {
        // Allocate to 0.1C resolution
        $temperature = "".(round($val*10.0) / 10.0);
        if (!isset($hours_at[$temperature])) $hours_at[$temperature] = 0;
        $hours_at[$temperature] += ($meta->interval / 3600);
        $total_hours += ($meta->interval / 3600);
    }
}

// Sort by temperature ascending
ksort($hours_at);

// Calculate and display percentage of hours above temperature
$sum = 0;
foreach ($hours_at as $temperature=>$hours) {
    // print $temperature." ".$hours."\n";
    $sum += $hours;
    $prc = 1.0 - ($sum / $total_hours);
    $prc = round($prc * 10000) / 10000;
    if ($prc>=0.996 && $prc<0.997) print $prc." ".$temperature."\n";    
    if ($prc>=0.990 && $prc<0.991) print $prc." ".$temperature."\n";
}
    
// -----------------------------------------------------    
    
function get_meta($datadir,$id) {
    $meta = new stdClass();
    $metafile = fopen($datadir.$id.".meta", 'rb');
    fseek($metafile,8);
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->interval = $tmp[1];
    $tmp = unpack("I",fread($metafile,4)); 
    $meta->start_time = $tmp[1];
    fclose($metafile);
    
    $meta->npoints = floor(filesize($datadir.$id.".dat") / 4.0);
    $meta->end_time = $meta->start_time + ($meta->interval*$meta->npoints);
    
    return $meta;
}
