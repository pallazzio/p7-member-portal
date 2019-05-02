<?php

require_once '../../../../../wp-load.php';

/**********************    CREATE ONE CUSTOM FIELD    *****************************/

/*$request = wp_remote_post( 'https://api.cc.email/v3/contact_custom_fields', array(
	'headers' => array(
		'Authorization' => 'Bearer TNMMqd7nZ22ivxg8IBqw0fI4Ix22',
		'Content-Type'  => 'application/json',
		'Accept'        => 'application/json',
	),
	'body' => json_encode( array(
		'type'  => 'string',
		'label' => 'Vehicle Make and Model',
	) ),
) );

$response = json_decode( wp_remote_retrieve_body( $request ), true );

echo( '<pre>' ); print_r( $response ); echo( '</pre>' );*/

/***********************   DELETE ONE CUSTOM FIELD   ****************************/

/*$field_id = '815fda20-1b5a-11e9-990a-d4ae52a4597c';

$headers = array(
	'Content-Type: application/json',
	'Authorization: Bearer TNMMqd7nZ22ivxg8IBqw0fI4Ix22',
	'Accept: application/json',
);

$ch = curl_init( 'https://api.cc.email/v3/contact_custom_fields/' . $field_id );

curl_setopt( $ch, CURLOPT_HTTPHEADER,     $headers );
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true     );
curl_setopt( $ch, CURLOPT_TIMEOUT,        10       );
curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,  'DELETE' );
curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false    );

$result    = curl_exec( $ch );
$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
curl_close( $ch );*/

/***********************     GET LIST OF CUSTOM FIELDS     ****************************/

/*$request = wp_remote_get( 'https://api.cc.email/v3/contact_custom_fields', array(
	'headers' => array(
		'Authorization' => 'Bearer 3LgxOHs7q5ACONkALgFMzr3A8pgn',
		'Content-Type'  => 'application/json',
		'Accept'        => 'application/json',
	),
) );

$response = json_decode( wp_remote_retrieve_body( $request ), true );

echo( '<pre>' ); print_r( $response ); echo( '</pre>' );*/

/***********************     GET STATUS OF ONE BULK ACTIVITY     ****************************/

/*$request = wp_remote_get( 'https://api.cc.email/v3/activities/6eab2b60-1f5a-11e9-8790-d4ae52844372', array(
	'headers' => array(
		'Authorization' => 'Bearer TNMMqd7nZ22ivxg8IBqw0fI4Ix22',
		'Content-Type'  => 'application/json',
		'Accept'        => 'application/json',
		'Cache-Control' => 'no-cache',
	),
) );

$response = json_decode( wp_remote_retrieve_body( $request ), true );

echo( '<pre>' ); print_r( $response ); echo( '</pre>' );*/

/***************************************************/

if ( ! empty( $_GET[ 'code' ] ) ) {

	$url = add_query_arg( array(
		'code'         => $_GET[ 'code' ],
		'grant_type'   => 'authorization_code',
		'redirect_uri' => 'https://www.fweda.com/wp-content/plugins/p7-member-portal/includes/oauth/constant-contact.php',
	), 'https://idfed.constantcontact.com/as/token.oauth2' );

	$request = wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Basic MzI5NWQyNTEtODM3MC00NDllLTliYTEtZWY5OTBiOGM5OTViOm5kaGJSaWhEYVQ1d0JQY1Y3d0w2b1E=',
		),
	) );

	$response = json_decode( wp_remote_retrieve_body( $request ), true );
	$response[ 'expires' ] = time() + 86000;

	$opt = get_option( 'p7mp_options' );
	$opt[ 'constant_contact_auth_token' ] = json_encode( $response );
	update_option( 'p7mp_options', $opt );

	header( 'Location: https://www.fweda.com/wp-admin/options-general.php?page=p7mp_options' );
}

exit;

?>