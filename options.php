<?php

if ( ! defined( 'WPINC' ) ) die();

add_filter( 'p7mp_options_tabs', 'p7mp_default_options_tabs', 5, 1 );
function p7mp_default_options_tabs( $options_tabs ) {
	$options_tabs[ 'general' ] = array(
		'priority' => 10,
		'title'    => __( 'General', 'p7-mp' ),
	);
	$options_tabs[ 'payment' ] = array(
		'priority' => 20,
		'title'    => __( 'CC Payment Gateway', 'p7-mp' ),
	);
	$options_tabs[ 'usage' ] = array(
		'priority' => 30,
		'title'    => __( 'Usage', 'p7-mp' ),
	);

	return $options_tabs;
}

add_filter( 'p7mp_options_boxes', 'p7mp_default_options', 5, 1 );
function p7mp_default_options( $boxes ) {
	$boxes[ 'registration' ] = array(
		'priority' => 10,
		'title'    => __( 'Profile/Registration', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'google_recaptcha_site_key' => array(
				'priority'        => 180,
				'name'            => __( 'reCAPTCHA v2 Site Key', 'p7-mp' ),
				'type'            => 'text',
			),
			'google_recaptcha_secret_key' => array(
				'priority'        => 200,
				'name'            => __( 'reCAPTCHA v2 Secret Key', 'p7-mp' ),
				'type'            => 'text',
			),
		),
	);

	$boxes[ 'cc_processing' ] = array(
		'priority' => 15,
		'title'    => __( 'Payment Processing', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'cc_fee' => array(
				'priority'      => 10,
				'name'          => __( 'CC Fee (%)', 'p7-mp' ),
				'type'          => 'text',
			),
			'offline_payment' => array(
				'priority'      => 20,
				'name'          => __( 'Allow Offline Payment', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'no',
				'options'       => array(
					'yes' => __( 'Yes', 'p7-mp' ),
					'no'  => __( 'No',  'p7-mp' ),
				),
			),
			'offline_payment_message' => array(
				'priority'      => 30,
				'name'          => __( 'Offline Payment Message', 'p7-mp' ),
				'type'          => 'wysiwyg',
				'default'       => __( 'Please click the SUBMIT button to let us know that you would like to pay offline. We will send you an invoice that can be paid by check or over the phone. Your account will be activated after your payment is received.', 'p7-mp' ),
				'options'       => array(
					'wpautop'       => true,
					'media_buttons' => false,
					'textarea_rows' => 10,
					'teeny'         => true,
				),
			),
		),
	);

	$boxes[ 'content' ] = array(
		'priority' => 20,
		'title'    => __( 'Member Content', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'profiles_public' => array(
				'priority'      => 10,
				'name'          => __( 'Member Info Accessibility', 'p7-mp' ),
				'desc'          => __( 'Choose amount of member info to show to non-members.', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'basic',
				'options'       => array(
					'all'   => __( 'All',   'p7-mp' ),
					'basic' => __( 'Basic', 'p7-mp' ),
					'none'  => __( 'None',  'p7-mp' ),
				),
			),
			'include_login_when_restricted' => array(
				'priority'      => 30,
				'name'          => __( 'Show Login Box', 'p7-mp' ),
				'desc'          => __( 'Include a login box on the page when a logged-out user encounters restricted content.', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'yes',
				'options'       => array(
					'yes' => __( 'Yes', 'p7-mp' ),
					'no'  => __( 'No',  'p7-mp' ),
				),
			),
		),
	);

	$boxes[ 'map' ] = array(
		'priority' => 30,
		'title'    => __( 'Member Map', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'google_maps_api_key' => array(
				'priority'      => 10,
				'name'          => __( 'Google Maps API Key', 'p7-mp' ),
				'type'          => 'text',
			),
			'google_maps_center_lat' => array(
				'priority'      => 20,
				'name'          => __( 'Latitude', 'p7-mp' ),
				'type'          => 'text',
				'default'       => '37.09024',
			),
			'google_maps_center_lng' => array(
				'priority'      => 30,
				'name'          => __( 'Longitude', 'p7-mp' ),
				'type'          => 'text',
				'default'       => '-95.712891',
			),
			'google_maps_default_zoom' => array(
				'priority'      => 40,
				'name'          => __( 'Zoom', 'p7-mp' ),
				'type'          => 'text',
				'default'       => 4,
				'attributes'    => array(
					'type' => 'number',
					'min'  => 1,
					'max'  => 21,
				),
			),
			'google_maps_style' => array(
				'priority'      => 50,
				'name'          => __( 'Map Style', 'p7-mp' ),
				'type'          => 'select',
				'options'       => array(
					''          => 'Standard',
					'grayscale' => 'Grayscale',
				),
			),
		),
	);

	$boxes[ 'mailchimp' ] = array(
		'priority' => 35,
		'title'    => __( 'MailChimp', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'mailchimp_api_key' => array(
				'priority'      => 10,
				'name'          => __( 'MailChimp API Key', 'p7-mp' ),
				'type'          => 'text',
			),
			'mailchimp_list_id' => array(
				'priority'      => 20,
				'name'          => __( 'MailChimp List ID', 'p7-mp' ),
				'type'          => 'text',
			),
		),
	);

	$boxes[ 'constant_contact' ] = array(
		'priority' => 37,
		'title'    => __( 'Constant Contact', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'constant_contact_auth_token' => array(
				'priority'      => 10,
				'name'          => __( 'Connect to Constant Contact', 'p7-mp' ),
				'type'          => 'p7mp_constant_contact_auth',
			),
			'constant_contact_list_id' => array(
				'priority'      => 20,
				'name'          => __( 'Sync Members to List', 'p7-mp' ),
				'type'          => 'select',
				'options'       => 'p7mp_get_constant_contact_lists_select',
			),
		),
	);

	$boxes[ 'pages' ] = array(
		'priority' => 40,
		'title'    => __( 'Pages', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'page_profile' => array(
				'priority'      => 10,
				'name'          => __( 'Profile/Registration Form', 'p7-mp' ),
				'desc'          => __( 'The page that contains the shortcode: [member_profile]', 'p7-mp' ),
				'type'          => 'select',
				'options'       => 'p7mp_get_pages_select',
			),
			'page_payment_form' => array(
				'priority'      => 20,
				'name'          => __( 'Payment Form', 'p7-mp' ),
				'desc'          => __( 'The page that contains the shortcode: [member_payment_form]', 'p7-mp' ),
				'type'          => 'select',
				'options'       => 'p7mp_get_pages_select',
			),
			'page_member_directory' => array(
				'priority'      => 30,
				'name'          => __( 'Member Directory', 'p7-mp' ),
				'desc'          => __( 'The page that contains the shortcode: [member_directory]', 'p7-mp' ),
				'type'          => 'select',
				'options'       => 'p7mp_get_pages_select',
			),
		),
	);

	$boxes[ 'administration' ] = array(
		'priority' => 50,
		'title'    => __( 'Administration', 'p7-mp' ),
		'tab'      => 'general',
		'fields'   => array(
			'admin_contacts' => array(
				'priority'      => 10,
				'name'          => __( 'Admin Contact(s)', 'p7-mp' ),
				'desc'          => __( 'Send all emails generated by this plugin to the following recipients.', 'p7-mp' ),
				'type'          => 'group',
				'options'       => array(
					'sortable'      => true,
					'group_title'   => 'Contact #{#}',
					'add_button'    => __( 'Add Contact',    'p7-mp' ),
					'remove_button' => __( 'Remove Contact', 'p7-mp' ),
				),
			),
			'name' => array(
				'priority'      => 20,
				'parent'        => 'admin_contacts',
				'name'          => __( 'Name', 'p7-mp' ),
				'type'          => 'text',
				'default'       => get_bloginfo( 'name' ),
				'attributes'    => array(
					'required' => 'required',
				),
			),
			'email' => array(
				'priority'      => 30,
				'parent'        => 'admin_contacts',
				'name'          => __( 'Address', 'p7-mp' ),
				'type'          => 'text_email',
				'default'       => get_bloginfo( 'admin_email' ),
				'attributes'    => array(
					'required' => 'required',
				),
			),
			'type' => array(
				'priority'      => 40,
				'parent'        => 'admin_contacts',
				'name'          => __( 'Type', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'to',
				'options'         => array(
					'to'  => __( 'To',  'p7-mp' ),
					'cc'  => __( 'CC',  'p7-mp' ),
					'bcc' => __( 'BCC', 'p7-mp' ),
				),
			),
		),
	);

	$boxes[ 'payment_PayPal_Pro' ] = array(
		'priority' => 60,
		'title'    => __( 'PayPal Pro', 'p7-mp' ),
		'tab'      => 'payment',
		'fields'   => array(
			'payment_PayPal_Pro_enabled' => array(
				'priority'      => 10,
				'name'          => __( 'PayPal Pro Enabled', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'no',
				'options'       => array(
					'yes' => __( 'Yes', 'p7-mp' ),
					'no'  => __( 'No',  'p7-mp' ),
				),
			),
			'payment_PayPal_Pro_username' => array(
				'priority'      => 20,
				'name'          => __( 'PayPal Pro API Username', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_PayPal_Pro_password' => array(
				'priority'      => 30,
				'name'          => __( 'PayPal Pro API Password', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_PayPal_Pro_signature' => array(
				'priority'      => 40,
				'name'          => __( 'PayPal Pro API Signature', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_PayPal_Pro_testMode' => array(
				'priority'      => 50,
				'name'          => __( 'Use PayPal Pro in Test Mode', 'p7-mp' ),
				'type'          => 'checkbox',
			),
		),
	);

	$boxes[ 'payment_Stripe' ] = array(
		'priority' => 70,
		'title'    => __( 'Stripe', 'p7-mp' ),
		'tab'      => 'payment',
		'fields'   => array(
			'payment_Stripe_enabled' => array(
				'priority'      => 10,
				'name'          => __( 'Stripe Enabled', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'no',
				'options'       => array(
					'yes' => __( 'Yes', 'p7-mp' ),
					'no'  => __( 'No',  'p7-mp' ),
				),
			),
			'payment_Stripe_apiKey' => array(
				'priority'      => 20,
				'name'          => __( 'Stripe API Secret Key', 'p7-mp' ),
				'type'          => 'text',
			),
		),
	);

	$boxes[ 'payment_Payflow_Pro' ] = array(
		'priority' => 80,
		'title'    => __( 'PayPal Payflow Pro', 'p7-mp' ),
		'tab'      => 'payment',
		'fields'   => array(
			'payment_Payflow_Pro_enabled' => array(
				'priority'      => 10,
				'name'          => __( 'Payflow Pro Enabled', 'p7-mp' ),
				'type'          => 'radio_inline',
				'default'       => 'no',
				'options'       => array(
					'yes' => __( 'Yes', 'p7-mp' ),
					'no'  => __( 'No',  'p7-mp' ),
				),
			),
			'payment_Payflow_Pro_username' => array(
				'priority'      => 20,
				'name'          => __( 'Payflow Pro API Username', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_Payflow_Pro_password' => array(
				'priority'      => 30,
				'name'          => __( 'Payflow Pro API Password', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_Payflow_Pro_vendor' => array(
				'priority'      => 40,
				'name'          => __( 'Payflow Pro Vendor', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_Payflow_Pro_partner' => array(
				'priority'      => 50,
				'name'          => __( 'Payflow Pro Partner', 'p7-mp' ),
				'type'          => 'text',
			),
			'payment_Payflow_Pro_testMode' => array(
				'priority'      => 60,
				'name'          => __( 'Use Payflow Pro in Test Mode', 'p7-mp' ),
				'type'          => 'checkbox',
			),
		),
	);

	$boxes[ 'instructions' ] = array(
		'priority' => 90,
		'title'    => __( 'Usage Instructions', 'p7-mp' ),
		'tab'      => 'usage',
		'fields'   => array(
			'usage_instructions' => array(
				'priority'      => 10,
				'name'          => __( 'Usage Instructions', 'p7-mp' ),
				'type'          => 'title',
				'render_row_cb' => 'usage_instructions',
			),
		),
	);

	return $boxes;
}

?>
