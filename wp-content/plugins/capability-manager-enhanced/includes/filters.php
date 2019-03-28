<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * class CME_Extensions
 * 
 * Load filters and actions for integration with third party plugins
 */
class CME_Extensions {
	var $extensions = array();
	
	function add( $object ) {
		if ( ! is_object( $object ) ) return;
		
		$this->extensions[ get_class( $object ) ] = $object;
	}
}

global $cme_extensions;
$cme_extensions = new CME_Extensions();

if ( defined( 'WC_PLUGIN_FILE' ) ) {
	require_once ( dirname(__FILE__) . '/filters-woocommerce.php' );
	$cme_extensions->add( new CME_WooCommerce() );
}