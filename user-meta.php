<?php

if ( ! defined( 'WPINC' ) ) die();

add_filter( 'p7mp_user_taxonomies', 'p7mp_default_user_taxonomies', 5, 1 );
function p7mp_default_user_taxonomies( $taxonomies ) {
	// Membership Level
	$taxonomies[ 'membership_level' ] = array(
		'public' => true,
		'single_value' => true,
		'show_admin_column' => true,
		'labels' => array(
			'name'                       => __( 'Membership Levels',                              'p7-mp' ),
			'singular_name'              => __( 'Membership Level',                               'p7-mp' ),
			'menu_name'                  => __( 'Membership Levels',                              'p7-mp' ),
			'search_items'               => __( 'Search Membership Levels',                       'p7-mp' ),
			'popular_items'              => __( 'Popular Membership Levels',                      'p7-mp' ),
			'all_items'                  => __( 'All Membership Levels',                          'p7-mp' ),
			'edit_item'                  => __( 'Edit Membership Level',                          'p7-mp' ),
			'update_item'                => __( 'Update Membership Level',                        'p7-mp' ),
			'add_new_item'               => __( 'Add New Membership Level',                       'p7-mp' ),
			'new_item_name'              => __( 'New Membership Level Name',                      'p7-mp' ),
			'not_found'                  => __( 'No Membership Levels Found',                     'p7-mp' ),
			'back_to_items'              => __( 'Back to Membership Levels',                      'p7-mp' ),
			'separate_items_with_commas' => __( 'Separate membership levels with commas',         'p7-mp' ),
			'add_or_remove_items'        => __( 'Add or remove membership levels',                'p7-mp' ),
			'choose_from_most_used'      => __( 'Choose from the most popular membership levels', 'p7-mp' ),
		),
		'rewrite' => array(
			'with_front' => true,
			'slug'       => 'members/levels',
		),
		'capabilities' => array(
			'manage_terms' => 'edit_users',
			'edit_terms'   => 'edit_users',
			'delete_terms' => 'edit_users',
			'assign_terms' => 'read',
		),
		'fields' => array(
			'dues_amount' => array(
				'name'    => __( 'Dues Amount', 'p7-mp' ),
				'type'    => 'text_money',
				'default' => '1.00',
			),
			'access_setting' => array(
				'name'    => __( 'Content Access Setting', 'p7-mp' ),
				'type'    => 'radio_inline',
				'default' => 'yes',
				'options' => array(
					'yes' => 'Yes',
					'no'  => 'No',
				),
			),
			'priority' => array(
				'name'            => __( 'Priority', 'p7-mp' ),
				'type'            => 'text',
				'sanitization_cb' => 'absint',
				'default'         => '1',
				'attributes'      => array(
					'type'    => 'number',
					'min'     => 1,
				),
			),
		),
		'default' => array(
			array(
				'name'           => __( 'Standard Member', 'p7-mp' ),
				'dues_amount'    => '100.00',
				'access_setting' => 'yes',
				'priority'       => 10,
			),
			array(
				'name'           => __( 'Premium Member',  'p7-mp' ),
				'dues_amount'    => '200.00',
				'access_setting' => 'yes',
				'priority'       => 20,
			),
		),
	);

	return $taxonomies;
}

add_filter( 'p7mp_post_types', 'p7mp_register_member', 5, 1 );
function p7mp_register_member( $post_types ) {
	$post_types[ 'member' ] = array(
		'label'               => __( 'Members', 'p7-mp' ),
		'labels'              => array(
			'name'               => __( 'Members',                 'p7-mp' ),
			'singular_name'      => __( 'Member',                  'p7-mp' ),
			'menu_name'          => __( 'Members',                 'p7-mp' ),
			'all_items'          => __( 'All Members',             'p7-mp' ),
			'not_found'          => __( 'No Members',              'p7-mp' ),
			'not_found_in_trash' => __( 'No Members in the Trash', 'p7-mp' ),
			'search_items'       => __( 'Search Members',          'p7-mp' ),
			'add_new'            => __( 'Add New',                 'p7-mp' ),
			'add_new_item'       => __( 'Add New Member',          'p7-mp' ),
		),
		'description'         => '',
		'public'              => true,
		'publicly_queryable'  => true,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'rest_base'           => '',
		'has_archive'         => false,
		'show_in_menu'        => false,
		'exclude_from_search' => false,
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
		'hierarchical'        => false,
		'query_var'           => true,
		'supports'            => array( 'title' ),
		'rewrite'             => array(
			'slug'       => 'members',
			'with_front' => false,
		),
	);

	return $post_types;
}

add_action( 'profile_update', 'p7mp_add_member_post', 10, 1 );
add_action( 'user_register',  'p7mp_add_member_post', 10, 1 );
function p7mp_add_member_post( $user_id ) {
	$userdata = get_userdata( $user_id );
	$member   = in_array( 'p7mp', $userdata->roles ) ? true : false;

	$post = p7mp_get_member_post( $user_id );
	if ( $post && ! $member ) {
		wp_delete_post( $post->ID, true );
	} else if ( $member ) {
		$status = 'active' === get_user_meta( $user_id, 'p7mp_u_member_status', true ) ? 'publish' : 'draft';
		$status = is_admin() && 'active' === $_POST[ 'p7mp_u_member_status' ] ? 'publish' : $status;
		
		$args = array(
			'post_type'      => 'p7mp_member',
			'post_author'    => $user_id,
			'post_title'     => $userdata->display_name,
			'post_name'      => $userdata->display_name,
			'post_status'    => $status,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);
		if ( $post ) $args[ 'ID' ] = $post->ID;
		wp_insert_post( $args );
	}

	update_user_meta( $user_id, 'p7mp_u_login', $userdata->user_login );
}

add_action( 'delete_user', 'p7mp_remove_member_post', 10, 1 );
function p7mp_remove_member_post( $user_id ) {
	$post = p7mp_get_member_post( $user_id );
	if ( $post ) {
		wp_delete_post( $post->ID, true );
	}
}

function p7mp_get_member_post( $user_id ) {
	$args = array(
		'post_type'   => 'p7mp_member',
		'post_status' => 'any',
		'numberposts' => 1,
		'author'      => $user_id,
	);
	$posts = get_posts( $args );

	if ( ! empty( $posts ) ) return $posts[ 0 ];

	return false;
}

add_filter( 'p7mp_user_meta', 'p7mp_default_user_meta', 5, 1 );
function p7mp_default_user_meta( $fields ) {
	$fields[ 'member_status' ] = array(
		'priority'        => 100,
		'name'            => __( 'Membership Status', 'p7-mp' ),
		'type'            => 'radio_inline',
		'import'          => true,
		'export'          => true,
		'default'         => 'inactive',
		'visibility'      => array( 'admin' ),
		'admin_column'    => true,
		'admin_filter'    => true,
		'options'         => array(
			'active'   => __( 'Active',   'p7-mp' ),
			'inactive' => __( 'Inactive', 'p7-mp' ),
		),
	);

	$fields[ 'member_date_joined' ] = array(
		'priority'        => 120,
		'name'            => __( 'Member Join Date', 'p7-mp' ),
		'type'            => 'text_date',
		'import'          => true,
		'export'          => true,
		'default'         => date( 'Y-m-d' ),
		'visibility'      => array( 'admin' ),
		'date_format'     => 'Y-m-d',
	);

	$fields[ 'member_expiry' ] = array(
		'priority'        => 140,
		'name'            => __( 'Member Expiration Date', 'p7-mp' ),
		'type'            => 'text_date',
		'import'          => true,
		'export'          => true,
		'default'         => date( 'Y-m-d' ),
		'visibility'      => array( 'admin' ),
		'date_format'     => 'Y-m-d',
	);

	$fields[ 'form_title' ] = array(
		'priority'        => 160,
		'name'            => array(
			'admin'      => __( 'Update Member Profile', 'p7-mp' ),
			'member'     => __( 'Update Member Profile', 'p7-mp' ),
			'non-member' => __( 'Create New Member Profile', 'p7-mp' ),
		),
		'type'            => 'title',
		'render_row_cb'   => 'form_heading',
	);

	$fields[ 'section_member_info' ] = array(
		'priority'        => 180,
		'name'            => __( 'Member Info', 'p7-mp' ),
		'type'            => 'title',
		'visibility'      => array( 'member', 'non-member' ),
		'render_row_cb'   => 'open_fieldset',
	);

	$fields[ 'member_name' ] = array(
		'priority'        => 200,
		'name'            => __( 'Member Name', 'p7-mp' ),
		'wp_user_field'   => array( 'display_name', 'nickname', 'first_name' ),
		'cc_sub_field'    => 'first_name',
		'type'            => 'text',
		'import'          => true,
		'export'          => true,
		'notification'    => true,
		'admin_column'    => true,
		'attributes'      => array(
			'required' => 'required',
		),
	);

	$fields[ 'member_email' ] = array(
		'priority'        => 220,
		'name'            => __( 'Member Email', 'p7-mp' ),
		'wp_user_field'   => 'user_email',
		'mc_sub_field'    => 'email_address',
		'cc_sub_field'    => 'email',
		'type'            => 'text_email',
		'import'          => true,
		'export'          => true,
		'notification'    => true,
		'validator'       => 'p7mp_validate_email',
		'attributes'  => array(
			'required' => 'required',
		),
	);

	$fields[ 'member_description' ] = array(
		'priority'        => 240,
		'name'            => __( 'Member Description', 'p7-mp' ),
		'wp_user_field'   => 'description',
		'type'            => 'textarea',
		'import'          => true,
		'export'          => true,
	);

	$fields[ 'section_member_type' ] = array(
		'priority'        => 9650,
		'name'            => __( 'Membership', 'p7-mp' ),
		'type'            => 'title',
		'visibility'      => array( 'non-member' ),
		'render_row_cb'   => 'open_fieldset',
	);

	$fields[ 'member_type' ] = array(
		'priority'        => 9700,
		'name'            => __( 'Membership Level', 'p7-mp' ),
		'wp_user_tax'     => 'membership_level',
		'type'            => 'select',
		'import'          => true,
		'export'          => true,
		'admin_column'    => true,
		'admin_filter'    => true,
		'visibility'      => array( 'admin', 'non-member' ),
		'options'         => 'p7mp_get_member_types_select_with_price',
	);

	$fields[ 'section_login_info' ] = array(
		'priority'        => 9750,
		'name'            => array(
			'member'     => __( 'Change Password', 'p7-mp' ),
			'non-member' => __( 'Login Info', 'p7-mp' ),
		),
		'type'            => 'title',
		'visibility'      => array( 'member', 'non-member' ),
		'render_row_cb'   => 'open_fieldset',
	);

	$fields[ 'login' ] = array(
		'priority'        => 9800,
		'name'            => __( 'Username', 'p7-mp' ),
		'wp_user_field'   => 'user_login',
		'type'            => 'text',
		'import'          => true,
		'export'          => true,
		'visibility'      => array( 'non-member' ),
		'validator'       => 'p7mp_validate_username',
		'attributes'      => array(
			'required' => 'required',
		),
	);

	$fields[ 'pass' ] = array(
		'priority'        => 9850,
		'name'            => array(
			'member'     => __( 'New Password', 'p7-mp' ),
			'non-member' => __( 'Password', 'p7-mp' ),
		),
		'wp_user_field'   => 'user_pass',
		'type'            => 'text',
		'import'          => true,
		'visibility'      => array( 'member', 'non-member' ),
		'validator'       => 'p7mp_validate_password',
		'attributes'      => array(
			'type'     => 'password',
			'required' => 'required',
		),
	);

	$fields[ 'pass_confirm' ] = array(
		'priority'        => 9900,
		'name'            => __( 'Confirm Password', 'p7-mp' ),
		'type'            => 'text',
		'visibility'      => array( 'member', 'non-member' ),
		'validator'       => 'match_pass',
		'attributes'      => array(
			'type'     => 'password',
			'required' => 'required',
		),
	);

	$fields[ 'section_end_form' ] = array(
		'priority'        => 9950,
		'name'            => __( 'End Form', 'p7-mp' ),
		'type'            => 'title',
		'render_row_cb'   => 'close_fieldset',
	);

	return $fields;
}

add_filter( 'p7mp_contact_form', 'p7mp_default_contact_form', 5, 1 );
function p7mp_default_contact_form( $fields ) {
	$fields[ 'name' ] = array(
		'priority'        => 10,
		'name'            => __( 'Name', 'p7-mp' ),
		'type'            => 'text',
		'attributes'      => array(
			'required' => 'required',
		),
	);

	$fields[ 'email' ] = array(
		'priority'        => 20,
		'name'            => __( 'Email', 'p7-mp' ),
		'type'            => 'text_email',
		'attributes'      => array(
			'required' => 'required',
		),
	);

	$fields[ 'message' ] = array(
		'priority'        => 30,
		'name'            => __( 'Message', 'p7-mp' ),
		'type'            => 'textarea',
	);

	return $fields;
}

?>
