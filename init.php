<?php
/*
Plugin Name: SX AS3 Media
Description: "SX AS3 Media" is FREE media storage plugin. Allows easily connect Amazon S3 bucket to any wordpress site in minutes and use it as alternative/backup media storage.
Version: 1.0.0
Author: Skynix Team
Author URI: https://skynix.co/
License: GPL
*/

/*  Copyright 2019  Skynix LLC  (email: apps@skynix.co)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once __DIR__ . '/vendor/autoload.php';

// load classes
spl_autoload_register( function ( $class_name ) {
    $classes_dir = plugin_dir_path( __FILE__ ) . '/classes/class-';
    $file = $classes_dir . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
    if( file_exists( $file ) ) require_once( $file );
} );

global $sxms_aws;
$sxms_aws = new SXMS_AWS(["textdomain" => "skynix-s3-media-storage"]);
$sxms_aws->init();
