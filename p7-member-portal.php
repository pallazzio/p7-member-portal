<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Member Portal
 * Plugin URI:        https://github.com/pallazzio/p7-member-portal
 * Description:       Front-end registration and login forms, additional user info fields for members, and member directory.
 * Version:           1.1.8
 * Author:            Jeremy Kozan
 * Author URI:        https://www.pallazzio.net/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       p7-mp
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) die();
use Omnipay\Omnipay;
use League\Csv\Reader;
use League\Csv\Writer;

$p7mp = new P7_Member_Portal();
class P7_Member_Portal {
	private $version             = '1.1.8';      // str             Current version.
	private $p                   = 'p7mp';       // str             Prefix for variables.
	private $pu                  = 'p7mp_u_';    // str             Prefix for user meta fields.
	private $pp                  = 'p7mp_p_';    // str             Prefix for payment form fields.
	private $pl                  = 'p7mp_l_';    // str             Prefix for login form fields.
	private $pc                  = 'p7mp_c_';    // str             Prefix for contact form fields.
	private $view                = 'non-member'; // str             Current page view type.
	private $plugin_file_path    = '';           // str             Absolute path to this file.      (with trailing slash)
	private $plugin_dir_path     = '';           // str             Absolute path to this directory. (with trailing slash)
	private $plugin_dir_url      = '';           // str             URL of this directory.           (with trailing slash)
	private $plugin_data         = array();      // array
	private $options             = array();      // array           CMB2 options for this plugin.
	private $options_tabs        = array();      // array           Holder for the options tabs input.
	private $options_boxes       = array();      // array           Holder for the options boxes input.
	public  $user                = null;         // WP_User  object Current logged in user.
	private $user_meta           = null;         // stdClass object Current logged in user's user_meta data.
	public  $user_meta_fields    = array();      // array           Holder for the user meta fields input.
	private $login_form_fields   = array();      // array           Holder for the login form fields input.
	private $contact_form_fields = array();      // array           Holder for the contact form fields input.
	private $payment_form_fields = array();      // array           Holder for the payment form fields input.
	private $post_types_fields   = array();      // array           Holder for the custom post types meta fields input.
	public  $user_taxonomies     = array();      // array           Holder for the custom user taxonomies.
	private $is_member           = null;         // bool            true if $this->user is a member.
	private $fieldset_open       = false;        // bool            true if fieldset is already open.
	private $notices             = null;         // object          Used to store notices.

	/**
	 * Constructs object.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->plugin_file_path = __FILE__;
		$this->plugin_dir_path  = plugin_dir_path( $this->plugin_file_path );
		$this->plugin_dir_url   = plugin_dir_url(  $this->plugin_file_path );
		$this->view             = is_admin() ? 'admin' : $this->view;

		require_once $this->plugin_dir_path . 'functions.php';
		require_once $this->plugin_dir_path . 'options.php';
		require_once $this->plugin_dir_path . 'user-meta.php';
		require_once $this->plugin_dir_path . 'login-form.php';
		require_once $this->plugin_dir_path . 'user-taxonomies.php';
		require_once $this->plugin_dir_path . 'dues-payment.php';
		require_once $this->plugin_dir_path . 'notices.php';
		require_once $this->plugin_dir_path . 'includes/vendor/autoload.php';
		require_once $this->plugin_dir_path . 'includes/vendor/webdevstudios/cmb2/init.php';
		require_once $this->plugin_dir_path . 'includes/vendor/rogerlos/cmb2-metatabs-options/cmb2_metatabs_options.php';

		require_once $this->plugin_dir_path . 'includes/pallazzio-wpghu/pallazzio-wpghu.php';
		new Pallazzio_WPGHU( $this->plugin_dir_path . wp_basename( $this->plugin_file_path ), 'pallazzio' );
		
		$this->options = get_option( 'p7mp_options' );
		$this->notices = new P7_Notices( 'p7mp' );

		register_activation_hook(   $this->plugin_file_path, array( $this, 'activate'   ) );
		register_deactivation_hook( $this->plugin_file_path, array( $this, 'deactivate' ) );

		add_action( 'wp_enqueue_scripts',    array( $this, 'enqueue' ),       10    );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ), 10, 1 );

		add_action( 'update_option_p7mp_options', array( $this, 'update_plugin_options' ) );

		add_action( 'save_post',           array( $this, 'save_post'           ), 10, 1 );
		add_action( 'create_term',         array( $this, 'create_term'         ), 10, 3 );
		add_action( 'wp_update_term_data', array( $this, 'update_term_data'    ), 10, 3 );

		add_action( 'profile_update', array( $this, 'profile_update' ), 10, 1 );

		add_action( 'admin_footer',  array( $this, 'add_export_members_link' ) );

		add_action( 'init',       array( $this, 'init'       ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'cmb2_init',       array( $this, 'cmb2_init'       ) );
		add_action( 'cmb2_admin_init', array( $this, 'cmb2_admin_init' ) );
		add_action( 'cmb2_after_init', array( $this, 'cmb2_after_init' ) );

		add_filter( 'display_post_states', array( $this, 'custom_page_states' ), 10, 1 );

		add_filter( 'the_content', array( $this, 'hide_content' ), 99, 1 );
		add_filter( 'the_content', array( $this, 'the_content' ),  20, 1 );

		add_filter( 'user_search_columns',           array( $this, 'user_search_columns'         ), 10, 3 );
		add_filter( 'manage_users_columns',          array( $this, 'user_table'                  )        );
		add_filter( 'manage_users_custom_column',    array( $this, 'user_table_row'              ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'user_table_columns_sortable' )        );
		add_action( 'pre_get_users',                 array( $this, 'user_table_columns_orderby'  )        );
		add_action( 'restrict_manage_users',         array( $this, 'add_user_filters'            ), 10, 1 );
		add_filter( 'pre_get_users',                 array( $this, 'filter_users'                )        );

		add_filter( 'manage_posts_columns',       array( $this, 'posts_table'       )        );
		add_filter( 'manage_posts_custom_column', array( $this, 'posts_table_row'   ), 10, 2 );
		add_filter( 'parse_query',                array( $this, 'filter_posts'      )        );

		add_action( 'disable_expired_accounts', array( $this, 'disable_expired_accounts' ) );
		add_action( 'sync_constant_contact',    array( $this, 'sync_constant_contact'    ) );

		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file_path ), array( $this, 'plugin_action_links' ), 10, 1 );
	}

	/**
	 * Syncs members to Constant Contact
	 *
	 * @return void
	 */
	public function sync_constant_contact() {
		p7mp_refresh_constant_contact_token();
		$opt = get_option( 'p7mp_options' );
		$auth = json_decode( $opt[ 'constant_contact_auth_token' ], true );
		if ( empty( $auth ) ) return; //bail if no oauth token

		$my_custom_fields = array();
		foreach ( $this->user_meta_fields as $k => $v ) {
			if ( empty( $v[ 'cc_sub_field_custom' ] ) ) continue;
			if ( false !== strpos( $v[ 'type' ], 'multi' ) ) {//TODO: Hanlde Multi
				/*$multi = array();
				if ( is_array( $v[ 'options' ] ) && ! empty( $v[ 'options' ] ) ) {
					$multi = $v[ 'options' ];
				} else if ( is_string( $v[ 'options' ] ) && ! empty( $v[ 'options' ] ) ) {
					$multi = $this->call_user_func( $v[ 'options' ] );
				}
				$this->write_log( $multi, 'multi' );

				if ( is_array( $multi ) && ! empty( $multi ) ) {
					foreach ( $multi as $key => $val ) {
						$my_custom_fields[ $k . '_' . $key ] = 'P7MP ' . $v[ 'name' ] . ' ' . $val;
					}
				}*/  //////////////////////   CAN EASILY CAUSE THE NUMBER OF CUSTOM FIELDS TO EXCEED THE LIMIT OF 100
				continue;
			}
			if ( ! is_string( $v[ 'name' ] ) ) $v[ 'name' ] = $k;
			$my_custom_fields[ $k ] = 'P7MP ' . $v[ 'name' ];
		}

		if ( ! empty( $my_custom_fields ) ) {

			//get list of custom fields from CTCT
			$request = wp_remote_get( 'https://api.cc.email/v3/contact_custom_fields', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $auth[ 'access_token' ],
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
			) );
			if ( '200' != wp_remote_retrieve_response_code( $request ) ) return;
			$response = json_decode( wp_remote_retrieve_body( $request ), true );
			$ctct_custom_fields = $response[ 'custom_fields' ];

			//delete custom fields from CTCT
			foreach ( $ctct_custom_fields as $k => $v ) {
				if ( 0 === strpos( $v[ 'label' ], 'P7MP ' ) && ! in_array( $v[ 'label' ], $my_custom_fields ) ) {//if CTCT has a custom field that starts with our designation but isn't in our data
					$ch = curl_init( 'https://api.cc.email/v3/contact_custom_fields/' . $v[ 'custom_field_id' ] );
					curl_setopt( $ch, CURLOPT_HTTPHEADER,     'Authorization: Bearer ' . $auth[ 'access_token' ], 'Content-Type: application/json', 'Accept: application/json' );
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true     );
					curl_setopt( $ch, CURLOPT_TIMEOUT,        10       );
					curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,  'DELETE' );
					curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false    );
					$result    = curl_exec( $ch );
					curl_close( $ch );

					unset( $ctct_custom_fields[ $k ] );
				}
			}

			//add custom fields to CTCT
			$labels = array_column( $ctct_custom_fields, 'label' );
			foreach ( $my_custom_fields as $k => $v ) {
				if ( ! in_array( $v, $labels ) ) {//if we have a custom field that doesn't exist on CTCT
					$request = wp_remote_post( 'https://api.cc.email/v3/contact_custom_fields', array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $auth[ 'access_token' ],
							'Content-Type'  => 'application/json',
							'Accept'        => 'application/json',
						),
						'body' => json_encode( array(
							'type'  => 'string',
							'label' => $v,
						) ),
					) );
					if ( '200' != wp_remote_retrieve_response_code( $request ) ) return;
				}
			}

		}

		$members = array();
		$users = get_users( array( 'role' => 'p7mp' ) );
		foreach ( $users as $user ) {
			$meta = $this->flatten_array( get_user_meta( $user->ID ) );

			$r = array();
			$r = $this->get_matching_fields( 'cc_sub_field', $this->user_meta_fields, $meta, $this->pu );
			$r = array_filter( $r, function( $value ) { return $value !== ''; } );
			if ( empty( $r ) ) continue;
			if ( empty( $r[ 'email' ] ) ) $r[ 'email' ] = $user->data->user_email;
			
			$custom = $this->get_matching_fields( 'cc_sub_field_custom', $this->user_meta_fields, $meta, $this->pu, true );
			$custom = array_filter( $custom, function( $value ) { return $value !== ''; } );
			if ( is_array( $custom ) && ! empty( $custom ) && is_array( $labels ) && ! empty( $labels )  ) {
				foreach ( $custom as $k => $v ) {
					//TODO: Hanlde Multi
					if ( false !== strpos( $this->user_meta_fields[ $k ][ 'type' ], 'multi' ) ) {
						unset( $custom[ $k ] );
						continue;
					}
					//END TODO: Hanlde Multi
					$custom[ $ctct_custom_fields[ array_search( $my_custom_fields[ $k ], $labels ) ][ 'name' ] ] = $v;
					unset( $custom[ $k ] );
				}
			}

			if ( ! empty( $custom ) ) $r = $r + $custom;
			if ( ! empty( $r ) ) $members[] = $r;
		}
		
		if ( empty( $members ) ) return;

		$request = wp_remote_post( 'https://api.cc.email/v3/activities/contacts_json_import/', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $auth[ 'access_token' ],
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'body' => json_encode( array(
				'import_data' => $members,
				'list_ids'    => array( $this->get_option( 'constant_contact_list_id' ) ),
			) ),
		) );

		$response = json_decode( wp_remote_retrieve_body( $request ), true );
		
		if ( 'initialized' === $response[ 'state' ] ) {
			update_option( 'p7mp_constant_contact_sync_success_time', time() );
		} else {
			$this->write_log( $response, 'faliure response' );
		}
	}

	/**
	 * Sets up initial plugin settings, data, etc.
	 *
	 * @return void
	 */
	public function activate() {
		$role_name = __( 'Member', 'p7-mp' );
		add_role( 'p7mp', $role_name, array( 'read' ) );

		if ( ! wp_next_scheduled( 'disable_expired_accounts' ) ) {
			wp_schedule_event( time(), 'daily', 'disable_expired_accounts' );
		}

		if ( ! wp_next_scheduled( 'sync_constant_contact' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'sync_constant_contact' );
		}

		$this->user_taxonomies( true );
		flush_rewrite_rules( false );
	}

	/**
	 * Removes role created by this plugin.
	 *
	 * @return void
	 */
	public function deactivate() {
		remove_role( 'p7mp' );

		if ( wp_next_scheduled( 'disable_expired_accounts' ) ) {
			wp_clear_scheduled_hook( 'disable_expired_accounts' );
		}

		if ( wp_next_scheduled( 'sync_constant_contact' ) ) {
			wp_clear_scheduled_hook( 'sync_constant_contact' );
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Runs routines when plugin options page is saved.
	 *
	 * @param string $option_name
	 * @param mixed  $option_value
	 *
	 * @return void
	 */
	public function update_plugin_options() {
		$this->options = get_option( 'p7mp_options' );
		$this->post_types();
		flush_rewrite_rules( false );
	}

	/**
	 * Updates permalinks if the members page URI changes.
	 *
	 * @param int $post_id
	 *
	 * @return void
	 */
	public function save_post( $post_id ) {
		if ( $post_id == $this->get_option( 'page_member_directory' ) ) $this->update_plugin_options();
	}

	/**
	 * Updates certain term meta when a term is created.
	 *
	 * @param int $term_id
	 * @param int $tt_id
	 * @param str $taxonomy
	 *
	 * @return void
	 */
	public function create_term( $term_id, $tt_id, $taxonomy ) {
		if ( 'p7mp_membership_level' === $taxonomy ) {
			if ( empty( get_term_meta( $term_id, 'priority', true ) ) ) update_term_meta( $term_id, 'priority', 0 );
		}
	}

	/**
	 * Updates certain term meta when a term is updated.
	 *
	 * @param array $update_data
	 * @param int   $term_id
	 * @param str   $taxonomy
	 *
	 * @return array $update_data
	 */
	public function update_term_data( $update_data, $term_id, $taxonomy ) {
		if ( ! in_array( str_replace( 'p7mp_', '', $taxonomy ), array_keys( $this->user_taxonomies ) ) ) return $update_data;
		
		$term = get_term( $term_id, $taxonomy );

		//$this->write_log( $term );

		/*$args = array(
			'meta_query' => array(
				array(
					'key'     => $this->pu . 'member_status',
					'value'   => $term->slug,
					'compare' => 'LIKE',
				),
			),
		);
		$users = get_users( $args );*/

		return $update_data;
	}

	/**
	 * Adds a plugin action links to the plugins list page.
	 *
	 * @param array $links
	 *
	 * @return array $links
	 */
	public function plugin_action_links( $links ) {
		$import_link = '<a href="users.php?page=p7mp_import_members">' . __( 'Import Members', 'p7-mp' ) . '</a>';
		array_unshift( $links, $import_link );
		$settings_link = '<a href="options-general.php?page=p7mp_options&tab=opt-tab-general">' . __( 'Settings', 'p7-mp' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Adds a link to the users table footer.
	 *
	 * @return void
	 */
	public function add_export_members_link() {
		$screen = get_current_screen();
		if ( 'users' !== $screen->id || ! current_user_can( 'manage_options' ) ) return;
		?>
			<script>
				(function($){
					'use strict';
					$(document).ready(function(){
						$('.tablenav.bottom .clear').before('<form method="post" style="float: right; margin-right: 1em;"><input type="hidden" id="p7mp_export_members" name="p7mp_export_members" value="1" /><input class="button button-primary p7-mp-export-members-button" style="margin-top:3px;" type="submit" value="<?php _e( 'Export Members', 'p7-mp' ); ?>" /></form>');
					});
				})(jQuery);
			</script>
		<?php
	}

	/**
	 * Loads frontend scripts and stylesheets.
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(  'p7-mp-style',  $this->plugin_dir_url . 'css/style.css', array(          ), $this->version, 'screen' );
		wp_enqueue_script( 'p7-mp-script', $this->plugin_dir_url .  'js/script.js', array( 'jquery' ), $this->version, true     );
	}

	/**
	 * Loads admin scripts and stylesheets.
	 *
	 * @param string $hook
	 *
	 * @return void
	 */
	public function admin_enqueue( $hook ) {
		$hooks = array( 'settings_page_p7mp_options', 'users_page_p7mp_import_members', 'user-new.php', 'user-edit.php', 'profile.php' );
		if ( in_array( $hook, $hooks ) ) {
			wp_enqueue_style(  'p7-mp-admin-style',  $this->plugin_dir_url . 'css/admin-style.css', array(          ), $this->version, 'screen' );
			wp_enqueue_script( 'p7-mp-admin-script', $this->plugin_dir_url .  'js/admin-script.js', array( 'jquery' ), $this->version, true     );
		}
	}

	/**
	* Adds custom states to post/page list
	 *
	 * @param array $states
	 *
	 * @return array $states
	*/
	public function custom_page_states( $states ) {
		global $post;

		// get saved project page ID
		$page_id = $this->get_option( 'page_member_directory' );

		// add our custom state after the post title only
		if ( 'page' === get_post_type( $post->ID ) && $post->ID == $page_id && $page_id != '0' ) {
			$states[] = __( 'Member Directory Page', 'p7-mp' );
		}

		return $states;
	}

	/**
	 * Generates an <svg> tag.
	 *
	 * @param str $str
	 *
	 * @return str $str
	 */
	public function svg( $str ) {
		return '<svg><use xlink:href="' . $this->plugin_dir_url . 'images/icons.svg#' . $str . '"></use></svg>';
	}

	/**
	 * Adds plugin options.
	 *
	 * @return void
	 */
	private function options_init() {
		$options_pages = array();

		$options_pages[ 'p7mp_options' ] = array(
			'title'    => __( 'Member Portal Settings', 'p7-mp' ),
			'topmenu'  => 'options-general.php',
			'boxes'    => $this->options_boxes( 'p7mp_options' ),
			'tabs'     => array_values( $this->options_tabs ),
			'menuargs' => array(
				'menu_title' => __( 'Membership', 'p7-mp' ),
			),
		);

		$options_pages[ 'p7mp_import_members' ] = array(
			'title'    => __( 'Import Members', 'p7-mp' ),
			'topmenu'  => 'users.php',
			'boxes'    => $this->import_members_boxes( 'p7mp_import_members' ),
			'menuargs' => array(
				'menu_title' => __( 'Import Members', 'p7-mp' ),
			),
		);

		$options_pages = apply_filters( 'p7mp_options_pages', $options_pages );

		foreach ( $options_pages as $key => $args ) {
			$args[ 'key' ]                           = $key;
			$args[ 'resettxt' ]                      = '';
			$args[ 'menuargs' ][ 'capability' ]      = 'manage_options';
			$args[ 'menuargs' ][ 'view_capability' ] = 'manage_options';

			new Cmb2_Metatabs_Options( $args );
		}
	}

	/**
	 * Adds tabs to the plugin options page.
	 *
	 * @return array $tabs
	 */
	private function options_tabs() {
		$this->options_tabs = array();

		$this->options_tabs = apply_filters( 'p7mp_options_tabs', $this->options_tabs );

		$this->prioritize( $this->options_tabs );

		foreach ( $this->options_tabs as $id => $tab ) {
			$this->options_tabs[ $id ][ 'id' ] = $id;
		}
	}

	/**
	 * Adds boxes to plugin options.
	 *
	 * This is typical CMB2, but note two crucial extra items:
	 * - the ['show_on'] property is configured
	 * - a call to object_type method
	 *
	 * @return array $boxes
	 */
	private function options_boxes() {
		$boxes = array();

		$boxes = apply_filters( 'p7mp_options_boxes', $boxes );

		$this->prioritize( $boxes );

		$this->options_boxes = array_merge( ...array_column( $boxes, 'fields' ) );

		foreach ( $boxes as $id => $box ) {
			$box[ 'id' ]              = $id;
			$box[ 'display_cb' ]      = false;
			$box[ 'admin_menu_hook' ] = false;
			$box[ 'show_on' ]         = array(
				'key'   => 'options-page',
				'value' => array( 'p7mp_options' ),
			);

			unset( $box[ 'priority' ] );

			$boxes[ $id ] = new_cmb2_box( $box );
			$boxes[ $id ] = $this->add_fields( $boxes[ $id ], $box[ 'fields' ] );
			$boxes[ $id ]->object_type( 'options-page' );

			$this->options_tabs[ $box[ 'tab' ] ][ 'boxes' ][] = $id;
		}
		
		return $boxes;
	}

	/**
	 * Adds boxes to import members.
	 *
	 * @return array $boxes
	 */
	private function import_members_boxes() {
		$boxes = array();
		
		$cmb = new_cmb2_box( array(
			'id'              => 'p7mp_import_members',
			'title'           => __( 'Import Members', 'p7-mp' ),
			'show_on'         => array(
				'key'   => 'options-page',
				'value' => array( 'p7mp_import_members' ),
			),
			'display_cb'      => false,
			'admin_menu_hook' => false,
			'hookup'          => false,
			'save_fields'     => false,
		) );

		$cmb->add_field( array(
			'name'            => __( 'Upload a .CSV File', 'p7-mp' ),
			'desc'            => __( 'File must be formatted correctly or errors may occur.', 'p7-mp' ),
			'id'              => 'upload_file',
			'type'            => 'file',
			'options' => array(
				//'url' => false,
			),
			'text' => array(
				'add_upload_file_text' => 'Choose File',
			),
			'query_args' => array(
				'type' => 'text/csv',
			),
		) );
		$cmb->object_type( 'options-page' );
		$boxes[] = $cmb;
		
		return $boxes;
	}

	/**
	 * Adds member profile fields to user meta.
	 *
	 * @return void
	 */
	private function user_meta_init() {
		$fields = array();

		$fields = apply_filters( 'p7mp_user_meta', $fields );

		$this->prioritize( $fields );

		$this->user_meta_fields = $fields;

		$box = new_cmb2_box( array(
			'id'               => $this->pu,
			'object_types'     => array( 'user' ),
			'show_names'       => true,
			'new_user_section' => 'add-new-user',
		) );

		$this->add_fields( $box, $fields, $this->pu );
	}

	/**
	 * Registers a frontend login form.
	 *
	 * @return void
	 */
	private function login_form_init() {
		$fields = array();

		$fields = apply_filters( 'p7mp_login_form', $fields );

		$this->prioritize( $fields );

		$this->login_form_fields = $fields;

		$box = new_cmb2_box( array(
			'id'           => $this->pl,
			'object_types' => array( 'post' ),
			'hookup'       => false,
			'save_fields'  => false,
		) );

		$this->add_fields( $box, $fields, $this->pl );
	}

	/**
	 * Registers a frontend contact form.
	 *
	 * @return void
	 */
	private function contact_form_init() {
		$fields = array();

		$fields = apply_filters( 'p7mp_contact_form', $fields );

		$this->prioritize( $fields );

		$this->contact_form_fields = $fields;

		$box = new_cmb2_box( array(
			'id'           => $this->pc,
			'object_types' => array( 'post' ),
			'hookup'       => false,
			'save_fields'  => false,
		) );

		$this->add_fields( $box, $fields, $this->pc );
	}

	/**
	 * Registers a frontend payment form.
	 *
	 * @return void
	 */
	private function payment_form_init() {
		$fields = array();

		$fields = apply_filters( 'p7mp_payment_form', $fields );

		$this->prioritize( $fields );

		$this->payment_form_fields = $fields;

		$box = new_cmb2_box( array(
			'id'           => $this->pp,
			'object_types' => array( 'post' ),
			'hookup'       => false,
			'save_fields'  => false,
		) );

		$this->add_fields( $box, $fields, $this->pp );
	}

	/**
	 * Registers a "Members Only" metabox on posts and pages.
	 *
	 * @return void
	 */
	private function members_only_init() {
		$prefix = '_p7mp_members_only_';
		$member_types = p7mp_get_member_types_select( false, true );

		$options = array(
			'p7mp_everyone'    => __( 'Everyone', 'p7-mp' ),
			'p7mp_all_members' => __( 'All Membership Levels', 'p7-mp' ),
		);
		if ( ! empty( $member_types ) ) $options += $member_types;

		$cmb = new_cmb2_box( array(
			'id'           => $prefix . 'form',
			'title'        => __( 'Content Access Settings', 'p7-mp' ),
			'object_types' => array( 'post', 'page', 'attachment' ),
			'context'      => 'side',
			'priority'     => 'high',
		) );

		$members_only_selectors = apply_filters( 'p7mp_members_only_selectors', array(
			array(
				'name'    => __( 'Membership Levels:', 'p7-mp' ),
				'id'      => $prefix . 'level',
				'type'    => 'multicheck',
				'default' => 'p7mp_everyone',
				'options' => $options,
			),
		) );

		foreach ( $members_only_selectors as $selector ) $cmb->add_field( $selector );
	}

	/**
	 * Adds CMB2 boxes to thier appropriate locations based on config passed in.
	 *
	 * @param obj   $box    CMB2 box object
	 * @param array $fields
	 * @param str   $key
	 *
	 * @return obj $box CMB2 box object
	 */
	private function add_fields( $box, $fields, $key = '' ) {
		if ( empty( $fields ) || ! is_array( $fields ) ) return $box;

		if ( ! empty( $key ) ) {
			$box->add_hidden_field( array(
				'field_args'  => array(
					'id'      => $key . 'nonce',
					'type'    => 'hidden',
					'default' => wp_create_nonce( $key . 'nonce' ),
				),
			) );
		}

		$group_ids = array();
		foreach ( $fields as $id => $field ) {
			if ( isset( $field[ 'visibility' ] ) ) {
				foreach ( $field[ 'visibility' ] as $k => $v ) {
					if ( isset( $v[ 'option' ] ) && $v[ 'value' ] === $this->get_option( $v[ 'option' ] ) ) {
						if ( $v[ 'negate' ] ) continue 2;
						unset( $field[ 'visibility' ][ $k ] );
					}
				}
				if ( ! empty( $field[ 'visibility' ] ) && ! in_array( $this->view(), $field[ 'visibility' ] ) ) continue;
			}

			if ( 'file' === $field[ 'type' ] && 'admin' !== $this->view() ) {
				$field[ 'type' ] = 'text';
				$field[ 'attributes' ][ 'type' ] = 'file';
			}

			if ( is_array( $field[ 'name' ] ) ) $field[ 'name' ] = $field[ 'name' ][ $this->view() ];

			if ( is_string( $field[ 'options' ] ) ) $field[ 'options' ] = $this->call_user_func( $field[ 'options' ] );

			foreach ( $field as $k => $v ) {
				if (                    false !== strpos( $k, '_cb' )  ) $field[ $k ] = array( $this, $v );
				if ( is_string( $v ) && false !== strpos( $v, 'opt_' ) ) $field[ $k ] = $this->get_option( str_replace( 'opt_', '', $v ) );
				if ( is_string( $v ) && false !== strpos( $v, 'usr_' ) ) $field[ $k ] = $this->user_meta(  str_replace( 'usr_', '', $v ) );
			}

			if ( 'member' === $this->view() && 'password' === $field[ 'attributes' ][ 'type' ] ) unset( $field[ 'attributes' ][ 'required' ] );

			if ( isset( $field[ 'parent' ] ) && isset( $group_ids[ $field[ 'parent' ] ] ) ) {
				$field[ 'id' ] = $id;
				$box->add_group_field( $group_ids[ $field[ 'parent' ] ], $field );
			} else {
				$field[ 'id' ] = $key . $id;
				$field_id = $box->add_field( $field );
				if ( 'group' === $field[ 'type' ] ) $group_ids[ $id ] = $field_id;
			}
		}

		return $box;
	}

	/**
	 * Sorts an array by key 'priority'.
	 *
	 * @param array $r
	 *
	 * @return array $r
	 */
	private function prioritize( &$r ) {
		uasort( $r, function( $a, $b ){
			return floatval( $a[ 'priority' ] ) - floatval( $b[ 'priority' ] );
		} );

		foreach ( $r as $k => $v ) {
			if ( isset( $r[ $k ][ 'fields' ] ) ) {
				$this->prioritize( $r[ $k ][ 'fields' ] );
			}
		}
	}

	/**
	 * Registers custom taxonomies for users.
	 *
	 * @return void
	 */
	public function user_taxonomies( $populate = false ) {
		$taxonomies = array();

		$taxonomies = apply_filters( 'p7mp_user_taxonomies', $taxonomies );

		$this->user_taxonomies = $taxonomies;

		foreach ( $taxonomies as $slug => $args ) {
			$slug = 'p7mp_' . $slug;
			register_taxonomy( $slug, 'user', $args );

			if ( isset( $args[ 'fields' ] ) && function_exists( 'new_cmb2_box' ) ) {
				$box = new_cmb2_box( array(
					'id'               => $slug,
					'title'            => 'Term Box Title Placeholder',
					'object_types'     => array( 'term' ),
					'taxonomies'       => array( $slug ),
					'new_term_section' => true,
				) );

				$this->add_fields( $box, $args[ 'fields' ] );
			}

			if ( $populate ) {
				$terms = get_terms( array(
					'taxonomy'   => $slug,
					'number'     => 1,
					'hide_empty' => false,
				) );

				if ( empty( $terms ) ) {
					foreach ( $args[ 'default' ] as $term ) {
						$name = is_array( $term ) ? $term[ 'name' ] : $term;

						$term_id = wp_insert_term( $name, $slug );

						if ( is_array( $term ) ) {
							foreach ( $term as $k => $v ) {
								if ( 'name' !== $k ) {
									update_term_meta( $term_id[ 'term_id' ], $k, $v );
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Registers custom post types.
	 *
	 * @return void
	 */
	private function post_types() {
		$post_types = array();

		$post_types = apply_filters( 'p7mp_post_types', $post_types );

		$members_page = ! empty( $this->get_option( 'page_member_directory' ) ) ? get_page_uri( $this->get_option( 'page_member_directory' ) ) : '';
		if ( ! empty( $members_page ) ) $post_types[ 'member' ][ 'rewrite' ][ 'slug' ] = $members_page;

		$this->post_types_fields = $post_types;

		foreach ( $post_types as $slug => $args ) {
			$slug = 'p7mp_' . $slug;
			register_post_type( $slug, $args );

			if ( isset( $args[ 'fields' ] ) ) {
				$box = new_cmb2_box( array(
					'id'               => $slug . '_edit',
					'title'            => $args[ 'labels' ][ 'singular_name' ],
					'object_types'     => array( $slug ),
					'show_names'       => true,
				) );

				$this->add_fields( $box, $args[ 'fields' ], '_' );
			}
		}
	}

	/**
	 * Calls methods that run at the init hook.
	 *
	 * @return void
	 */
	public function init() {
		$this->disallow_dashboard_access();

		add_shortcode( 'member_profile',      array( $this, 'shortcode_member_profile'      ) );
		add_shortcode( 'member_login_box',    array( $this, 'shortcode_member_login_box'    ) );
		add_shortcode( 'member_contact_form', array( $this, 'shortcode_member_contact_form' ) );
		add_shortcode( 'member_payment_form', array( $this, 'shortcode_member_payment_form' ) );
		add_shortcode( 'member_directory',    array( $this, 'shortcode_member_directory'    ) );
		add_shortcode( 'member_list_terms',   array( $this, 'shortcode_member_list_terms'   ) );
		add_shortcode( 'member_map',          array( $this, 'shortcode_member_map'          ) );
	}

	/**
	 * Calls methods that run at the admin_init hook.
	 *
	 * @return void
	 */
	public function admin_init() {
		$this->modify_posted_values();
		$this->import_members();
		$this->export_members();

		foreach ( $this->post_types_fields as $k => $v ) {
			add_filter( "manage_edit-p7mp_{$k}_sortable_columns", array( $this, 'posts_table' ) );
		}
	}

	/**
	 * Calls methods that run at the cmb2_init hook.
	 *
	 * @return void
	 */
	public function cmb2_init() {
		$this->user_taxonomies();
		$this->post_types();
		$this->user_meta_init();
		$this->login_form_init();
		$this->contact_form_init();
		$this->payment_form_init();
		$this->members_only_init();
	}

	/**
	 * Calls methods that run at the cmb2_admin_init hook.
	 *
	 * @return void
	 */
	public function cmb2_admin_init() {
		$this->options_tabs();
		$this->options_init();
	}

	/**
	 * Calls methods that at the cmb2_after_init hook.
	 *
	 * @return void
	 */
	public function cmb2_after_init() {
		$this->modify_posted_values();
		$this->process_user_profile_submission();
		$this->process_user_login_submission();
		$this->process_member_contact_submission();
		$this->process_dues_payment_submission();
	}

	/**
	 * Checks to see if the current user is a member.
	 *
	 * @param bool $force_check
	 *
	 * @return bool
	 */
	public function is_member( $force_check = false ) {
		if ( isset( $this->is_member ) && ! $force_check ) return $this->is_member;

		if ( is_user_logged_in() && in_array( 'p7mp', (array) $this->user()->roles ) ) return $this->is_member = true;

		return $this->is_member = false;
	}

	/**
	 * Returns the current logged in user.
	 *
	 * @return WP_User object $this->user
	 */
	private function user() {
		if ( isset( $this->user ) ) return $this->user;

		return $this->user = wp_get_current_user();
	}

	/**
	 * Returns page view type.
	 *
	 * @return str $this->view
	 */
	private function view() {
		if ( 'admin'  === $this->view                       ) return $this->view;
		if ( 'member' === $this->view || $this->is_member() ) return $this->view = 'member';
		return $this->view;
	}

	/**
	 * Redirects members who try to visit the admin dashboard.
	 *
	 * @return void
	 */
	private function disallow_dashboard_access() {
		if ( 'admin' === $this->view() && $this->is_member() ) {
			$dest = $this->get_option( 'page_profile' );
			$dest = ! empty( $dest ) ? get_the_permalink( $dest ) : home_url();
			wp_redirect( $dest );
			exit();
		}
	}

	/**
	 * Returns a matched set of posted values with the wp_user fields they go with.
	 *
	 * @param str   $field
	 * @param array $fields
	 * @param array $values
	 * @param str   $prefix
	 * @param bool  $use_labels
	 *
	 * @return array $output
	 */
	private function get_matching_fields( $field, $fields, $values, $prefix = '', $use_labels = false ) {
		$output = array();

		foreach ( $values as $key => $value ) {
			if ( ! empty( $prefix ) ) $key = str_replace( $prefix, '', $key );
			
			$f = $fields[ $key ][ $field ];
			if ( is_string( $f ) ) {
				if ( false !== strpos( $f, ' ' ) ) {
					$parts = explode( ' ', $f );
					if ( ! empty( $output[ $parts[ 0 ] ][ $parts[ 1 ] ] ) ) $output[ $parts[ 0 ] ][ $parts[ 1 ] ] .= ' ';
					$output[ $parts[ 0 ] ][ $parts[ 1 ] ] .= $value;
				} else {
					if ( ! empty( $output[ $f ] ) ) $output[ $f ] .= ' ';
					if ( $use_labels && isset( $fields[ $f ][ 'options' ][ $value ] ) && is_string( $fields[ $f ][ 'options' ][ $value ] ) && ! empty( $fields[ $f ][ 'options' ][ $value ] ) ) {
						$output[ $f ] .= $fields[ $f ][ 'options' ][ $value ];
					} else if ( $use_labels && isset( $fields[ $f ][ 'options' ] ) && is_string( $fields[ $f ][ 'options' ] ) && ! empty( $fields[ $f ][ 'options' ] ) ) {
						$labels = $this->call_user_func( str_replace( '_with_price', '', $fields[ $f ][ 'options' ] ) );
						$output[ $f ] .= $labels[ $value ];
					} else {
						$output[ $f ] .= $value;
					}
				}
			} else if ( is_array( $f ) ) {
				foreach ( $f as $k ) {
					if ( ! empty( $output[ $k ] ) ) $output[ $k ] .= ' ';
					$output[ $k ] .= $value;
				}
			}
		}

		return $output;
	}

	/**
	 * Returns posted values without fields that have [ 'attributes' ][ 'type' ] === 'password'.
	 *
	 * @param array $fields
	 * @param array $values
	 * @param str   $prefix
	 *
	 * @return array $output
	 */
	private function unset_passwords( $fields, $values, $prefix = '' ) {
		foreach ( $values as $k => $v ) {
			if ( 'password' === $fields[ str_replace( $prefix, '', $k ) ][ 'attributes' ][ 'type' ] ) {
				unset( $values[ $k ] );
			}
		}

		return $values;
	}

	/**
	 * Returns.
	 *
	 * @param array $fields
	 * @param array $values
	 * @param str   $prefix
	 *
	 * @return array $output
	 */
	private function validate( $fields, $values, $prefix ) {
		$output = array();
		foreach ( $fields as $k => $v ) {
			if ( isset( $v[ 'visibility' ] ) && ! in_array( $this->view(), $v[ 'visibility' ] ) ) continue;
			if ( 'password' === $v[ 'attributes' ][ 'type' ] && 'member' === $this->view() )      continue;

			if ( isset( $v[ 'attributes' ][ 'required' ] ) && empty( $values[ $prefix . $k ] ) ) {
				$output[ $prefix . $k ] = __( 'This field is required.', 'p7-mp' );
			}
		}
		if ( ! empty( $output ) ) return $output;

		$output = $values;
		foreach ( $values as $k => $v ) {
			$func = $fields[ str_replace( $prefix, '', $k ) ][ 'validator' ];

			if ( empty( $func ) ) {
				unset( $output[ $k ] );
				continue;
			}

			if ( false !== strpos( $func, 'match_' ) ) {
				if ( 0 === strcmp( $v, $values[ $prefix . str_replace( 'match_', '', $func ) ] ) ) {
					$result = true;
				} else {
					$result = __( 'Not a match.', 'p7-mp' );
				}
			} else {
				$result = $this->call_user_func( $func, $v );
			}

			if ( true === $result ) {
				unset( $output[ $k ] );
			} else {
				$output[ $k ] = $result;
			}
		}

		return ! empty( $output ) ? $output : false;
	}

	/**
	 * Returns.
	 *
	 * @param str $response
	 *
	 * @return bool
	 */
	private function is_robot( $response ) {
		if ( empty( $this->get_option( 'google_recaptcha_site_key' ) ) || empty( $this->get_option( 'google_recaptcha_secret_key' ) ) ) return false;

		if ( ! empty( $response ) ) {
			$context = stream_context_create( array(
				'http' => array(
					'header'  => 'Content-type: application/x-www-form-urlencoded',
					'method'  => 'POST',
					'content' => http_build_query( array(
						'secret'   => $this->get_option( 'google_recaptcha_secret_key' ),
						'response' => $response,
					) ),
				),
			) );

			$result = json_decode( file_get_contents( 'https://www.google.com/recaptcha/api/siteverify', false, $context ) );
			
			if ( ! empty( $result ) && $result->success != 1 ) {
				return __( 'Please prove you are not a robot.', 'p7-mp' );
			} else if ( empty( $result->challenge_ts ) ) {
				return __( 'Error connecting to verification server. Please try again later.', 'p7-mp' );
			}
			return false;
		}

		return __( 'There was an unknown error with the reCAPTCHA verification process. Please try again later or contact us.', 'p7-mp' );
	}

	/**
	 * Returns the user defined value of an option or its default value.
	 *
	 * @param str $option
	 *
	 * @return mixed
	 */
	public function get_option( $option ) {
		if ( ! empty( $this->options[ $option ] ) ) return $this->options[ $option ];

		if ( isset( $this->options_boxes[ $option ][ 'default' ] ) ) {
			$output = $this->call_user_func( $this->options_boxes[ $option ][ 'default' ] );
			if ( false !== $output ) return $output;

			return $this->options_boxes[ $option ][ 'default' ];
		}

		return false;
	}

	/**
	 * Returns the current logged in user's user_meta data.
	 *
	 * @param str $option
	 *
	 * @return mixed
	 */
	public function user_meta( $option = '' ) {
		if ( ! $this->is_member() ) return false;

		if ( ! is_object( $this->user_meta ) ) $this->user_meta = (object) $this->flatten_array( get_user_meta( $this->user()->ID ) );

		if ( empty( $option ) ) return $this->user_meta;

		$option = $this->pu . $option;
		return $this->user_meta->$option;
	}

	/**
	 * Converts an array of arrays to a flat array.
	 * Only applies to array elements whose value is an array with a single element.
	 *
	 * @param array $r
	 *
	 * @return array $r
	 */
	public function flatten_array( $r ) {
		if ( empty( $r ) || ! is_array( $r ) ) return $r;

		foreach ( $r as $k => $v ) {
			if ( is_array( $v ) && 1 === count( $v ) ) {
				$r[ $k ] = reset( $v );
			}
		}

		return $r;
	}

	/**
	 * Calls user function.
	 *
	 * @param str   $func
	 * @param mixed ...$args
	 *
	 * @return mixed
	 */
	private function call_user_func() {
		$args = func_get_args();
		$func = $args[ 0 ];

		if ( function_exists( $func ) ) {
			if ( 2 === count( $args ) ) return call_user_func( $func, $args[ 1 ] );
			$re = call_user_func( $func );
			return $re;
		} else if ( method_exists( $this, $func ) ) {
			if ( 2 === count( $args ) ) return call_user_func( array( $this, $func ), $args[ 1 ] );
			return call_user_func( array( $this, $func ) );
		}

		return false;
	}

	/**
	 * Generates HTML to open a fieldset element in a CMB2 form.
	 * For use on field of type: 'title'.
	 *
	 * @param bool $field_args
	 * @param bool $field
	 *
	 * @return void
	 */
	public function open_fieldset( $field_args, $field ) {
		if ( $this->fieldset_open ) echo '</fieldset>'; $this->fieldset_open = true;
		echo '<fieldset class="' . $field->args( 'id' ) . '"><legend>' . $field->args( 'name' ) . '</legend>';
	}

	/**
	 * Closes a fieldset.
	 * For use on field of type: 'title'.
	 *
	 * @param bool $field_args
	 * @param bool $field
	 *
	 * @return void
	 */
	public function close_fieldset( $field_args, $field ) {
		echo '</fieldset>';
	}

	/**
	 * Generates HTML to render a main header as part of a CMB2 form.
	 * For use on field of type: 'title'.
	 *
	 * @param bool $field_args
	 * @param bool $field
	 *
	 * @return void
	 */
	public function form_heading( $field_args, $field ) {
		echo '<h2>' . $field->args( 'name' ) . '</h2>';
	}

	/**
	 * Modifies the $_POST content when forms are submitted.
	 *
	 * @return void
	 */
	private function modify_posted_values() {
		$taxonomies = $this->get_matching_fields( 'wp_user_tax', $this->user_meta_fields, $_POST, $this->pu );
		foreach ( $taxonomies as $k => $v ) {
			$_POST[ 'p7mp_' . $k ] = $_POST[ $k ];
		}
	}

	/**
	 * Checks which form is being handled.
	 *
	 * @param string $key
	 *
	 * @return bool
	 */
	private function verify_nonce( $key ) {
		$key = $key . 'nonce';
		if ( empty( $_POST ) )                                                          return false;
		if ( ! isset( $_POST[ 'submit-cmb' ], $_POST[ 'object_id' ], $_POST[ $key ] ) ) return false;
		if ( 'admin' === $this->view() )                                                return false;

		if ( ! wp_verify_nonce( $_POST[ $key ], $key ) ) {
			return new WP_Error( 'nonce_fail', __( 'Security check failed. Please try again.', 'p7-mp' ) );
		}

		return true;
	}

	/**
	 * Adds columns to custom post types.
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public function posts_table( $columns ) {
		$fields = $this->post_types_fields[ str_replace( 'p7mp_', '', $_GET[ 'post_type' ] ) ][ 'fields' ];

		if ( ! empty( $fields ) ) {
			$fields = array_filter( $fields, function( $v ) { return $v[ 'admin_column' ]; } );
			foreach ( $fields as $id => $args ) $columns[ $id ] = $args[ 'name' ];
		}

		$date = $columns[ 'date' ];
		unset( $columns[ 'date' ] );
		$columns[ 'date' ] = $date;

		return $columns;
	}

	/**
	 * Adds post data to custom columns in the posts table.
	 *
	 * @param str $column
	 * @param int $post_id
	 *
	 * @return str $val
	 */
	public function posts_table_row( $column, $post_id ) {
		$fields = $this->post_types_fields[ str_replace( 'p7mp_', '', $_GET[ 'post_type' ] ) ][ 'fields' ];

		if ( ! empty( $fields[ $column ] ) ) echo get_post_meta( $post_id, '_' . $column, true );
	}

	/**
	 * Filters posts.
	 *
	 * @param obj $query
	 *
	 * @return void
	 */
	public function filter_posts( $query ) {
		global $pagenow;

		$fields = $this->post_types_fields[ str_replace( 'p7mp_', '', $_GET[ 'post_type' ] ) ][ 'fields' ];

		if ( ! empty( $fields && is_admin() && $pagenow === 'edit.php' ) ) {
			$meta_query = array();
			foreach ( $_GET as $k => $v ) {
				if ( array_key_exists( ltrim( $k, '_' ), $fields ) && ! empty( $v ) && $fields[ ltrim( $k, '_' ) ][ 'admin_filter' ] ) {
					$meta_query[] = array(
						'key'     => $k,
						'value'   => $v,
						'compare' => 'LIKE',
					);
				}
			}

			if ( ! empty( $meta_query ) ) $query->set( 'meta_query', $meta_query );
		}
	}

	function user_search_columns( $search_columns, $search, $wp_user_query ) {
		$search_columns[] = 'user_url';
		
		return $search_columns;
	}

	/**
	 * Adds columns to user table.
	 *
	 * @param array $column
	 *
	 * @return array $column
	 */
	public function user_table( $column ) {
		unset( $column[ 'name' ], $column[ 'posts' ] );

		foreach ( $this->user_meta_fields as $id => $args ) {
			if ( $args[ 'admin_column' ] ) {
				$column[ $id ] = $args[ 'name' ];
			}
		}

		$column[ 'payments' ] = __( 'Payments', 'p7-mp' );

		return $column;
	}

	/**
	 * Adds user data to custom columns in the user table.
	 *
	 * @param str $val
	 * @param str $column
	 * @param int $user_id
	 *
	 * @return str $val
	 */
	public function user_table_row( $val, $column, $user_id ) {
		if ( 'payments' === $column ) {
			$user_login = get_userdata( $user_id )->user_login;

			$query = new WP_Query( array(
				'post_type'  => 'p7mp_dues_payment',
				'meta_query' => array(
					array(
						'key'   => '_member_account',
						'value' => $user_login,
					),
				),
			) );

			$val = '0';
			if ( 0 < $query->post_count ) {
				$val = '<a href="' . admin_url( 'edit.php?s&post_status=all&post_type=p7mp_dues_payment&action=-1&_member_account=' . $user_login, 'https' ) . '">' . $query->post_count . '</a>';
			}

			return $val;
		}

		$fields = $this->user_meta_fields;

		if ( isset( $fields[ $column ] ) ) {

			if ( is_string( $fields[ $column ][ 'options' ] ) ) {
				$fields[ $column ][ 'options' ] = $this->call_user_func( str_replace( '_with_price', '', $fields[ $column ][ 'options' ] ) );
			}

			if ( is_array( $fields[ $column ][ 'options' ] ) ) {
				$val = $fields[ $column ][ 'options' ][ get_user_meta( $user_id, $this->pu . $column, true ) ];
				return is_array( $val ) ? implode( ', ', $val ) : $val;
			}

			$val = get_user_meta( $user_id, $this->pu . $column, true );

		}

		return is_array( $val ) ? implode( ', ', $val ) : $val;
	}

	/**
	 * Makes custom user table columns sortable.
	 *
	 * @param array $columns
	 *
	 * @return array $columns
	 */
	public function user_table_columns_sortable( $columns ) {
		foreach ( $this->user_meta_fields as $id => $args ) {
			if ( $args[ 'admin_column' ] ) {
				$columns[ $id ] = $args[ 'name' ];
			}
		}

		return $columns;
	}

	/**
	 * Orders user columns.
	 *
	 * @param obj $query
	 *
	 * @return void
	 */
	public function user_table_columns_orderby( $query ) {
		if ( ! is_admin() ) return;

		$orderby = $query->get( 'orderby' );

		$order_type = 'meta_value';

		foreach ( $this->user_meta_fields as $id => $args ) {
			if ( $orderby === $args[ 'name' ] ) {
				$query->set( 'meta_key', $this->pu . $id );
				$query->set( 'orderby', $order_type );
				break;
			}
		}
	}

	/**
	 * Adds filters to top of user list.
	 *
	 * @param str $which
	 *
	 * @return void
	 */
	public function add_user_filters( $which ) {
		if ( 'bottom' === $which ) return;

		$output = '';
		$style  = ' margin-left: 50px;';
		$fields = $this->user_meta_fields;
		foreach ( $fields as $id => $args ) {

			if ( $args[ 'admin_filter' ] ) {
				$output .= '<select name="' . $this->pu . $id . '" style="float: none;' . $style . '">';
				$style = '';
				$output .= '<option value="">' . $args[ 'name' ] . '</option>';

					if ( is_string( $args[ 'options' ] ) ) $args[ 'options' ] = $this->call_user_func( str_replace( '_with_price', '', $args[ 'options' ] ) );
					unset( $args[ 'options' ][ '' ] );
					foreach ( $args[ 'options' ] as $k => $v ) {
						$output .= '<option value="' . $k . '" ' . selected( $k, $_GET[ $this->pu . $id ], false ) . '>' . $v . '</option>';
					}

				$output .= '</select>';
			}

		}

		if ( ! empty( $output ) ) {
			echo $output;
			submit_button( __( 'Filter', 'p7-mp' ), null, $which, false );
		}
	}

	/**
	 * Filters users.
	 *
	 * @param obj $query
	 *
	 * @return void
	 */
	public function filter_users( $query ) {
		global $pagenow;
		if ( ! is_admin() || 'users.php' !== $pagenow ) return;

		$fields = $this->user_meta_fields;
		$meta_query = array();
		foreach ( $_GET as $k => $v ) {
			$id = str_replace( $this->pu, '', $k );
			if ( false !== strpos( $k, $this->pu ) && ! empty( $v ) && $fields[ $id ][ 'admin_filter' ] ) {
				$meta_query[] = array(
					'key'     => $k,
					'value'   => $v,
					'compare' => false !== strpos( $fields[ $id ][ 'type' ], 'multi' ) ? 'LIKE' : '==',
				);
			}
		}

		if ( ! empty( $meta_query ) ) $query->set( 'meta_query', $meta_query );
	}

	/**
	 * Updates some user meta fields anytime user profile is updated.
	 *
	 * @param int $user_id
	 *
	 * @return void
	 */
	public function profile_update( $user_id ) {
		update_user_meta( $user_id, $this->pu . 'lat',                '' );
		update_user_meta( $user_id, $this->pu . 'lng',                '' );
		update_user_meta( $user_id, $this->pu . 'geocode_fail_time',  '' );
		update_user_meta( $user_id, $this->pu . 'geocode_fail_count', '' );

		if ( get_option( 'p7mp_bad_address_notice' ) == $user_id ) update_option( 'p7mp_bad_address_notice', '' );
	}

	/**
	 * Handles images uploaded by users.
	 *
	 * @param str $key
	 *
	 * @return int $attachment_id
	 */
	private function user_image_upload( $key ) {
		if (
			   empty( $_FILES )
			|| ! isset( $_FILES[ $key ] )
			|| isset( $_FILES[ $key ][ 'error' ] ) && 0 !== $_FILES[ $key ][ 'error' ]
		) {
			return false;
		}

		if ( empty( array_filter( $_FILES[ $key ] ) ) ) return false;

		// Include the WordPress media uploader API.
		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		
		$attachment_id = media_handle_upload( $key, 0 );
		return $attachment_id;
	}

	/**
	 * Geocodes an address.
	 *
	 * @param int   $user_id
	 * @param array $user_meta
	 * @param array $map_fields
	 *
	 * @return array $user_meta
	 */
	private function geocode( $user_id, $user_meta, $map_fields ) {
		$geocode_api_interval = 60 * 60 * 1;
		if ( $geocode_api_interval > time() - get_option( 'p7mp_geocode_api_limit_reached' ) ) return $user_meta;
		if ( $geocode_api_interval > time() - $user_meta[ $this->pu . 'geocode_fail_time' ]  ) return $user_meta;
		if ( 3 < $user_meta[ $this->pu . 'geocode_fail_count' ] ) {
			update_option( 'p7mp_bad_address_notice', $user_id );
			return $user_meta;
		}

		$address  =        $map_fields[ 'address' ];
		$address .= ', ' . $map_fields[ 'city'    ];
		$address .= ', ' . $map_fields[ 'state'   ];
		$address .= '  ' . $map_fields[ 'zip'     ];
		
		$request  = wp_remote_get( 'https://maps.google.com/maps/api/geocode/json?address=' . urlencode( $address ) . '&key=' . $this->get_option( 'google_maps_api_key' ) );
		$response = wp_remote_retrieve_body( $request );

		if ( 200 !== wp_remote_retrieve_response_code( $request ) ) return $user_meta;

		$response = json_decode( $response, true );

		if ( 'OVER_QUERY_LIMIT' === $response[ 'status' ] ) {
			update_option( 'p7mp_geocode_api_limit_reached', time() );
			return $user_meta;
		}

		if ( 'ZERO_RESULTS' === $response[ 'status' ] ) {
			update_user_meta( $user_id, $this->pu . 'geocode_fail_time', time() );
			update_user_meta( $user_id, $this->pu . 'geocode_fail_count', $user_meta[ $this->pu . 'geocode_fail_count' ] + 1 );
			return $user_meta;
		}

		if ( 'OK' === $response[ 'status' ] ) {
			$this->profile_update( $user_id );

			$lat = $response[ 'results' ][ 0 ][ 'geometry' ][ 'location' ][ 'lat' ];
			update_user_meta( $user_id, $this->pu . 'lat', $lat );
			$user_meta[ $this->pu . 'lat' ] = $lat;

			$lng = $response[ 'results' ][ 0 ][ 'geometry' ][ 'location' ][ 'lng' ];
			update_user_meta( $user_id, $this->pu . 'lng', $lng );
			$user_meta[ $this->pu . 'lng' ] = $lng;
		}

		return $user_meta;
	}

	/**
	 * Adds a subscriber to a MailChimp mailing list.
	 *
	 * @param array $mcdata
	 *
	 * @return int $http_code
	 */
	private function mail_chimp_add( $mcdata ) {
		if ( empty( $mcdata[ 'email_address' ] ) || empty( $this->get_option( 'mailchimp_api_key' ) ) || empty( $this->get_option( 'mailchimp_list_id' ) ) ) return false;
		
		$merge_fields = array();
		foreach ( $mcdata as $k => $v ) {
			if ( 0 === strpos( $k, 'mf_' ) ) {
				$merge_fields[ str_replace( 'mf_', '', $k ) ] = $v;
				unset( $mcdata[ $k ] );
			}
		}

		$mcdata[ 'merge_fields' ] = $merge_fields;
		$mcdata[ 'status'       ] = 'subscribed';

		$mc_api_key     = $this->get_option( 'mailchimp_api_key' );
		$mc_list_id     = $this->get_option( 'mailchimp_list_id' );
		$mc_member_id   = md5( strtolower( $mcdata[ 'email_address' ] ) );
		$mc_data_center = substr( $mc_api_key, strpos( $mc_api_key, '-' ) + 1 );
		$url = 'https://' . $mc_data_center . '.api.mailchimp.com/3.0/lists/' . $mc_list_id . '/members/' . $mc_member_id;

		$ch = curl_init( $url );

		curl_setopt( $ch, CURLOPT_USERPWD,        'user:' . $mc_api_key                     );
		curl_setopt( $ch, CURLOPT_HTTPHEADER,     array( 'Content-Type: application/json' ) );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true                                      );
		curl_setopt( $ch, CURLOPT_TIMEOUT,        10                                        );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST,  'PUT'                                     );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false                                     );
		curl_setopt( $ch, CURLOPT_POSTFIELDS,     json_encode( $mcdata )                    );

		$result    = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		return $http_code;
	}

	/**
	 * Generates a member registration/profile form.
	 *
	 * @param array $atts Shortcode atts.
	 *
	 * @return string $output
	 */
	public function shortcode_member_profile( $atts = array() ) {
		$cmb = cmb2_get_metabox( $this->pu, $this->user()->ID );

		$output = '';

		if ( ( $error = $cmb->prop( 'submission_error' ) ) && is_wp_error( $error ) ) {
			$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . sprintf( __( 'There was an error in the submission: %s', 'p7-mp' ), '<strong>' . $error->get_error_message() . '</strong>' ) . '</div>';
		}

		if ( ( $robot = $cmb->prop( 'grecaptcha_error' ) ) && is_wp_error( $robot ) ) {
			$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . sprintf( __( 'There was an error in the submission: %s', 'p7-mp' ), '<strong>' . $robot->get_error_message() . '</strong>' ) . '</div>';
		}
		
		if ( 'true' === $_GET[ 'member_updated' ] ) {
			$output .= '<div class="alert alert-success">' . __( 'Your profile has been updated.', 'p7-mp' ) . '</div>';
		}

		$form_config = array();
		$form_config[ 'save_button' ] = $this->is_member() ? __( 'Update Profile', 'p7-mp' ) : __( 'Join Now', 'p7-mp' );

		$output .= cmb2_get_metabox_form( $cmb, $this->user()->ID, $form_config );

		$img_fields = array_filter( $this->user_meta_fields, function( $item ) {
			return 'file' === $item[ 'attributes' ][ 'type' ] ? true : false;
		} );
		
		$images = array();
		foreach ( $img_fields as $k => $v ) {
			if ( ! empty( $this->user_meta( $k ) ) ) $images[ $this->pu . $k ] = $this->user_meta( $k );
		}

		if ( ! empty( $images ) ) {
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var MPRI = '<? echo $this->svg( 'close' ); ?>';
							var MPUI = <?php echo json_encode( $images ); ?>;
							$.each(MPUI, function(key, data){
								if(data.length){
									var $el = $('form#<?php echo $this->pu; ?> #'+key);
									var $div = $('<div />', {
											'class': 'p7-mp-user-img',
										});
									var $img = $('<img />', {
										src: data,
										alt: 'Remove Image',
									}).appendTo($div);
									var $a = $('<a />', {
										href: '#',
										title: 'Remove Image',
										html: MPRI,
										click: function(e){
											e.preventDefault();
											$(this).closest('div.p7-mp-user-img').next('input[type="hidden"]').remove();
											$(this).prev('img').remove();
											$(this).before($el);
											$(this).remove();
										},
									}).appendTo($div);

									$el.after($('<input />', {
										type: 'hidden',
										name: key,
										value: data,
									})).after($div).remove();
								}
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		if ( $validation_errors = $cmb->prop( 'validation_errors' ) ) {
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var MPVE = <?php echo json_encode( $validation_errors ); ?>;
							$.each(MPVE, function(key, data){
								$('form#<?php echo $this->pu; ?> #'+key).addClass('p7-mp-validation-error').after('<div class="p7-mp-validation-error-message alert alert-danger">'+data+'</div>');
							});
							$('form#<?php echo $this->pu; ?>').on('click', '.p7-mp-validation-error', function(){
								$(this).removeClass('p7-mp-validation-error');
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		if ( 'non-member' === $this->view() && ! empty( $this->get_option( 'google_recaptcha_site_key' ) ) && ! empty( $this->get_option( 'google_recaptcha_secret_key' ) ) ) {
			ob_start();
			?>
				<script async defer src="https://www.google.com/recaptcha/api.js"></script>
				<script>
					function enableSubmit(){
						jQuery('form#<?php echo $this->pu; ?> input[type="submit"]').prop('disabled', false).css('cursor', 'pointer');
					}
					(function($){
						'use strict';
						$(document).ready(function(){
							$('form#<?php echo $this->pu; ?> input[type="submit"]').prop('disabled', true).css('cursor', 'not-allowed');
						});
						$('form#<?php echo $this->pu; ?> input[type="submit"]').before('<div class="g-recaptcha" data-callback="enableSubmit" data-sitekey="<?php echo esc_attr( $this->get_option( 'google_recaptcha_site_key' ) ); ?>"></div>');
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		return $output;
	}

	/**
	 * Handles user profile form submission.
	 *
	 * @return void
	 */
	private function process_user_profile_submission() {
		$nonce = $this->verify_nonce( $this->pu );
		if ( false === $nonce ) return false;

		$box = cmb2_get_metabox( $this->pu, $this->user()->ID );

		if ( is_wp_error( $nonce ) ) return $box->prop( 'submission_error', $nonce );

		$sanitized_values  = $box->get_sanitized_values( $_POST );
		$validation_errors = $this->validate( $this->user_meta_fields, $sanitized_values, $this->pu );
		$is_robot          = 'non-member' === $this->view() ? $this->is_robot( $_POST[ 'g-recaptcha-response' ] ) : false;
		
		if ( $validation_errors || $is_robot ) {
			if ( $validation_errors ) {
				$box->prop( 'submission_error', new WP_Error( 'validation_fail', __( 'Please correct the errors below.', 'p7-mp' ) ) );
				$box->prop( 'validation_errors', $validation_errors );
			}

			if ( $is_robot ) {
				$box->prop( 'grecaptcha_error', new WP_Error( 'grecaptcha_fail', $is_robot ) );
			}
		
			return $box;
		}

		$dest = add_query_arg( 'member_updated', 'true' );

		$userdata = $this->get_matching_fields( 'wp_user_field', $this->user_meta_fields, $sanitized_values, $this->pu );
		$userdata[ 'rich_editing'         ] = 'false';
		$userdata[ 'syntax_highlighting'  ] = 'false';
		$userdata[ 'show_admin_bar_front' ] = 'false';
		if ( $this->is_member() ) {
			$userdata[ 'ID' ]         = $this->user()->ID;
			$userdata[ 'user_login' ] = $this->user()->user_login;
			$user_id = wp_insert_user( $userdata );
		} else {
			$userdata[ 'role' ] = 'p7mp';
			$user_id = wp_insert_user( $userdata );
			update_user_meta( $user_id, 'p7mp_u_member_date_joined', date( 'Y-m-d' ) );
			update_user_meta( $user_id, 'p7mp_u_member_expiry',      date( 'Y-m-d' ) );
			update_user_meta( $user_id, 'p7mp_u_member_status',      'inactive'      );

			wp_signon( array(
				'user_login'    => $userdata[ 'user_login' ],
				'user_password' => $userdata[ 'user_pass' ],
				'remember'      => false,
			), true );

			$dest = ! empty( $this->get_option( 'page_payment_form' ) ) ? get_the_permalink( $this->get_option( 'page_payment_form' ) ) : $dest;
		}

		// If there is a snag, inform the user.
		if ( is_wp_error( $user_id ) ) return $box->prop( 'submission_error', $user_id );

		// Make sure unhashed passwords are never stored in the database.
		$sanitized_values = $this->unset_passwords( $this->user_meta_fields, $sanitized_values, $this->pu );

		$box->save_fields( $user_id, 'user', $sanitized_values );

		$this->mail_chimp_add( $this->get_matching_fields( 'mc_sub_field', $this->user_meta_fields, $sanitized_values, $this->pu ) );

		$img_fields = array_filter( $this->user_meta_fields, function( $item ) {
			return 'file' === $item[ 'attributes' ][ 'type' ] ? true : false;
		} );
		
		foreach ( $img_fields as $k => $v ) {
			$img_id = $this->user_image_upload( $this->pu . $k );
			if ( $img_id ) {
				$img_id = wp_get_attachment_image_src( $img_id, 'medium' );
				update_user_meta( $user_id, $this->pu . $k, $img_id[ 0 ] );
			}
		}

		wp_redirect( esc_url_raw( $dest ) );
		exit();
	}

	/**
	 * Generates a member status widget or a login form if not logged in.
	 *
	 * @param array $atts
	 *
	 * @return str $output
	 */
	public function shortcode_member_login_box( $atts = array() ) {
		$output = '';

		$instance = shortcode_atts( array(
			'hide' => false,
			'link' => __( 'Sign In', 'p7-mp' ),
		), $atts, 'member_login_box' );

		$instance[ 'hide' ] = 'false' === $instance[ 'hide' ] || ! $instance[ 'hide' ] ? false : true;
		$instance[ 'link' ] = sanitize_text_field( $instance[ 'link' ] );

		$output .= $instance[ 'hide' ] ? '<a href="#" class="p7-mp-login-box-activator">' . $instance[ 'link' ] . '</a>' : '';

		$hidden = $instance[ 'hide' ] ? ' hidden over' : '';
		$output .= '<div class="p7-mp-login-box' . $hidden . '"><div>';

			if ( $this->is_member() ) {
				$output .= '<p>' . __( 'Welcome', 'p7-mp' ) . ' ' . $this->user()->first_name . '</p>';

				$status = $this->user_meta( 'member_status' );
				if ( 'active' !== $status ) {
					$output .= '<div class="alert alert-danger">' . __( 'ASA Membership Inactive', 'p7-mp' ) . '</div>';
					$output .= ! empty( $ppf = $this->get_option( 'page_payment_form' ) ) ? '<a class="btn btn-success button button-success" href="' . get_the_permalink( $ppf ) . '">' . __( 'Activate Now', 'p7-mp' ) . '</a>' : '';
				}

				$output .= ! empty( $pp = $this->get_option( 'page_profile' ) ) ? '<a class="btn btn-primary button button-primary" href="' . get_the_permalink( $pp ) . '">' . __( 'Manage Profile', 'p7-mp' ) . '</a>' : '';

				global $wp;
				$output .= '<a class="btn btn-danger button button-danger" href="' . wp_logout_url( add_query_arg( 'member_logged_out', 'true', home_url( '/' ) . $wp->request ) ) . '">' . __( 'Log Out', 'p7-mp' ) . '</a>';

				$output .= ! empty( $pp = $this->get_option( 'page_payment_form' ) ) ? '<a class="btn btn-primary button button-primary" href="' . get_the_permalink( $pp ) . '">' . __( 'Renew/Upgrade Membership', 'p7-mp' ) . '</a>' : '';
			} else {
				$box = cmb2_get_metabox( $this->pl );

				if ( ( $error = $box->prop( 'submission_error' ) ) && is_wp_error( $error ) ) {
					$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . sprintf( __( 'There was an error in the submission: %s', 'p7-mp' ), '<strong>' . $error->get_error_message() . '</strong>' ) . '</div>';
				}

				if ( 'true' === $_GET[ 'member_logged_out' ] ) {
					$output .= '<div class="alert alert-info">' . __( 'You have been logged out.', 'p7-mp' ) . '</div>';
				}

				$output .= cmb2_get_metabox_form( $box, '', array( 'save_button' => __( 'Sign In', 'p7-mp' ) ) );

				$output .= ! empty( $pp = $this->get_option( 'page_profile' ) ) ? '<span class="p7-mp-not-a-member">' . __( 'Not a Member?', 'p7-mp' ) . '</span> <a class="btn btn-success button button-success p7-mp-register-btn" href="' . get_the_permalink( $pp ) . '">' . __( 'Register', 'p7-mp' ) . '</a>' : '';
				
				$output .= '<a class="p7-mp-forgot-pw" href="' . wp_lostpassword_url() . '">' . __( 'Forgot Password?', 'p7-mp' ) . '</a>';
			}

		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Handles user login.
	 *
	 * @return void
	 */
	private function process_user_login_submission() {
		$nonce = $this->verify_nonce( $this->pl );
		if ( false === $nonce ) return false;

		$box = cmb2_get_metabox( $this->pl );

		if ( is_wp_error( $nonce ) ) return $box->prop( 'submission_error', $nonce );

		$sanitized_values = $box->get_sanitized_values( $_POST );

		$creds = $this->get_matching_fields( 'wp_user_field', $this->login_form_fields, $sanitized_values, $this->pl );
		$creds[ 'remember' ] = true;

		$user = wp_signon( $creds, true );

		if ( is_wp_error( $user ) ) {
			if ( isset( $user->errors[ 'incorrect_password' ] ) ) $user = new WP_Error( 'incorrect_password', __( 'Incorrect username or password.', 'p7-mp' ) );
			return $box->prop( 'submission_error', $user );
		}

		wp_redirect( esc_url_raw( add_query_arg( 'member_logged_in', 'true' ) ) );
		exit();
	}

	/**
	 * Generates a contact form.
	 *
	 * @return str $output
	 */
	public function shortcode_member_contact_form( $atts = array() ) {
		$output = '';

		$cmb = cmb2_get_metabox( $this->pc );

		if ( $error = $cmb->prop( 'submission_error' ) ) {
			$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . sprintf( __( 'There was an error in the submission: %s', 'p7-mp' ), '<strong>' . $error->get_error_message() . '</strong>' ) . '</div>';
		} else {
			if ( 'true' === $_GET[ 'email_sent' ] ) {
				$output .= '<div class="alert alert-success p7-mp-submission-success-message">' . __( 'Your message has been sent to the member email address we have on file.', 'p7-mp' ) . '</div>';
			} else if ( 'false' === $_GET[ 'email_sent' ] ) {
				$output .= '<div class="alert alert-warning p7-mp-submission-warning-message">' . __( 'There was a problem sending email. Please contact us.', 'p7-mp' ) . '</div>';
			}
		}

		$atts = shortcode_atts( array(
			'member' => '',
		), $atts, 'member_contact_form' );

		$user = get_user_by( 'login', $atts[ 'member' ] );
		if ( $user ) {
			$cmb->add_field( array(
				'id'      => $this->pc . 'member_id',
				'type'    => 'hidden',
				'default' => $user->ID,
			) );

			$output .= cmb2_get_metabox_form( $cmb, '', array( 'save_button' => __( 'Send Message', 'p7-mp' ) ) );
		} else {
			$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . __( 'The "member" parameter is required. It must contain a valid username. <br />e.g. [member_contact_form member="username"]', 'p7-mp' ) . '</div>';
		}

		if ( $validation_errors = $cmb->prop( 'validation_errors' ) ) {
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var MCVE = <?php echo json_encode( $validation_errors ); ?>;
							$.each(MCVE, function(key, data){
								$('form#<?php echo $this->pc; ?> #'+key).addClass('p7-mp-validation-error').after('<div class="p7-mp-validation-error-message alert alert-danger">'+data+'</div>');
							});
							$('form#<?php echo $this->pc; ?>').on('click', '.p7-mp-validation-error', function(){
								$(this).removeClass('p7-mp-validation-error');
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		if ( 'non-member' === $this->view() && ! empty( $this->get_option( 'google_recaptcha_site_key' ) ) && ! empty( $this->get_option( 'google_recaptcha_secret_key' ) ) ) {
			ob_start();
			?>
				<script async defer src="https://www.google.com/recaptcha/api.js"></script>
				<script>
					function enableSubmit(){
						jQuery('form#<?php echo $this->pc; ?> input[type="submit"]').prop('disabled', false).css('cursor', 'pointer');
					}
					(function($){
						'use strict';
						$(document).ready(function(){
							$('form#<?php echo $this->pc; ?> input[type="submit"]').prop('disabled', true).css('cursor', 'not-allowed');
						});
						$('form#<?php echo $this->pc; ?> input[type="submit"]').before('<div class="g-recaptcha" data-callback="enableSubmit" data-sitekey="<?php echo esc_attr( $this->get_option( 'google_recaptcha_site_key' ) ); ?>"></div>');
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		return $output;
	}

	/**
	 * Handles member contact form submissions.
	 *
	 * @return void
	 */
	private function process_member_contact_submission() {
		$nonce = $this->verify_nonce( $this->pc );
		if ( false === $nonce ) return false;

		$box = cmb2_get_metabox( $this->pc );

		if ( is_wp_error( $nonce ) ) return $box->prop( 'submission_error', $nonce );

		$sanitized_values  = $box->get_sanitized_values( $_POST );
		$validation_errors = $this->validate( $this->user_meta_fields, $sanitized_values, $this->pc );
		$is_robot          = 'non-member' === $this->view() ? $this->is_robot( $_POST[ 'g-recaptcha-response' ] ) : false;

		if ( $validation_errors || $is_robot ) {
			if ( $validation_errors ) {
				$box->prop( 'submission_error', new WP_Error( 'validation_fail', __( 'Please correct the errors below.', 'p7-mp' ) ) );
				$box->prop( 'validation_errors', $validation_errors );
			}

			if ( $is_robot ) {
				$box->prop( 'grecaptcha_error', new WP_Error( 'grecaptcha_fail', $is_robot ) );
			}
			
			return $box;
		}

		$contactee = get_user_by( 'id', $_POST[ $this->pc . 'member_id' ] );
		$blog_name = htmlspecialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
		$to        = array();
		$contacts  = $this->get_option( 'admin_contacts' );
		if ( is_array( $contacts ) ) {
			foreach ( $contacts as $contact ) {
				if ( $contact[ 'type' ] === 'to' ) {
					$to[] = $contact[ 'email' ];
				} else {
					$headers[] = $contact[ 'type' ] . ': ' . $contact[ 'email' ];
				}
			}
		}
		$subject = __( 'Inquiry from ', 'p7-mp' ) . $blog_name;
		$headers = array_unique( $headers );
		$to      = array_unique( $to );
		if ( empty( $to ) ) $to = array( $blog_name . ' <' . get_bloginfo( 'admin_email' ) . '>' );

		$message = '<dl>';
		$message .= '<dt>' . __( 'Request Sent To', 'p7-mp' ) . ':</dt><dd><a href="' . get_permalink( p7mp_get_member_post( $contactee->ID ) ) . '">' . $contactee->display_name . '</a></dd>';
		if ( $this->is_member() ) {
			$message .= '<dt>' . __( 'Requested By', 'p7-mp' ) . ':</dt><dd><a href="' . get_permalink( p7mp_get_member_post( $this->user()->ID ) ) . '">' . $this->user()->display_name . '</a></dd>';
		}
		foreach ( $sanitized_values as $k => $v ) {
			$message .= '<dt>' . $this->contact_form_fields[ str_replace( $this->pc, '', $k ) ][ 'name' ] . ':</dt><dd>' . $v . '</dd>';
		}
		$message .= '</dl>';

		// admin email
		wp_mail( $to, $subject, $message, $headers );

		// user email
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( $this->is_member() ) $headers[] = 'Reply-To: ' . $this->user()->user_email;
		$to = $contactee->user_email;

		$sent = false;
		if ( wp_mail( $to, $subject, $message, $headers ) ) $sent = true;

		if ( $sent ) {
			$redirect = add_query_arg( 'email_sent', 'true', $redirect );
		} else {
			$redirect = add_query_arg( 'email_sent', 'false', $redirect );
		}
		wp_redirect( esc_url_raw( $redirect ) );
		exit();
	}

	/**
	 * Generates a payment form.
	 *
	 * @return str $output
	 */
	public function shortcode_member_payment_form( $atts = array() ) {
		$output = '';

		$cmb = cmb2_get_metabox( $this->pp );

		if ( $error = $cmb->prop( 'submission_error' ) ) {
			$output .= '<div class="alert alert-danger p7-mp-submission-error-message">' . sprintf( __( 'There was an error in the submission: %s', 'p7-mp' ), '<strong>' . $error->get_error_message() . '</strong>' ) . '</div>';
		} else if ( ! empty( $_GET ) ) {
			if ( 'true' === $_GET[ 'payment_received' ] ) {
				$output .= '<div class="alert alert-success p7-mp-submission-success-message">' . __( 'Thank you, your payment has been received.', 'p7-mp' ) . '</div>';
			}

			if ( 'true' === $_GET[ 'email_sent' ] ) {
				$output .= '<div class="alert alert-success p7-mp-submission-success-message">' . __( 'A receipt has been sent to the member email address we have on file.', 'p7-mp' ) . '</div>';
			} else if ( 'false' === $_GET[ 'email_sent' ] ) {
				$output .= '<div class="alert alert-warning p7-mp-submission-warning-message">' . __( 'There was a problem sending email. Please contact us for a receipt.', 'p7-mp' ) . '</div>';
			}

			if ( 'true' === $_GET[ 'offline_payment_email_sent' ] ) {
				$output .= '<div class="alert alert-success p7-mp-submission-success-message">' . __( 'We have received your membership application and we will contact you shortly to accept payment.', 'p7-mp' ) . '</div>';
			} else if ( 'false' === $_GET[ 'offline_payment_email_sent' ] ) {
				$output .= '<div class="alert alert-warning p7-mp-submission-warning-message">' . __( 'There was a problem sending email. Please contact us.', 'p7-mp' ) . '</div>';
			}

			return $output;
		}

		$output .= cmb2_get_metabox_form( $cmb, '', array( 'save_button' => __( 'Submit Payment', 'p7-mp' ) ) );

		if ( 'yes' === $this->get_option( 'offline_payment' ) ) {
			$offline_payment_message = str_replace( array( "\r\n", "\r", "\n" ), "", wpautop( $this->get_option( 'offline_payment_message' ) ) );
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var $form      = $('form#<?php echo $this->pp; ?>');
							var $offline   = $form.find('.p7mp_p_section_offline_payment_info');
							var $cc        = $form.find('.p7mp_p_section_cc_info');
							var $ccElement = '';
							var $submit    = $form.find('input[type="submit"]');
							var submitText = $submit.val();
							$offline.hide().find('legend').after('<?php echo $offline_payment_message; ?>');
							$('[name="p7mp_p_payment_method"]').on('change', function(){
								if('offline' == $(this).val()){
									$offline.show();
									$ccElement = $cc.detach();
									$submit.val('Submit');
								}else{
									$offline.hide().after($ccElement);
									$submit.val(submitText);
								}
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		$cc_fee = floatval( $this->get_option( 'cc_fee' ) ) / 100;
		if ( 0 < $cc_fee && ! empty( $cc_fee ) ) {
			$cc_fee_message = '<p class="cmb2-metabox-description">'
							. sprintf( __( 'A credit card processing fee of %s%% will be added. Current total = $%s', 'p7-mp' ),
								'<span class="p7mp-procesing-fee">' . $cc_fee * 100 . '</span>',
								'<span class="p7mp-cc-total"></span>' )
							. '</p>';
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var $payMethodElement = $('[name="p7mp_p_payment_method"]');
							$payMethodElement.closest('ul').after('<?php echo $cc_fee_message; ?>');
							var $ccFeeMessage = $payMethodElement.closest('ul').next('p.cmb2-metabox-description');
							$payMethodElement.on('change', function(){
								if('offline' == $(this).val()){
									$ccFeeMessage.hide();
								}else{
									$ccFeeMessage.show();
								}
							});

							var $memberTypeElement = $('[name="p7mp_p_member_type"]');
							var amt = $memberTypeElement.find('option:selected').text().split(': $')[1].replace(/[^\d.-]/g, '');
							amt = amt * (1 + <?php echo $cc_fee; ?>);
							$('span.p7mp-cc-total').text(amt.toFixed(2));
							$memberTypeElement.on('change', function(){
								var amt = $(this).find('option:selected').text().split(': $')[1].replace(/[^\d.-]/g, '');
								amt = amt * (1 + <?php echo $cc_fee; ?>);
								$('span.p7mp-cc-total').text(amt.toFixed(2));
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		if ( $validation_errors = $cmb->prop( 'validation_errors' ) ) {
			ob_start();
			?>
				<script>
					(function($){
						'use strict';
						$(document).ready(function(){
							var MPVE = <?php echo json_encode( $validation_errors ); ?>;
							$.each(MPVE, function(key, data){
								$('form#<?php echo $this->pp; ?> #'+key).addClass('p7-mp-validation-error').after('<div class="p7-mp-validation-error-message alert alert-danger">'+data+'</div>');
							});
							$('form#<?php echo $this->pp; ?>').on('click', '.p7-mp-validation-error', function(){
								$(this).removeClass('p7-mp-validation-error');
							});
						});
					})(jQuery);
				</script>
			<?php
			$output .= ob_get_clean();
		}

		return $output;
	}

	/**
	 * Handles dues payment processing.
	 *
	 * @return void
	 */
	private function process_dues_payment_submission() {
		$nonce = $this->verify_nonce( $this->pp );
		if ( false === $nonce ) return false;

		$box = cmb2_get_metabox( $this->pp );

		if ( is_wp_error( $nonce ) ) return $box->prop( 'submission_error', $nonce );

		$redirect = ! empty( $this->get_option( 'page_payment_form' ) ) ? get_the_permalink( $this->get_option( 'page_payment_form' ) ) : home_url();
		
		$sanitized_values = $box->get_sanitized_values( $_POST );

		if ( 'offline' === $sanitized_values[ 'p7mp_p_payment_method' ] ) {
			$blog_name = htmlspecialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
			$to        = array();
			$contacts  = $this->get_option( 'admin_contacts' );
			if ( is_array( $contacts ) ) {
				foreach ( $contacts as $contact ) {
					if ( $contact[ 'type' ] === 'to' ) {
						$to[] = $contact[ 'email' ];
					} else {
						$headers[] = $contact[ 'type' ] . ': ' . $contact[ 'email' ];
					}
				}
			}

			$mail_fields = $sanitized_values;
			$extra_mail_fields = array();
			if ( ! isset( $mail_fields[ $this->pp . 'member_account' ] ) ) $extra_mail_fields[ $this->pp . 'member_account' ] = $this->user()->user_login;
			$mail_fields = $extra_mail_fields + $mail_fields;

			$subject = __( 'New Offline Dues Payment (Invoice) Request From: ', 'p7-mp' ) . $mail_fields[ $this->pp . 'member_account' ] . ' - ' . $blog_name;
			$headers = array_unique( $headers );
			$to      = array_unique( $to );
			if ( empty( $to ) ) $to = array( $blog_name . ' <' . get_bloginfo( 'admin_email' ) . '>' );

			ob_start();
			?>
				<dl>
					<?php
						foreach ( $mail_fields as $k => $v ) {
							$k = str_replace( $this->pp, '', $k );
							if ( ! $this->payment_form_fields[ $k ][ 'email' ] ) continue;

							if ( is_string( $this->payment_form_fields[ $k ][ 'email' ] ) ) {
								$v = $this->call_user_func( $this->payment_form_fields[ $k ][ 'email' ], $v );
							}

							?><dt><?php echo $this->payment_form_fields[ $k ][ 'name' ]; ?></dt><dd><?php echo $v; ?></dd><?php
						}

						foreach ( $this->user_meta_fields as $k => $v ) {
							if ( true === $v[ 'notification' ] ) {
								?><dt><?php echo $k; ?></dt><dd><?php echo $this->user_meta( $k ); ?></dd><?php
							}
						}
					?>
				</dl>
			<?php
			$message = ob_get_clean();

			if ( wp_mail( $to, $subject, $message, $headers ) ) {
				$redirect = add_query_arg( 'offline_payment_email_sent', 'true', $redirect );
			} else {
				$redirect = add_query_arg( 'offline_payment_email_sent', 'false', $redirect );
			}
			wp_redirect( esc_url_raw( $redirect ) );
			exit();
		}

		if ( $validation_errors = $this->validate( $this->payment_form_fields, $sanitized_values, $this->pp ) ) {
			$box->prop( 'submission_error', new WP_Error( 'validation_fail', __( 'Please correct the errors below.', 'p7-mp' ) ) );
			$box->prop( 'validation_errors', $validation_errors );
			return $box;
		}

		$payment_processor = '';
		foreach ( $this->options as $k => $v ) {
			if ( 0 === strpos( $k, 'payment_' ) && false !== strpos( $k, '_enabled' ) && 'yes' === $v ) {
				$payment_processor = str_replace( '_enabled', '', $k );
				break;
			}
		}

		if ( empty( $payment_processor ) ) return $box->prop( 'submission_error', new WP_Error( 'gateway_disabled', __( 'We cannot process payments online at this time. Please try again later or contact us.', 'p7-mp' ) ) );

		$gateway_init = array();
		foreach ( $this->options as $k => $v ) {
			if ( 0 === strpos( $k, $payment_processor ) && false === strpos( $k, '_enabled' ) ) {
				$k = end( explode( '_', $k ) );
				$gateway_init[ $k ] = 'testMode' === $k ? 1 : $v;
			}
		}

		$gateway = Omnipay::create( str_replace( 'payment_', '', $payment_processor ) );
		$gateway->initialize( $gateway_init );

		$term = get_term_by( 'slug', $sanitized_values[ 'p7mp_p_member_type' ], 'p7mp_membership_level' );
		$payment[ 'amount'   ] = preg_replace( '/[^0-9.]/', '', get_term_meta( $term->term_id, 'dues_amount', true ) );
		$payment[ 'currency' ] = 'USD';
		$payment[ 'card'     ] = $this->get_matching_fields( 'cc_field', $this->payment_form_fields, $sanitized_values, $this->pp );

		$cc_fee = preg_replace( '/[^0-9.]/', '', $this->get_option( 'cc_fee' ) ) / 100;
		if ( 0 < $cc_fee && ! empty( $cc_fee ) ) {
			$payment[ 'amount' ] = $payment[ 'amount' ] * ( 1 + $cc_fee );
			$payment[ 'amount' ] = number_format( $payment[ 'amount' ], '2', '.', '' );
		}

		try {
			$response = $gateway->purchase( $payment )->send();

			if ( $response->isSuccessful() /*|| '4111111111111111' === $payment[ 'card' ][ 'number' ]*/ ) {
				$payment_id = wp_insert_post( array(
					'post_type'   => 'p7mp_dues_payment',
					'post_author' => 0,
				), true );
				wp_update_post( get_post( $payment_id ) );

				$post_meta = $this->get_matching_fields( 'dues_field', $this->post_types_fields[ 'dues_payment' ][ 'fields' ], $sanitized_values, $this->pp );
				$post_meta[ 'amount' ] = $payment[ 'amount' ];
				if ( ! isset( $post_meta[ 'member_account' ] ) ) $post_meta[ 'member_account' ] = $this->user()->user_login;
				foreach ( $post_meta as $k => $v ) {
					update_post_meta( $payment_id, '_' . $k, $v );
				}

				$mail_fields = $sanitized_values;
				$extra_mail_fields = array();
				if ( ! isset( $mail_fields[ $this->pp . 'member_account' ] ) ) $extra_mail_fields[ $this->pp . 'member_account' ] = $this->user()->user_login;
				$mail_fields = $extra_mail_fields + $mail_fields;
				$user = get_user_by( 'login', $mail_fields[ $this->pp . 'member_account' ] );

				wp_set_object_terms( $user->ID, $mail_fields[ $this->pp . 'member_type' ], 'p7mp_membership_level' );
				update_user_meta( $user->ID, $this->pu . 'member_type', $mail_fields[ $this->pp . 'member_type' ] );
				update_user_meta( $user->ID, $this->pu . 'member_status', 'active' );
				$usr_meta = $this->flatten_array( get_user_meta( $user->ID ) );
				if ( strtotime( $usr_meta[ $this->pu . 'member_expiry' ] ) > strtotime( date( 'Y-m-d' ) ) ) {
					update_user_meta( $user->ID, $this->pu . 'member_expiry', date( 'Y-m-d', strtotime( $usr_meta[ $this->pu . 'member_expiry' ] . ' + 1 Year' ) ) );
				} else {
					update_user_meta( $user->ID, $this->pu . 'member_expiry', date( 'Y-m-d', strtotime( date( 'Y-m-d' ) . ' + 1 Year' ) ) );
				}

				$blog_name = htmlspecialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
				$headers   = array( 'Content-Type: text/html; charset=UTF-8' );
				$to        = array();
				$contacts  = $this->get_option( 'admin_contacts' );
				if ( is_array( $contacts ) ) {
					foreach ( $contacts as $contact ) {
						if ( $contact[ 'type' ] === 'to' ) {
							$to[] = $contact[ 'email' ];
						} else {
							$headers[] = $contact[ 'type' ] . ': ' . $contact[ 'email' ];
						}
					}
				}

				$subject = __( 'New Dues Payment From: ', 'p7-mp' ) . $mail_fields[ $this->pp . 'member_account' ] . ' - ' . $blog_name;
				$headers = array_unique( $headers );
				$to      = array_unique( $to );
				if ( empty( $to ) ) $to = array( $blog_name . ' <' . get_bloginfo( 'admin_email' ) . '>' );

				ob_start();
				?>
					<dl>
						<dt>Receipt ID</dt><dd><?php echo get_post( $payment_id )->post_title; ?></dd>
						<dt>Payment Amount</dt><dd>$<?php echo $payment[ 'amount' ]; ?></dd>
						<?php
							foreach ( $mail_fields as $k => $v ) {
								$k = str_replace( $this->pp, '', $k );
								if ( ! $this->payment_form_fields[ $k ][ 'email' ] ) continue;

								if ( is_string( $this->payment_form_fields[ $k ][ 'email' ] ) ) {
									$v = $this->call_user_func( $this->payment_form_fields[ $k ][ 'email' ], $v );
								}

								?><dt><?php echo $this->payment_form_fields[ $k ][ 'name' ]; ?></dt><dd><?php echo $v; ?></dd><?php
							}
						?>
					</dl>
				<?php
				$message = ob_get_clean();

				// admin email
				wp_mail( $to, $subject, $message, $headers );

				// user email
				$headers = array( 'Content-Type: text/html; charset=UTF-8' );
				if ( isset( $mail_fields[ $this->pp . 'email' ] ) ) $headers[] = 'CC: ' . $mail_fields[ $this->pp . 'email' ];
				$to      = $user->user_email;
				$subject = __( 'Receipt for your Dues Payment to: ', 'p7-mp' ) . $blog_name;
				$sent    = false;
				if ( wp_mail( $to, $subject, $message, $headers ) ) $sent = true;

				if ( ! is_wp_error( $payment_id ) ) {
					$redirect = add_query_arg( 'payment_received', 'true', $redirect );
				}
				if ( $sent ) {
					$redirect = add_query_arg( 'email_sent', 'true', $redirect );
				} else {
					$redirect = add_query_arg( 'email_sent', 'false', $redirect );
				}
				wp_redirect( esc_url_raw( $redirect ) );
				exit();
			} elseif ( $response->isRedirect() ) {
				// TODO: find out how this works.
				$response->redirect();
			} else {
				// Payment failed
				return $box->prop( 'submission_error', new WP_Error( 'payment_failed', $response->getMessage() ) );
			}
		} catch ( \Exception $e ) {
			return $box->prop( 'submission_error', new WP_Error( 'payment_exception', $e->getMessage() ) );
		}
	}

	/**
	 * Generates a member directory.
	 *
	 * @return str $output
	 */
	public function shortcode_member_directory( $atts = array() ) {
		$show = $this->get_option( 'profiles_public' );
		if ( 'none' === $show && ! $this->is_member() ) return __( 'Please log in or register. Only members can see info about members.', 'p7-mp' );

		wp_enqueue_script( 'p7-mp-list', $this->plugin_dir_url . 'js/list.min.js', array(), '1.5.0', true );
		wp_add_inline_script( 'p7-mp-list', apply_filters( 'p7mp_member_directory_list_js_config', 'var options = {valueNames: ["entry-title","p7-mp-member-email"]};var userList = new List("p7mp-members", options);' ) );

		$output = apply_filters( 'p7mp_member_directory', $output );

		if ( empty( $output ) ) {
			$output .= '<div id="p7mp-members">';
				$output .= '<form id="p7mp-directory-search" class="pull-left"><input class="search" placeholder="Search" type="search" name="p7mp_directory_search" /></form>';
				$output .= '<button class="sort button-primary" data-sort="entry-title">Sort by name</button>';
				$output .= '<div class="list">';

					$show = $this->is_member() ? 'all' : $show;

					$users = get_users( array( 'role' => 'p7mp' ) );
					foreach ( $users as $user ) {
						$meta = $this->flatten_array( get_user_meta( $user->ID ) );
						if ( 'active' !== $meta[ 'p7mp_u_member_status' ] ) continue;

						$output .= '<article class="post p7mp-member">';

							$output .= '<header class="entry-header"><h2 class="entry-title"><a href="' . get_permalink( p7mp_get_member_post( $user->ID ) ) . '" rel="bookmark">' . $user->display_name . '</a></h2></header>';

							$output .= '<div class="entry-content">';

								if ( 'premium-members' === $meta[ 'p7mp_u_member_type' ] ) {
									$output .= '<div class="p7-mp-highlight">' . $this->svg( 'star' ) . '</div>';
								}
								if ( ! empty( $meta[ 'p7mp_u_member_email' ] ) ) {
									$output .= '<a class="p7-mp-member-email" href="mailto:' . $meta[ 'p7mp_u_member_email' ] . '">' . $this->svg( 'email' ) . ' ' . $meta[ 'p7mp_u_member_email' ] . '</a>';
								}
								if ( ! empty( $meta[ 'p7mp_u_member_description' ] ) ) {
									$output .= '<div class="p7-mp-member-description">' . wpautop( $meta[ 'p7mp_u_member_description' ] ) . '</div>';
								}

							$output .= '</div><!-- /.entry-content -->';

						$output .= '</article><!-- /.p7mp-member -->';
					}

				$output .= '</div><!-- /.list -->';
			$output .= '</div><!-- /#p7mp-members -->';

		}

		return $output;
	}

	/**
	 * Generates a list of all terms in the given taxonomy.
	 *
	 * @return str $output
	 */
	public function shortcode_member_list_terms( $atts = array() ) {
		$atts = shortcode_atts( array(
			'taxonomy' => '',
			'cols'     => 1,
		), $atts, 'member_list_terms' );

		$terms = get_terms( array(
			'taxonomy'   => 'p7mp_' . $atts[ 'taxonomy' ],
			'hide_empty' => false,
		) );

		if ( is_wp_error( $terms ) ) return 'The "taxonomy" parameter is required. It should be the slug of a registered "p7mp_user_taxonomy".';

		$dir_url = get_permalink( $this->get_option( 'page_member_directory' ) );

		if ( 1 < $atts[ 'cols' ] ) {
			$class = ' multicol';
			$style = ' style="column-count: ' . $atts[ 'cols' ] . ';"';
		}

		$output .= '<ul class="p7-mp-terms' . $class . '"' . $style . '>';

			foreach ( $terms as $key => $term ) {
				$term_url = add_query_arg( $atts[ 'taxonomy' ], $term->slug, $dir_url );
				$output .= '<li><a href="' . $term_url . '">' . $term->name . '</a></li>';
			}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Generates a member map.
	 *
	 * @param array $atts
	 *
	 * @return str $output
	 */
	public function shortcode_member_map( $atts = array() ) {
		$show = $this->get_option( 'profiles_public' );
		if ( 'none' === $show && ! $this->is_member() ) return __( 'Please log in or register. Only members can see info about other members.', 'p7-mp' );

		$args = apply_filters( 'p7mp_member_map_user_query', array(
			'role'       => 'p7mp',
			'meta_query' => array(
				array(
					'key'     => $this->pu . 'member_status',
					'value'   => 'active',
					'compare' => '=',
				),
			),
		) );
		$users = get_users( $args );

		if ( empty( $users ) ) return __( 'No active members found.', 'p7-mp' );

		$can_geocode = ! empty( $this->get_option( 'google_maps_api_key' ) );

		$locations = array();
		foreach ( $users as $user ) {
			$meta       = $this->flatten_array( get_user_meta( $user->ID ) );
			$map_fields = $this->get_matching_fields( 'map_field', $this->user_meta_fields, $meta, $this->pu );
			if ( empty( $meta[ $this->pu . 'lat' ] ) || empty( $meta[ $this->pu . 'lng' ] ) ) {
				if ( ! empty( $map_fields[ 'address' ] ) && $can_geocode ) {
					$meta = $this->geocode( $user->ID, $meta, $map_fields );
				}
			}

			if ( empty( $meta[ $this->pu . 'lat' ] ) || empty( $meta[ $this->pu . 'lng' ] ) ) continue;

			$locations[] = array(
				'<h3>' . $map_fields[ 'name' ] . '</h3><address>' . $map_fields[ 'address' ] . ' <br />' . $map_fields[ 'city' ] . ', ' . $map_fields[ 'state' ] . '  ' . $map_fields[ 'zip' ] . '</address>',
				$meta[ $this->pu . 'lat' ],
				$meta[ $this->pu . 'lng' ],
			);
		}

		if ( empty( $locations ) ) return __( 'No valid map locations found.', 'p7-mp' );

		$lat_start  = ! empty( $this->get_option( 'google_maps_center_lat'   ) ) ? $this->get_option( 'google_maps_center_lat'   ) : '37.09024';
		$lng_start  = ! empty( $this->get_option( 'google_maps_center_lng'   ) ) ? $this->get_option( 'google_maps_center_lng'   ) : '-95.712891';
		$zoom_start = ! empty( $this->get_option( 'google_maps_default_zoom' ) ) ? $this->get_option( 'google_maps_default_zoom' ) : '4';
		$map_style  = $this->map_style( $this->get_option( 'google_maps_style' ) );

		ob_start();
		?>
			<div id="p7mp-member-map" style="width: 100%; height: 600px; margin-bottom: 1em;"></div>
			<script>
			function initMap() {
				var map = new google.maps.Map(document.getElementById('p7mp-member-map'), {
					zoom: <?php echo $zoom_start; ?>,
					center: new google.maps.LatLng(<?php echo $lat_start; ?>,<?php echo $lng_start; ?>),
					mapTypeId: google.maps.MapTypeId.ROADMAP,
					styles: [<?php echo $map_style; ?>]
				});
				
				var locations = <?php echo json_encode( $locations ); ?>;
				var infowindow = new google.maps.InfoWindow();
				var marker, i;

				for(i = 0; i < locations.length; i++){
					marker = new google.maps.Marker({
						position: new google.maps.LatLng(locations[i][1], locations[i][2]),
						map: map,
					});

					google.maps.event.addListener(marker, 'mouseover', (function(marker, i){
						return function(){
							infowindow.setContent(locations[i][0]);
							infowindow.open(map, marker);
						}
					})(marker, i));
				}
			}
			</script>
			<script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo $this->get_option( 'google_maps_api_key' ); ?>&callback=initMap"></script>
		<?php

		return ob_get_clean();
	}

	/**
	 * Generates a member directory.
	 *
	 * @param str $content
	 *
	 * @return str $content
	 */
	public function the_content( $content ) {
		global $post;
		if ( 'p7mp_member' !== $post->post_type ) return $content;

		$show = $this->get_option( 'profiles_public' );
		if ( 'none' === $show && ! $this->is_member() ) return __( 'Please log in or register. Only members can see info about members.', 'p7-mp' );

		$content = '';
		$content = apply_filters( 'p7mp_member_content', $content );

		if ( empty( $content ) ) {
			$show = $this->is_member() ? 'all' : $show;
			$meta = $this->flatten_array( get_user_meta( $post->post_author ) );
			
			if ( 'premium-members' === $meta[ 'p7mp_u_member_type' ] ) {
				$content .= '<div class="p7-mp-highlight">' . $this->svg( 'star' ) . '</div>';
			}
			if ( ! empty( $meta[ 'p7mp_u_member_email' ] ) ) {
				$content .= '<a class="p7-mp-member-email" href="mailto:' . $meta[ 'p7mp_u_member_email' ] . '">' . $this->svg( 'email' ) . ' ' . $meta[ 'p7mp_u_member_email' ] . '</a>';
			}
			if ( ! empty( $meta[ 'p7mp_u_member_description' ] ) ) {
				$content .= '<div class="p7-mp-member-description">' . wpautop( $meta[ 'p7mp_u_member_description' ] ) . '</div>';
			}
		}

		return $content;
	}

	/**
	 * Displays a "content restricted" message if content is restricted and active member is not logged in.
	 *
	 * @param str $content
	 *
	 * @return str $content
	 */
	public function hide_content( $content ) {
		if ( is_singular() && is_main_query() ) {

			$access_level = (array) get_post_meta( get_the_ID(), '_p7mp_members_only_level', true );
			$member_types = p7mp_get_member_types_select( false, true );

			$keys   = array_keys( $member_types );
			$keys[] = 'p7mp_everyone';
			$keys[] = 'p7mp_all_members';
			foreach ( $access_level as $k => $v ) {
				if ( ! in_array( $v, $keys ) ) unset( $access_level[ $k ] );
			}

			if ( empty( $access_level ) || in_array( 'p7mp_everyone', $access_level ) || current_user_can( 'administrator' ) ) return $content;

			if ( ! $this->is_member() ) {
				$login_box = 'no' !== $this->get_option( 'include_login_when_restricted' ) ? do_shortcode( '[member_login_box]' ) : '';
				if ( empty( $_GET[ 'category' ] ) ) { // KLUDGE FOR FWEDA
					return '<div class="p7-mp-submission-error-message"><p>' . __( 'This content is restricted. Only members may view it. Please log in or register.', 'p7-mp' ) . '</p></div>' . $login_box;
				}
			}

			global $post;
			if ( $post->ID == $this->get_option( 'page_payment_form' ) ) return $content;

			if ( 'active' !== $this->user_meta( 'member_status' ) && empty( $_GET[ 'category' ] )/*KLUDGE FOR FWEDA*/ ) return '<div class="p7-mp-submission-error-message"><p>' . __( 'Your membership is not active. Please activate or renew your membership.', 'p7-mp' ) . '</p></div>';

			if ( ! in_array( 'p7mp_all_members', $access_level ) && ! in_array( $this->user_meta( 'member_type' ), $access_level ) ) {
				$content = '<div class="p7-mp-submission-error-message"><p>' . __( 'This content is restricted. Only the following member types may view it:', 'p7-mp' ) . '</p><ul>';
				foreach ( $access_level as $level ) {
					$content .= '<li>' . $member_types[ $level ] . '</li>';
				}
				$content .= '</ul><p>' . __( 'Please upgrade your membership.', 'p7-mp' ) . '</p></div>';
				return $content;
			}

			$content = apply_filters( 'p7mp_hide_content_after_member_level_check', $content );
			
		}

		return $content;
	}

	/**
	 * Changes status of expired members.
	 *
	 * @return void
	 */
	public function disable_expired_accounts() {
		$one_day  = 60 * 60 * 24;
		$one_week = $one_day * 7;

		$users = get_users( array(
			'role'       => 'p7mp',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'     => 'p7mp_u_member_expiry',
					'value'   => date( 'Y-m-d', time() - $one_week ),
					'compare' => '>=',
				),
				array(
					'key'     => 'p7mp_u_member_expiry',
					'value'   => date( 'Y-m-d', time() + $one_week ),
					'compare' => '<=',
				),
			),
		) );

		foreach ( $users as $user ) {
			$expiry = get_user_meta( $user->ID, 'p7mp_u_member_expiry', true );
			$diff   = ( strtotime( date( 'Y-m-d' ) ) - strtotime( $expiry ) ) / $one_day;

			if ( $expiry <= date( 'Y-m-d' ) ) {
				update_user_meta( $user->ID, 'p7mp_u_member_status', 'inactive' );
			}

			$link    = get_bloginfo( 'url' );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$to      = $user->user_email;
			$subject = 'Your account is expiring.';
			$message = '<p>Click the link below to update your membership.</p><a href="' . $link . '">' . $link . '</a>';
			//wp_mail( $to, $subject, $message, $headers );
		}
	}

	/**
	 * Creates a csv string from an array.
	 *
	 * @return void
	 */
	private function export_members() {
		if ( empty( $_POST[ 'p7mp_export_members' ] ) || ! current_user_can( 'manage_options' ) ) return;

		$fields = array_filter( $this->user_meta_fields, function( $item ) {
			if ( ! empty( $item[ 'parent' ] ) && 'group' === $this->user_meta_fields[ $item[ 'parent' ] ][ 'type' ] ) {
				return false;
			}

			return $item[ 'export' ] ? true : false;
		} );

		$args = array( 'role' => 'p7mp' );

		if ( ! empty( $_GET ) ) {
			$user_fields = $this->user_meta_fields;
			foreach ( $_GET as $k => $v ) {
				if ( false !== strpos( $k, $this->pu ) && ! empty( $v ) && $user_fields[ str_replace( $this->pu, '', $k ) ][ 'admin_filter' ] ) {
					$args[ 'meta_query' ][] = array(
						'key'     => $k,
						'value'   => $v,
						'compare' => 'LIKE',
					);
				}
			}
		}

		$users = get_users( $args );
		if ( empty( $users ) ) return;

		// count repeatables
		$groups = array();
		foreach ( $users as $key => $value ) {
			$user = $this->flatten_array( get_user_meta( $value->ID ) );
			foreach ( $fields as $id => $field ) {
				if ( 'group' === $field[ 'type' ] ) {
					$group = (array) maybe_unserialize( $user[ $this->pu . $id ] );
					$n = count( $group );
					$groups[ $id ] = $groups[ $id ] >= $n ? $groups[ $id ] : $n;
					if ( $n ) $user[ $this->pu . $id ] = $group;
				}
			}

			$users[ $key ] = $user;
		}

		// get subfields of groups
		foreach ( $groups as $k => $v ) {
			$groups[ $k ] = array( 'count' => $v );
			foreach ( $this->user_meta_fields as $id => $field ) {
				if ( $k === $field[ 'parent' ] ) {
					$groups[ $k ][ 'fields' ][] = $id;
				}
			}
		}

		$r = array();
		foreach ( $users as $key => $user ) {
			foreach ( $fields as $id => $field ) {
				if ( 'group' === $field[ 'type' ] ) {
					for ( $i = 0; $i < $groups[ $id ][ 'count' ]; $i++ ) {
						foreach ( $groups[ $id ][ 'fields' ] as $field ) {
							$r[ $key ][ $field . '_' . $i ] = $user[ $this->pu . $id ][ $i ][ $field ];
						}
					}
					continue;
				}

				if ( false !== strpos( $field[ 'type' ], 'multi' ) ) {
					$user[ $this->pu . $id ] = implode( ',', (array) maybe_unserialize( $user[ $this->pu . $id ] ) );
				}

				$r[ $key ][ $id ] = $user[ $this->pu . $id ];
			}
		}

		$csv = Writer::createFromString( '' );
		$csv->insertOne( array_keys( $r[ 0 ] ) );
		$csv->insertAll( $r );
		$csv->output( sanitize_key( get_bloginfo( 'name' ) ) . '_members_' . date( 'Y-m-d' ) . '.csv' );
		exit();
	}

	/**
	 * Inserts/updates members from uploaded .csv file.
	 *
	 * @return void
	 */
	private function import_members() {
		if ( 'p7mp_import_members' !== $_POST[ 'object_id' ] || empty( $_POST[ 'upload_file_id' ] ) ) return;

		$csv     = Reader::createFromPath( get_attached_file( $_POST[ 'upload_file_id' ] ), 'r' );
		$headers = $csv->fetchOne();
		$members = $csv->setOffset( 1 )->fetchAssoc( $headers );

		foreach ( $headers as $k => $v ) {
			$headers[ $k ] = trim( $v );
		}

		wp_delete_attachment( $_POST[ 'upload_file_id' ] );

		$fields = array_filter( $this->user_meta_fields, function( $item ) {
			return $item[ 'import' ] ? true : false;
		} );

		$user_fields = $this->get_matching_fields( 'wp_user_field', $fields, array_combine( $headers, $headers ) );

		$n = 0;
		foreach ( $members as $member ) {
			$n++;
			$member = array_map( 'trim', $member );

			$userdata = array(
				'user_login'    => $member[ $user_fields[ 'user_login' ] ],
				'user_email'    => strtolower( $member[ $user_fields[ 'user_email' ] ] ),
				'user_nicename' => sanitize_html_class( $member[ $user_fields[ 'user_nicename' ] ] ),
				'display_name'  => $member[ $user_fields[ 'display_name' ] ],
				'user_pass'     => $member[ $user_fields[ 'user_pass' ] ],
				'user_url'      => strtolower( $member[ $user_fields[ 'user_url' ] ] ),
				'description'   => $member[ $user_fields[ 'description' ] ],
			);

			$user = get_user_by( 'login', $member[ $user_fields[ 'user_login' ] ] );

			if ( ! $user ) {
				$userdata[ 'user_pass' ] = ! empty( $member[ $user_fields[ 'user_pass' ] ] ) ? $member[ $user_fields[ 'user_pass' ] ] : wp_generate_password( rand( 12, 15 ) );
				$user_id = wp_insert_user( $userdata );
			} else {
				$userdata[ 'ID' ] = $user->ID;
				if ( ! empty( $member[ $user_fields[ 'user_pass' ] ] ) ) {
					$userdata[ 'user_pass' ] = wp_hash_password( $member[ $user_fields[ 'user_pass' ] ] );
				} else {
					unset( $userdata[ 'user_pass' ] );
				}
				$user_id = wp_insert_user( $userdata );
			}
			unset( $member[ $user_fields[ 'user_pass' ] ] );

			if ( is_wp_error( $user_id ) ) {
				$message = $user_id->get_error_message();
				$this->notices->add_error( sprintf( __( 'Error on row with login: "%s". %s Importing aborted. This row and subsequent rows were not processed.', 'p7-mp' ), $member[ $user_fields[ 'user_login' ] ], '<strong>' . $message . '</strong>' ) );
				wp_redirect( esc_url_raw( add_query_arg( 'members_import_successful', 'partial' ) ) );
				exit();
			}

			$role = 'p7mp';
			$u = new WP_User( $user_id );
			$u->add_role( $role );
			$u->remove_role( 'subscriber' );

			update_user_option( $user_id, 'show_admin_bar_front', 'false' );

			foreach ( $headers as $k => $v ) {
				$id = 'p7mp_u_' . $v;
				$val = $member[ $v ];

				if ( false !== strpos( $fields[ $v ][ 'type' ], 'multi' ) ) {
					$val = explode( ',', $val );
				}

				if ( 'text_date' === $fields[ $v ][ 'type' ] ) {
					$val = date( 'Y-m-d', strtotime( $val ) );
				}

				if ( ! empty( $fields[ $v ][ 'wp_user_tax' ] ) ) {
					$_POST[ 'p7mp_' . $fields[ $v ][ 'wp_user_tax' ] ] = $val;
				}
				update_user_meta( $user_id, $id, $val );
			}

			P7_User_Taxonomies::save_profile( $user_id );
			p7mp_add_member_post( $user_id );
		}

		return;
	}

	/**
	 * Prints output.
	 * For use on field of type: 'title'.
	 *
	 * @param bool $field_args
	 * @param bool $field
	 *
	 * @return void
	 */
	public function usage_instructions( $field_args, $field ) {
		?>
			<div class="p7-mp-instructions">
				<h3><?php _e( 'Shortcodes:', 'p7-mp' ); ?></h3>
				<dl>
					<dt>[member_profile]</dt>
					<dd>
						<?php _e( 'Renders a form for non-members to register. Members use this form to update their profile.', 'p7-mp' ); ?>
					</dd>
					<dt>[member_login_box hide="true" link="Text"]</dt>
					<dd>
						<?php _e( 'Renders a login box. Parameters <code>hide</code> and <code>link</code> are optional.', 'p7-mp' ); ?>
					</dd>
					<dt>[member_payment_form]</dt>
					<dd>
						<?php _e( 'Renders a form for users to activate or renew their membership.', 'p7-mp' ); ?>
					</dd>
					<dt>[member_directory]</dt>
					<dd>
						<?php _e( 'Renders a list of members.', 'p7-mp' ); ?>
					</dd>
					<dt>[member_contact_form member="username"]</dt>
					<dd>
						<?php _e( 'Renders a contact form that gets submitted to a member. Parameter <code>member</code> is required.', 'p7-mp' ); ?>
					</dd>
					<dt>[member_map]</dt>
					<dd>
						<?php _e( 'Renders a google map with a pin for each member\'s address.', 'p7-mp' ); ?>
					</dd>
				</dl>
				<p><?php _e( 'Note: After adding shortcodes to your pages, remember to come back to the settings and tell me which pages your shortcodes are on.', 'p7-mp' ); ?></p>
			</div>
		<?php
	}

	/**
	 * Writes to error_log.
	 *
	 * @param mixed  $log
	 * @param string $id
	 *
	 * @return void
	 */
	public static function write_log( $log, $id = '' ) {
		error_log( '************* ' . $id . ' *************' );
		if ( is_array( $log ) || is_object( $log ) ) {
			error_log( print_r( $log, true ) );
		} else {
			error_log( $log );
		}
	}

	/**
	 * Returns style data for google map.
	 *
	 * @param str $id
	 *
	 * @return str $output
	 */
	private function map_style( $id ) {
		switch ( $id ) {
			case 'grayscale':
				$style = '{
					"stylers": [{
						"visibility": "on"
					}, {
						"saturation": -100
					}, {
						"gamma": 0.54
					}]
				}, {
					"featureType": "road",
					"elementType": "labels.icon",
					"stylers": [{
						"visibility": "off"
					}]
				}, {
					"featureType": "water",
					"stylers": [{
						"color": "#4d4946"
					}]
				}, {
					"featureType": "poi",
					"elementType": "labels.icon",
					"stylers": [{
						"visibility": "off"
					}]
				}, {
					"featureType": "poi",
					"elementType": "labels.text",
					"stylers": [{
						"visibility": "simplified"
					}]
				}, {
					"featureType": "road",
					"elementType": "geometry.fill",
					"stylers": [{
						"color": "#ffffff"
					}]
				}, {
					"featureType": "road.local",
					"elementType": "labels.text",
					"stylers": [{
						"visibility": "simplified"
					}]
				}, {
					"featureType": "water",
					"elementType": "labels.text.fill",
					"stylers": [{
						"color": "#ffffff"
					}]
				}, {
					"featureType": "transit.line",
					"elementType": "geometry",
					"stylers": [{
						"gamma": 0.48
					}]
				}, {
					"featureType": "transit.station",
					"elementType": "labels.icon",
					"stylers": [{
						"visibility": "off"
					}]
				}, {
					"featureType": "road",
					"elementType": "geometry.stroke",
					"stylers": [{
						"gamma": 7.18
					}]
				}';
				return $style;
			
			default:
				return '';
		}
		
		return '';
	}

}

?>
