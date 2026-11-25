<?php
define('CLUBDISPLAY_WEATHER_TEST',true);
require __DIR__.'/../public/weather.php';
$r=weatherRecord(['timestamp'=>'2026-06-01T12:00:00+02:00','temperature'=>23.5,'wind_speed'=>12,'precipitation_probability'=>0]);
if($r['temperature']!==23.5||$r['chance']!==0.0||$r['humidity']!==null)throw new RuntimeException('weather normalization');
if(weatherRecord(['timestamp'=>'invalid'])!==null)throw new RuntimeException('invalid date');
$s=weatherSun('2026-06-01');
if(!is_int($s['sunrise'])||$s['sunset']<=$s['sunrise'])throw new RuntimeException('sun');
echo "Weather tests OK\n";
