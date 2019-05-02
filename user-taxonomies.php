<?php
/**
 * Class Name:  P7_User_Taxonomies
 * Class URI:   http://gostomski.co.uk/code/wordpress-user-taxonomies
 * Author:      Damian Gostomski
 * Author URI:  http://gostomski.co.uk
 * Description: Simplify the process of adding support for custom taxonomies for Users. Just use 'register_taxonomy' and everything else is taken care of.
 * Version:     1.0
 * License:     GPLv2
 * 
 * WordPress originally introducted support for taxonomies back in WordPress 2.3
 * Although it's currently possible to create user taxonomies using the Taxonomy API
 * You'd need to write additional code to fill in the gaps
 * This plugin handles all the boring boilerplate code, so you can just get on with making awesome stuff
 * 
 * This is heavily inspired by previous work by Justin Tadlock
 * http://justintadlock.com/archives/2011/10/20/custom-user-taxonomies-in-wordpress
 */

if ( ! defined( 'WPINC' ) ) die();

if ( ! class_exists( 'P7_User_Taxonomies' ) ) {

class P7_User_Taxonomies {
	private static $taxonomies = array();

	/**
	 * Register all the hooks and filters we can in advance
	 * Some will need to be registered later on, as they require knowledge of the taxonomy name
	 */
	public function __construct() {
		// Taxonomies
		add_action( 'registered_taxonomy', array( $this, 'registered_taxonomy' ), 10, 3 );

		// Menus
		add_action( 'admin_menu',  array( $this, 'admin_menu'  ), 10    );
		add_filter( 'parent_file', array( $this, 'parent_menu' ), 10, 1 );

		// User Profiles
		//add_action( 'show_user_profile', array( $this, 'user_profile'      ), 10, 1 );
		//add_action( 'edit_user_profile', array( $this, 'user_profile'      ), 10, 1 );
		add_action( 'user_register',     array( $this, 'save_profile'      ), 10, 1 );
		add_action( 'profile_update',    array( $this, 'save_profile'      ), 10, 1 );
		add_filter( 'sanitize_user',     array( $this, 'restrict_username' ), 10, 1 );
	}
	
	/**
	 * This is our way into manipulating registered taxonomies
	 * It's fired at the end of the register_taxonomy function
	 * 
	 * @param str   $taxonomy - The name of the taxonomy being registered
	 * @param str   $object   - The object type the taxonomy is for; We only care if this is "user"
	 * @param array $args     - The user supplied + default arguments for registering the taxonomy
	 */
	public function registered_taxonomy( $taxonomy, $object, $args ) {
		global $wp_taxonomies;
		
		// Only modify user taxonomies, everything else can stay as is
		if ( $object !== 'user' ) return;
		
		// We're given an array, but expected to work with an object later on
		$args = (object) $args;
		
		// Register any hooks/filters that rely on knowing the taxonomy now
		add_filter( "manage_edit-{$taxonomy}_columns",  array( $this, 'set_user_column'       ), 10, 1 );
		add_action( "manage_{$taxonomy}_custom_column", array( $this, 'set_user_column_values'), 10, 3 );
		
		// Set the callback to update the count if not already set
		if ( empty( $args->update_count_callback ) ) {
			$args->update_count_callback = array( $this, 'update_count' );
		}
		
		// We're finished, make sure we save out changes
		$wp_taxonomies[ $taxonomy ]    = $args;
		self::$taxonomies[ $taxonomy ] = $args;
	}
	
	/**
	 * We need to manually update the number of users for a taxonomy term
	 * 
	 * @see	_update_post_term_count()
	 * @param array $terms    - List of Term taxonomy IDs
	 * @param obj   $taxonomy - Current taxonomy object of terms
	 */
	public function update_count( $terms, $taxonomy ) {
		global $wpdb;
		
		foreach ( (array) $terms as $term ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $term ) );

			do_action( 'edit_term_taxonomy', $term, $taxonomy );
			$wpdb->update( $wpdb->term_taxonomy, compact( 'count' ), array( 'term_taxonomy_id' => $term ) );
			do_action( 'edited_term_taxonomy', $term, $taxonomy );
		}
	}
	
	/**
	 * Add each of the taxonomies to the Users menu
	 * They will behave in the same was as post taxonomies under the Posts menu item
	 * Taxonomies will appear in alphabetical order
	 */
	public function admin_menu() {
		// Put the taxonomies in alphabetical order
		$taxonomies = self::$taxonomies;
		ksort( $taxonomies );

		foreach( $taxonomies as $key => $taxonomy ) {
			add_users_page(
				$taxonomy->labels->menu_name, 
				$taxonomy->labels->menu_name, 
				$taxonomy->cap->manage_terms, 
				"edit-tags.php?taxonomy={$key}"
			);
		}
	}
	
	/**
	 * Fix a bug with highlighting the parent menu item
	 * By default, when on the edit taxonomy page for a user taxonomy, the Posts tab is highlighted
	 * This will correct that bug
	 */
	function parent_menu( $parent = '' ) {
		global $pagenow;

		if ( 'edit-tags.php' !== $pagenow && 'term.php' !== $pagenow ) return $parent;

		if( ! empty( $_GET[ 'taxonomy' ] ) && isset( self::$taxonomies[ $_GET[ 'taxonomy' ] ] ) ) $parent = 'users.php';

		return $parent;
	}

	/**
	 * Correct the column names for user taxonomies
	 * Need to replace "Posts" with "Users"
	 */
	public function set_user_column( $columns ) {
		unset( $columns[ 'posts' ] );
		$columns[ 'users' ] = __( 'Users' );
		return $columns;
	}

	/**
	 * Set values for custom columns in user taxonomies
	 */
	public function set_user_column_values( $display, $column, $term_id ) {
		if ( 'users' === $column ) {
			$term = get_term( $term_id, $_GET[ 'taxonomy' ] );
			echo $term->count;
		}
	}

	/**
	 * Add the taxonomies to the user view/edit screen
	 * 
	 * @param obj $user - The user of the view/edit screen
	 */
	public function user_profile( $user ) {
		// Using output buffering as we need to make sure we have something before outputting the header
		// But we can't rely on the number of taxonomies, as capabilities may vary
		ob_start();

		foreach ( self::$taxonomies as $key => $taxonomy ) :
			// Check the current user can assign terms for this taxonomy
			if ( ! current_user_can( $taxonomy->cap->assign_terms ) ) continue;

			$type  = 'checkbox';
			$name  = $key . '[]';
			$label = $taxonomy->labels->name;
			if ( $taxonomy->singular ) {
				$type = 'radio';
				$name = $key;
				$label = $taxonomy->labels->singular_name;
			}

			// Get all the terms in this taxonomy
			$terms = get_terms( $key, array( 'hide_empty' => false ) );
			?>
			<table class="form-table">
				<tr>
					<th><label for=""><?php echo $label; ?></label></th>
					<td>
						<?php if ( ! empty( $terms ) ) : ?>
							<?php foreach ( $terms as $term ) : ?>
								<input type="<?php echo $type; ?>" name="<?php echo $name; ?>" id="<?php echo "{$key}-{$term->slug}"; ?>" value="<?php echo $term->slug; ?>" <?php checked ( true, is_object_in_term( $user->ID, $key, $term ) ); ?> />
								<label for="<?php echo "{$key}-{$term->slug}"; ?>"><?php echo $term->name; ?></label><br />
							<?php endforeach; // $terms ?>
						<?php else : ?>
							<?php echo $taxonomy->labels->not_found; ?>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php
		endforeach; // $taxonomies

		// Output the above if we have anything, with a heading
		$output	= ob_get_clean();
		if ( ! empty( $output ) ) {
			echo '<h3>', __( 'Taxonomies' ), '</h3>';
			echo $output;
		}
	}
	
	/**
	 * Save the custom user taxonomies when saving a users profile
	 * 
	 * @param int $user_id - The ID of the user to update
	 */
	public function save_profile( $user_id ) {
		foreach ( self::$taxonomies as $key => $taxonomy ) {
			// Check the current user can edit this user and assign terms for this taxonomy
			if ( ! current_user_can( 'edit_user', $user_id ) && current_user_can( $taxonomy->cap->assign_terms ) ) return false;

			if ( ! isset( $_POST[ $key ] ) ) continue;
			$terms = $_POST[ $key ];
			
			// Clean the data
			if ( is_array( $terms ) ) {
				foreach ( $terms as $k => $v ) {
					$terms[ $k ] = esc_attr( $v );
				}
			} else {
				$terms = array( esc_attr( $terms ) );
			}

			// Save the data
			wp_set_object_terms( $user_id, $terms, $key, false );
			clean_object_term_cache( $user_id, $key );
		}
	}
	
	/**
	 * Usernames can't match any of our user taxonomies
	 * As otherwise it will cause a URL conflict
	 * This method prevents that happening
	 */
	public function restrict_username( $username ) {
		if ( isset( self::$taxonomies[ $username ] ) ) return '';

		return $username;
	}
}

new P7_User_Taxonomies;

}

?>
