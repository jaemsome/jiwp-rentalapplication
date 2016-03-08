<?php

/*
Plugin Name: KMI rental application
Plugin URI: 
Description: Plugin to manage user's rental application.
Author: KMI
Version: 1.0
Author URI: 
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly

require_once 'rental_application.php';

register_activation_hook(__FILE__, 'kmi_activate_rental_application');

function kmi_activate_rental_application()
{
    error_log('KMI rental application activated.');
}

register_deactivation_hook(__FILE__, 'kmi_deactivate_rental_application');

function kmi_deactivate_rental_application()
{
    error_log('KMI rental application deactivated.');
}