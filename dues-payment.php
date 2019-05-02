<?php

if ( ! defined( 'WPINC' ) ) die();

add_filter( 'p7mp_post_types', 'p7mp_register_dues_payment', 5, 1 );
function p7mp_register_dues_payment( $post_types ) {
	$post_types[ 'dues_payment' ] = array(
		'label'               => __( 'Dues Payments', 'p7-mp' ),
		'labels'              => array(
			'name'               => __( 'Dues Payments',            'p7-mp' ),
			'singular_name'      => __( 'Dues Payment',             'p7-mp' ),
			'menu_name'          => __( 'Dues Payments',            'p7-mp' ),
			'all_items'          => __( 'All Payments',             'p7-mp' ),
			'not_found'          => __( 'No Payments',              'p7-mp' ),
			'not_found_in_trash' => __( 'No Payments in the Trash', 'p7-mp' ),
			'search_items'       => __( 'Search Payments',          'p7-mp' ),
			'add_new'            => __( 'Add New',                  'p7-mp' ),
			'add_new_item'       => __( 'Add New Dues Payment',     'p7-mp' ),
		),
		'description'         => '',
		'public'              => false,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_rest'        => false,
		'rest_base'           => '',
		'has_archive'         => false,
		'show_in_menu'        => true,
		'exclude_from_search' => true,
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'hierarchical'        => false,
		'query_var'           => true,
		'menu_position'       => 71,
		'menu_icon'           => 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAzMiAzMiIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBmaWxsPSIjOWVhM2E4IiBkPSJNMTggMjJjLTEuMTA0IDAtMi0wLjg5Ni0yLTJ2LTJjMC0xLjEwNSAwLjg5Ni0yIDItMmgxMnY2aC0xMnpNMTkuNSAxNy41ODNjLTAuODI4IDAtMS41IDAuNjcyLTEuNSAxLjUgMCAwLjgyOSAwLjY3MiAxLjUgMS41IDEuNSAwLjgyOSAwIDEuNS0wLjY3MSAxLjUtMS41IDAtMC44MjgtMC42NzEtMS41LTEuNS0xLjV6TTE1IDE4djJjMCAxLjY1NiAxLjM0NCAzIDMgM2gxMHY0YzAgMS42NTYtMS4zNDMgMy0zIDNoLTIxYy0xLjY1NiAwLTMtMS4zNDQtMy0zdi0xOWMwLTEuNjU3IDEuMzQ0LTMgMy0zaDkuNjMybC0yLjM0OSAwLjk3OWgtNy43ODJjLTAuODI4IDAtMS41IDAuNjcyLTEuNSAxLjVzMC42NzIgMS41IDEuNSAxLjVoMjQuNDk5djYuMDIxaC0xMGMtMS42NTYgMC0zIDEuMzQzLTMgM3pNNS4wMDEgMTFoLTJ2Mmgxdi0xaDF2LTF6TTUuMDAxIDE0aC0ydjJoMS4wNDFsLTAuMDQxLTEgMSAwLjAyMXYtMS4wMjF6TTUuMDAxIDE3aC0ydjJoMS4wMjFsLTAuMDIxLTFoMXYtMXpNNS4wMDEgMjBoLTJ2MmgxLjAyMWwtMC4wMjEtMSAxIDAuMDIxdi0xLjAyMXpNNS4wMDEgMjNoLTJ2Mmgxdi0xaDF2LTF6TTUuMDAxIDI2aC0ydjJoMS4wMjFsLTAuMDIxLTAuOTc5aDF2LTEuMDIxek05LjAwMSA3LjkzOGwxMS43NS00LjkzOCAyLjI1IDQuOTM4aC0xNHpNMjIuNjQ2IDVoMi4zNTRjMC44NzYgMCAxLjY1NiAwLjM4MSAyLjIwNSAwLjk3OWgtNC4wODFsLTAuNDc4LTAuOTc5eiIgLz48L3N2Zz4=',
		'supports'            => array( 'title' ),
		'rewrite'             => array(
			'slug'       => 'dues-payments',
			'with_front' => false,
		),
		'fields'              => array(
			'amount' => array(
				'name'         => __( 'Amount', 'p7-mp' ),
				'type'         => 'text_money',
				'admin_column' => true,
			),
			'cc_first_name' => array(
				'name'         => __( 'CC First Name', 'p7-mp' ),
				'dues_field'   => 'cc_first_name',
				'type'         => 'text',
				'admin_column' => true,
			),
			'cc_last_name' => array(
				'name'         => __( 'CC Last Name', 'p7-mp' ),
				'dues_field'   => 'cc_last_name',
				'type'         => 'text',
				'admin_column' => true,
			),
			'member_account' => array(
				'name'         => __( 'Member Account', 'p7-mp' ),
				'dues_field'   => 'member_account',
				'description'  => 'This must be an exact and valid username.',
				'type'         => 'text',
				'admin_column' => true,
				'admin_filter' => true,
			),
			'cc_expiry_month' => array(
				'name'         => __( 'CC Expiry Month', 'p7-mp' ),
				'dues_field'   => 'cc_expiry_month',
				'type'         => 'text',
				'attributes'   => array(
					'type' => 'number',
					'min'  => 1,
					'max'  => 12,
				),
			),
			'cc_expiry_year' => array(
				'name'         => __( 'CC Expiry Year', 'p7-mp' ),
				'dues_field'   => 'cc_expiry_year',
				'type'         => 'text',
				'attributes'   => array(
					'type' => 'number',
					'min'  => date( 'Y' ),
				),
			),
		),
	);

	return $post_types;
}

add_action( 'save_post_p7mp_dues_payment', 'p7mp_dues_payment_save', 5, 3 );
function p7mp_dues_payment_save( $post_ID, $post, $update ) {
	if ( ! $update ) return;

	if ( 'Payment #' . (string) $post->ID !== $post->post_title ) {
		$post->post_title = 'Payment #' . (string) $post->ID;
		$post->post_name  = 'payment-'  . (string) $post->ID;
		wp_update_post( $post );
		return;
	}

	if ( 'private' !== $post->post_status && 'trash' !== $post->post_status ) {
		$post->post_status = 'private';
		wp_update_post( $post );
		return;
	}
}

add_filter( 'p7mp_payment_form', 'p7mp_default_payment_form', 5, 1 );
function p7mp_default_payment_form( $fields ) {
	$fields[ 'section_member_type' ] = array(
		'priority'      => 100,
		'name'          => __( 'Membership', 'p7-mp' ),
		'type'          => 'title',
		'render_row_cb' => 'open_fieldset',
	);

	$fields[ 'member_account' ] = array(
		'priority'      => 120,
		'name'          => __( 'Member Account', 'p7-mp' ),
		'email'         => true,
		'type'          => 'select',
		'visibility'    => array( 'non-member' ),
		'options'       => 'p7mp_get_members_select',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'member_type' ] = array(
		'priority'      => 140,
		'name'          => __( 'Member Type', 'p7-mp' ),
		'email'         => true,
		'type'          => 'select',
		'default'       => 'usr_member_type',
		'options'       => 'p7mp_get_member_types_select_with_price',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'payment_method' ] = array(
		'priority'      => 145,
		'name'          => __( 'Payment Method', 'p7-mp' ),
		//'description'   => 'A fee will be added.',
		'type'          => 'radio_inline',
		'visibility'    => array(
			array(
				'option' => 'offline_payment',
				'value'  => 'yes',
			),
		),
		'default'       => 'credit_card',
		'options'       => array(
			'credit_card' => 'Credit Card',
			'offline'     => 'Offline Payment',
		),
	);

	$fields[ 'section_your_info' ] = array(
		'priority'      => 150,
		'name'          => __( 'Your Info', 'p7-mp' ),
		'type'          => 'title',
		'visibility'    => array( 'non-member' ),
		'render_row_cb' => 'open_fieldset',
	);

	$fields[ 'email' ] = array(
		'priority'      => 155,
		'name'          => __( 'Email Address', 'p7-mp' ),
		'type'          => 'text_email',
		'visibility'    => array( 'non-member' ),
		'validator'     => 'p7mp_validate_email',
		'attributes'  => array(
			'required' => 'required',
		),
	);

	$fields[ 'section_offline_payment_info' ] = array(
		'priority'      => 156,
		'name'          => __( 'Offline Payment Info', 'p7-mp' ),
		'email'         => true,
		'type'          => 'title',
		'visibility'    => array(
			array(
				'option' => 'offline_payment',
				'value'  => 'yes',
			),
		),
		'render_row_cb' => 'open_fieldset',
	);

	$fields[ 'section_cc_info' ] = array(
		'priority'      => 160,
		'name'          => __( 'Credit Card Info', 'p7-mp' ),
		'email'         => true,
		'type'          => 'title',
		'render_row_cb' => 'open_fieldset',
	);

	$fields[ 'cc_first_name' ] = array(
		'priority'      => 180,
		'name'          => __( 'First Name', 'p7-mp' ),
		'cc_field'      => 'firstName',
		'email'         => true,
		'type'          => 'text',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_last_name' ] = array(
		'priority'      => 200,
		'name'          => __( 'Last Name', 'p7-mp' ),
		'cc_field'      => 'lastName',
		'email'         => true,
		'type'          => 'text',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_number' ] = array(
		'priority'      => 220,
		'name'          => __( 'Credit Card Number', 'p7-mp' ),
		'cc_field'      => 'number',
		'email'         => 'p7mp_last_4',
		'type'          => 'text',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_expiry_month' ] = array(
		'priority'      => 240,
		'name'          => __( 'Month', 'p7-mp' ),
		'cc_field'      => 'expiryMonth',
		'email'         => true,
		'type'          => 'select',
		'options'       => 'p7mp_get_month_select',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_expiry_year' ] = array(
		'priority'      => 260,
		'name'          => __( 'Year', 'p7-mp' ),
		'cc_field'      => 'expiryYear',
		'email'         => true,
		'type'          => 'select',
		'options'       => 'p7mp_get_cc_expiry_year_select',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_cvv' ] = array(
		'priority'      => 280,
		'name'          => __( 'CVV', 'p7-mp' ),
		'cc_field'      => 'cvv',
		'type'          => 'text',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_address' ] = array(
		'priority'      => 300,
		'name'          => __( 'Billing Address', 'p7-mp' ),
		'cc_field'      => 'billingAddress1',
		'type'          => 'text',
		'default'       => 'usr_company_street',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_city' ] = array(
		'priority'      => 320,
		'name'          => __( 'City', 'p7-mp' ),
		'cc_field'      => 'billingCity',
		'type'          => 'text',
		'default'       => 'usr_company_city',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_state' ] = array(
		'priority'      => 340,
		'name'          => __( 'State', 'p7-mp' ),
		'cc_field'      => 'billingState',
		'type'          => 'select',
		'default'       => 'usr_company_state',
		'options'       => 'p7mp_get_us_states_select',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'cc_zip' ] = array(
		'priority'      => 360,
		'name'          => __( 'Zip Code', 'p7-mp' ),
		'cc_field'      => 'billingPostcode',
		'type'          => 'text',
		'default'       => 'usr_company_zip',
		'attributes'    => array(
			'required' => 'required',
		),
	);

	$fields[ 'section_end_form' ] = array(
		'priority'      => 380,
		'name'          => __( 'End Form', 'p7-mp' ),
		'type'          => 'title',
		'render_row_cb' => 'close_fieldset',
	);

	return $fields;
}

function p7mp_last_4( $str ) {
	$prefix = str_repeat( '*', strlen( $str ) - 4 );
	return $prefix . substr( $str, -4 );
}

?>
