<?php

// If uninstall not called from WordPress exit
if( !defined( 'WP_UNINSTALL_PLUGIN' ) )
	exit();
	
// Delete option from option table
delete_option( 'ar404_settings' );	
delete_option( 'ar404_urls' );	
