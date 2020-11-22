<?php
/*
Plugin Name: Paid Memberships Pro - Drip Ecommerce
Plugin URI: 
Description: Integrate directly with Drip Ecommerce
Version: .1
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
*/

class PMProDrip{

	function __construct(){

		if( !function_exists( 'pmpro_getOption' ) ){
			return;
		}

		$this->account_number = pmpro_getOption( 'pmpro_drip_account' );
		$this->api_key = pmpro_getOption( 'pmpro_drip_api_key' );

		add_action( 'pmpro_after_checkout', array( $this, 'after_checkout' ), 10, 2 );
		add_filter('pmpro_custom_advanced_settings', array( $this, 'advanced_settings' ), 99);

	}

	/**
	 * Makes a request to the Drip Ecommerce API
	 */
	function drip_request( $endpoint = '', $body = array(), $action = 'POST' ){

		$args = array(
			'headers' => array(
				'Content-Type: application/json',
				'User-Agent: WP Updatr (wpupdatr.com)',
				'Authorization' => 'Basic '.base64_encode( $this->api_key.':' )
				
			),			
		);

		if( !empty( $body ) ){
			$args['body'] = json_encode( $body );
		}

		if( $action == 'GET' ){
			$response = wp_remote_get( 'https://api.getdrip.com'.$endpoint, $args );
		} else {
			$response = wp_remote_post( 'https://api.getdrip.com'.$endpoint, $args );
		}

		if ( is_wp_error( $response ) ) {
		    $error_message = $response->get_error_message();
		    echo "Something went wrong: $error_message";
		} else {
			return json_decode( wp_remote_retrieve_body( $response ) );
		}

	}

	/**
	 * Adds fields to the PMPro Advanced Settings page
	 */
	function advanced_settings(){

		$accounts = $this->drip_request( '/v2/accounts/', array(), 'GET' );		

		$fields['pmpro_drip_api_key'] = array(
			'field_name' => 'pmpro_drip_api_key',
			'field_type' => 'text',
			'label' => __( 'Drip Ecommerce API Key', 'pmpro-drip' ),
			'description' => __( 'Navigate to User Settings.', 'pmpro-membership-maps')
		);

		if( defined( 'PMPRO_VERSION' ) ){
			if( version_compare( PMPRO_VERSION, '2.4.2', '>=' ) ){
				$fields['pmpro_drip_api_key']['description'] = sprintf( __( 'Navigate to User Settings. %s', 'pmpro-membership-maps' ), '<a href="https://www.getdrip.com/user/edit" target="_BLANK">'.__( 'Obtain Your API Key', 'pmpro-drip' ).'</a>' );
			}
		}

		$options = array( '' => __( 'Select an Account', 'pmpro-drip' ) );

		if( !empty( $accounts->accounts ) ){
			foreach( $accounts->accounts as $account ){
				$options[$account->id] = $account->name;
			}
		}

		$fields['pmpro_drip_account'] = array(
			'field_name' => 'pmpro_drip_account',
			'field_type' => 'select',
			'options' => $options,
			'label' => __( 'Drip Ecommerce Default Account', 'pmpro-drip' ),
			'description' => __( 'Preferred Drip Account.', 'pmpro-membership-maps')
		);

		return $fields;

	}	

	/**
	 * Processes a purchase after checkout - sends data to Drip
	 */
	function after_checkout( $user_id, $morder ){

		global $pmpro_currency;
		
		$timestamp = current_time( 'timestamp' );

		$items_array = array();

		$items_array[] = array(
			'product_id' => $morder->membership_id,
			'name' => $morder->membership_name,
			'price' => $morder->InitialPayment,
			'total' => $morder->membership_level->initial_payment
		);

		$args = array(
			'provider' => 'paid-memberships-pro',
			'email' => $morder->Email,
			'initial_status' => 'active',
			'action' => 'paid',
			'occurred_at' => date("Y-m-d", $timestamp ) . 'T' . date("H:i:s", $timestamp ) .'+00:00',
			'order_id' => $morder->code,
			'grand_total' => floatval( $morder->total ),
			'currency' => $pmpro_currency,
			'order_url' => get_admin_url( 'admin.php?page=pmpro-orders&order='.$morder->code ),
			'items' => $items_array,			
		);

		//if billing address enabled
		if( !empty( $morder->billing ) ){
			$args['billing_address'] = array(
				'label' => __('Billing Address', 'pmpro-drip'),
				'first_name' => $morder->FirstName,
				'last_name' => $morder->LastName,
				'address_1' => $morder->billing->street,
				'city' => $morder->billing->city,
				'state' => $morder->billing->state,
				'postal_code' => $morder->billing->zip,
				'country' => $morder->billing->country,
				'phone' => $morder->billing->phone
			);
		}

		$args = apply_filters( 'pmpro_drip_new_order_array', $args, $morder );

		$this->drip_request( '/v3/'.$this->account_number.'/shopper_activity/order', $args, 'POST' );

	}

}

new PMProDrip();