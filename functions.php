<?php

/**
 * Returns an associative array of US State abbreviations and names.
 *
 * @return array $r
 */
function p7mp_get_us_states_select() {
	return array( '' => 'Choose&hellip;', 'AL' => 'Alabama', 'AK' => 'Alaska', 'AS' => 'American Samoa', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusets', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'PR' => 'Puerto Rico', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Vitginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming' );
}

/**
 * Returns an associative array of month numbers and names.
 *
 * @return array $r
 */
function p7mp_get_month_select() {
	return array( '' => 'Choose&hellip;', '01' => '01 - January', '02' => '02 - February', '03' => '03 - March', '04' => '04 - April', '05' => '05 - May', '06' => '06 - June', '07' => '07 - July', '08' => '08 - August', '09' => '09 - September', '10' => '10 - October', '11' => '11 - November', '12' => '12 - December', );
}

/**
 * Returns an associative array of year numbers.
 *
 * @param int $start_year
 * @param int $end_year optional
 *
 * @return array $r
 */
function p7mp_get_year_select( $start_year, $end_year = null ) {
	$start_year = absint( $start_year );
	$end_year   = isset( $end_year ) ? absint( $end_year ) : date( 'Y' );

	$r = range( $start_year, $end_year );

	return array_combine( $r, $r );
}

/**
 * Returns an associative array of year numbers.
 *
 * @return array $r
 */
function p7mp_get_cc_expiry_year_select() {
	return p7mp_get_year_select( date( 'Y' ),  date( 'Y', strtotime( date( 'Y' ) . ' +9 years' ) ) );
}

/**
 * Returns an associative array of year numbers.
 *
 * @return array $r
 */
function p7mp_get_year_founded_select() {
	return p7mp_get_year_select( date( 'Y', strtotime( date( 'Y' ) . ' -100 years' ) ) );
}

/**
 * Returns an associative array of pages or posts.
 *
 * @param str $post_type
 * @param int $parent Post ID.
 * @param int $indent
 *
 * @return array $r
 */
function p7mp_get_pages_select( $post_type = 'page', $parent = 0, $indent = 0 ) {
	$r = array();
	$pages = get_pages( array(
		'post_type' => $post_type,
		'parent'    => $parent,
	) );
	if ( empty( $pages ) ) return $r;

	foreach ( $pages as $page ) {
		$r[ $page->ID ] = ' ' . str_pad( '', $indent, '-' ) . $page->post_title;

		$s = p7mp_get_pages_select( $post_type, $page->ID, $indent + 1 );
		if ( is_array( $s ) ) {
			foreach ( $s as $k => $v ) {
				$r[ $k ] = $v;
			}
		}
	}

	return $r;
}

/**
 * Returns an associative array of members.
 *
 * @return array $r
 */
function p7mp_get_members_select() {
	$args = array(
		'role' => 'p7mp',
	);
	$users = get_users( $args );

	$r = array( '' => 'Choose&hellip;' );
	foreach ( $users as $user ) {
		$r[ $user->user_login ] = $user->data->display_name;
	}

	return $r;
}

/**
 * Returns an associative array of member types.
 *
 * @return array $r
 */
function p7mp_get_member_types_select( $with_price = false, $for_access_setting = false ) {
	$args = array(
		'taxonomy'   => 'p7mp_membership_level',
		'hide_empty' => false,
		'order'      => 'ASC',
		'orderby'    => 'meta_value_num',
		'meta_key'   => 'priority',
	);

	if ( $for_access_setting ) {
		$args[ 'meta_query' ] = array(
			'relation' => 'OR',
			array(
				'key'     => 'access_setting',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => 'access_setting',
				'value'   => 'yes',
			),
		);
	}

	$terms = get_terms( $args );

	if ( empty( $terms ) || is_wp_error( $terms ) ) return false;

	$r = array();
	foreach ( $terms as $term ) {
		if ( $with_price ) {
			$amount = get_term_meta( $term->term_id, 'dues_amount', true );
			if ( ! empty( $amount ) ) $term->name .= ': $' . $amount;
		}
		$r[ $term->slug ] = $term->name;
	}
	
	return $r;
}

/**
 * Returns an associative array of member types.
 * Alias for get_member_types_select( true ).
 *
 * @return array $r
 */
function p7mp_get_member_types_select_with_price() {
	return p7mp_get_member_types_select( true, false );
}

/**
 * Returns an associative array of lists from Constant Contact.
 *
 * @return array $r
 */
function p7mp_get_constant_contact_lists_select() {
	p7mp_refresh_constant_contact_token();
	
	$opt = get_option( 'p7mp_options' );
	$auth = json_decode( $opt[ 'constant_contact_auth_token' ], true );
	if ( empty( $auth ) ) return;

	$request = wp_remote_get( 'https://api.cc.email/v3/contact_lists?limit=500&include_count=true', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $auth[ 'access_token' ],
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		),
	) );

	$response = json_decode( wp_remote_retrieve_body( $request ) );

	if ( '200' != wp_remote_retrieve_response_code( $request ) || ! is_array( $response->lists ) ) {
		return array( $opt[ 'constant_contact_list_id' ] => $opt[ 'constant_contact_list_id' ] );
	}

	$r = array();
	foreach ( $response->lists as $list ) {
		$r[ $list->list_id ] = $list->name . ' (' . $list->membership_count . ')';
	}

	asort( $r );

	return array( '' => 'Choose&hellip;' ) + $r;
}

/**
 * Refreshes an expired auth token for Constant Contact.
 *
 * @return void
 */
function p7mp_refresh_constant_contact_token() {
	$opt = get_option( 'p7mp_options' );
	$auth = json_decode( $opt[ 'constant_contact_auth_token' ], true );
	//if ( empty( $auth ) || time() < $auth[ 'expires' ] ) return;

	$request = wp_remote_post( 'https://idfed.constantcontact.com/as/token.oauth2?refresh_token=' . $auth[ 'refresh_token' ] . '&grant_type=refresh_token', array(
		'headers' => array(
			'Authorization' => 'Basic MzI5NWQyNTEtODM3MC00NDllLTliYTEtZWY5OTBiOGM5OTViOm5kaGJSaWhEYVQ1d0JQY1Y3d0w2b1E=',
		),
	) );

	$response = json_decode( wp_remote_retrieve_body( $request ), true );

	if ( '200' != wp_remote_retrieve_response_code( $request ) || empty( $response[ 'access_token' ] ) ) return;

	$response[ 'expires' ] = time() + 86000;

	$opt[ 'constant_contact_auth_token' ] = json_encode( $response );
	update_option( 'p7mp_options', $opt );
}

/**
 * Returns true if valid. Returns a message if invalid.
 *
 * @param str $str
 *
 * @return mixed
 */
function p7mp_validate_email( $str ) {
	if ( ! is_email( $str ) ) {
		return __( 'Please enter a valid email address.', 'p7-mp' );
	}

	return true;
}

/**
 * Returns true if valid. Returns a message if invalid.
 *
 * @param str $str
 *
 * @return mixed
 */
function p7mp_validate_phone( $str ) {
	$phone = preg_replace( '/[^0-9]/', '', $str );
	if ( 11 === strlen( $phone ) ) $phone = preg_replace( '/^1/', '', $phone );
	if ( 10 !== strlen( $phone ) && ! empty( $str ) ) {
		return __( 'Please enter a 10-digit phone number.', 'p7-mp' );
	}

	return true;
}

/**
 * Returns true if valid. Returns a message if invalid.
 *
 * @param str $str
 *
 * @return mixed
 */
function p7mp_validate_username( $str ) {
	if ( strlen( $str ) < 4 ) {
		return __( 'Username should be at least 4 characters long.', 'p7-mp' );
	}

	if ( username_exists( $str ) || 'admin' === $str ) {
		return __( 'Username is already taken.', 'p7-mp' );
	}

	if ( ! validate_username( $str ) || strpos( $str, ' ' ) ) {
		return __( 'Please choose a username that contains only alphanumeric characters.', 'p7-mp' );
	}

	return true;
}

/**
 * Returns true if valid. Returns a message if invalid.
 *
 * @param str $str
 *
 * @return mixed
 */
function p7mp_validate_password( $str ) {
	if (
		   strlen( $str ) < 8
		|| ! preg_match( '/[A-Z]/', $str )
		|| ! preg_match( '/[a-z]/', $str )
		|| ! preg_match( '/[0-9]/', $str )
	) {
		$output  = __( 'Password Requirements:', 'p7-mp' );
		$output .= '<ul>';
		$output .= '<li>' . __( 'At least 8 characters',                  'p7-mp' ) . '</li>';
		$output .= '<li>' . __( 'Contain one uppercase letter',           'p7-mp' ) . '</li>';
		$output .= '<li>' . __( 'Contain one lowercase letter',           'p7-mp' ) . '</li>';
		$output .= '<li>' . __( 'Contain one number',                     'p7-mp' ) . '</li>';
		$output .= '<li>' . __( 'Cannot be too similar to your username', 'p7-mp' ) . '</li>';
		$output .= '</ul>';

		return $output;
	}

	return true;
}

/**
 * Renders custom CMB2 field type for connecting Constant Contact API v3 via Oauth2
 *
 * @param obj $field
 * @param str $escaped_value //Oauth2 token
 * @param str $object_id
 * @param str $object_type
 * @param obj $field_type_object
 *
 * @return void
 */
add_action( 'cmb2_render_p7mp_constant_contact_auth', 'p7mp_render_constant_contact_auth', 10, 5 );
function p7mp_render_constant_contact_auth( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
	$url = add_query_arg( array(
		'response_type' => 'code',
		'client_id'     => '3295d251-8370-449e-9ba1-ef990b8c995b',
		'scope'         => 'contact_data',
		'redirect_uri'  => urlencode( trailingslashit( plugin_dir_url( __FILE__ ) ) . 'includes/oauth/constant-contact.php' ),
	), 'https://api.cc.email/v3/idfed' );

	if ( ! empty( get_option( 'p7mp_constant_contact_sync_success_time' ) ) ) {
		$offset = get_option( 'gmt_offset' ) * 3600;
		$sync_time = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), get_option( 'p7mp_constant_contact_sync_success_time' ) + $offset );
	} else {
		$sync_time = __( 'Never.', 'p7mp' ) . ' ' . __( 'Synchronizations are performed automatically once per day.', 'p7mp' );
	}

	?>
		<div class="p7mp-const">
			<div class="connect">
				<a href="<?php echo $url; ?>" class="button-primary"><?php _e( 'Connect', 'p7mp' ); ?></a>
			</div>
			<div class="disconnect">
				<strong><?php _e( 'Status: ', 'p7mp' ); ?></strong>
				<a href="https://campaign-ui.constantcontact.com/campaign/dashboard" target="_blank"><?php _e( 'Connected', 'p7mp' ); ?></a>
				<strong><?php _e( 'Last Successful Synchronization: ', 'p7mp' ); ?></strong>
				<span><?php echo $sync_time; ?></span>
				<b class="button-secondary"><?php _e( 'Disconnect', 'p7mp' ); ?></b>
			</div>
			<?php echo $field_type_object->input( array( 'type' => 'hidden' ) ); ?>
		</div>
		<script>
			(function($){
				'use strict';
				$(document).ready(function(){
					if($('.p7mp-const > input[type="hidden"]').val()){
						$('.p7mp-const > .connect').hide();
					}else{
						$('.p7mp-const > .disconnect').hide();
						$('.p7mp-const').closest('div.cmb-row').next('div.cmb-row').hide();
					}
				});

				$('body').on('click', '.p7mp-const > .disconnect > b', function(){
					$('.p7mp-const > input[type="hidden"]').val('');
					$('.p7mp-const').closest('form.cmb-form').find('input[name="submit-cmb"]').click();
				});
			})(jQuery);
		</script>
		<style>
			.p7mp-const > .disconnect > strong {
				line-height: 26px;
			}

			.p7mp-const > .disconnect > a,
			.p7mp-const > .disconnect > span {
				line-height: 26px;
				color: green;
				margin-right: 2em;
			}
		</style>
	<?php
}

?>
