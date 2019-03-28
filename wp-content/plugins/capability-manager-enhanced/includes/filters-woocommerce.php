<?php
/**
 * class CME_WooCommerce
 * 
 * Uses WordPress or Woo API to adjust WooCommerce permissions
 */
class CME_WooCommerce {
	function __construct() {
		// Implement duplicate_product capability automatically if current user has it in role.
		global $current_user;
		if ( ! empty( $current_user->allcaps['duplicate_products'] ) ) {
			add_filter( 'woocommerce_duplicate_product_capability', array( &$this, 'implement_duplicate_product_cap' ) );
		}
	}
	
	function implement_duplicate_product_cap( $cap ) {
		return 'duplicate_products';
	}
}