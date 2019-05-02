<?php
/**
 * Class Name: P7_Notices
 */

if ( ! defined( 'WPINC' ) ) die();

if ( ! class_exists( 'P7_Notices' ) ) {

class P7_Notices {
	public $errors   = array();
	public $warnings = array();
	public $success  = array();
	public $prefix   = array();

	/**
	 * Constructs the object.
	 * 
	 * @param str $prefix
	 * @return void
	 */
	public function __construct( $prefix ) {
		$this->prefix = $prefix;
		add_action( 'admin_notices', array( $this, 'output_notices' ) );
		add_action( 'shutdown',      array( $this, 'save_notices'   ) );
	}

	/**
	 * Adds an error.
	 * 
	 * @param str $str
	 * @return void
	 */
	public function add_error( $str ) {
		$this->errors[] = $str;
	}

	/**
	 * Adds a warning.
	 * 
	 * @param str $str
	 * @return void
	 */
	public function add_warning( $str ) {
		$this->warnings[] = $str;
	}

	/**
	 * Adds a success message.
	 * 
	 * @param str $str
	 * @return void
	 */
	public function add_success( $str ) {
		$this->success[] = $str;
	}

	/**
	 * Saves notices to an option.
	 * 
	 * @return void
	 */
	public function save_notices() {
		$notices = array(
			'error'   => $this->errors,
			'warning' => $this->warnings,
			'updated' => $this->success,
		);

		update_option( $this->prefix . '_p7_notices', $notices );
	}

	/**
	 * Shows any stored error messages.
	 * 
	 * @return void
	 */
	public function output_notices() {
		$notices = maybe_unserialize( get_option( $this->prefix . '_p7_notices' ) );

		foreach ( $notices as $k => $v ) {
			if ( ! empty( $v ) ) {
				
				foreach ( $v as $notice ) {
					echo '<div class="' . $k . ' notice is-dismissible">';
						echo '<p>' . wp_kses_post( $notice ) . '</p>';
					echo '</div>';
				}
				
			}
		}

		delete_option( $this->prefix . '_p7_notices' );
	}

}

}

?>
