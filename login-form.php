<?php

if ( ! defined( 'WPINC' ) ) die();

add_filter( 'p7mp_login_form', 'p7mp_default_login_form', 5, 1 );
function p7mp_default_login_form( $fields ) {
	$fields[ 'login' ] = array(
		'priority'        => 10,
		'name'            => __( 'Username / Email', 'p7-mp' ),
		'wp_user_field'   => array( 'user_login' ),
		'type'            => 'text',
		'attributes'      => array(
			'required' => 'required',
		),
	);

	$fields[ 'pass' ] = array(
		'priority'        => 20,
		'name'            => __( 'Password', 'p7-mp' ),
		'wp_user_field'   => array( 'user_password' ),
		'type'            => 'text',
		'attributes'      => array(
			'type'     => 'password',
			'required' => 'required',
		),
	);

	return $fields;
}

?>
