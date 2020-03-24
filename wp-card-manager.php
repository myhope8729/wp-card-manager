<?php
/*
* Plugin Name: WP Card Manager
* Description: Card Sender via whatsapp/facebook
* Version: 1.0.1
* Plugin URI:
* Author: myhope1227
*
*/

//Exit if accessed directly

if ( !class_exists( 'wp_card_manager' ) ):
class wp_card_manager{

	/**
	 * Plugin instance method.
	 *
	 * Define all actions and filters, shortcodes that plugins will use.
	 *
	 */
	public function instance() {
		add_action( 'init', array( $this, 'plugin_init' ) );
		add_action( 'admin_menu', array( $this, 'plugin_admin_menu' ) );
		add_action( 'wp_ajax_save_card_content', array( $this, 'plugin_save_card_content' ) );
		add_action( 'wp_ajax_nopriv_save_card_content', array( $this, 'plugin_save_card_content' ) );
		add_action( 'wp_ajax_delete_msg_category', array( $this, 'plugin_delete_msg_category' ) );
		add_action( 'wp_ajax_delete_card_message', array( $this, 'plugin_delete_card_message' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'plugin_register_styles' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'plugin_register_admin_script' ), 1 );
		add_action( 'cron_delete_cards', array( $this, 'plugin_delete_cards' ) );

		add_filter( 'cron_schedules', array( $this, 'plugin_add_cron_interval' ) );
		
		add_shortcode( 'ecard', array( $this, 'plugin_shortcode_ecard' ) );
		add_shortcode( 'ecard_view', array( $this, 'plugin_shortcode_ecardview' ) );

		register_activation_hook( __FILE__, array( $this, 'plugin_activate' ) );
		//register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivate' ) );
	}


	/**
	 * Plugin activate method.
	 *
	 * Create all tables that plugin use and create page for ecard.
	 *
	 * @global $wpdb
	 */
	public function plugin_activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Create cards table
		$sql_cards = "CREATE TABLE `{$wpdb->prefix}ecards` (
			id MEDIUMINT NOT NULL AUTO_INCREMENT,
			card_id varchar(24),
			card_img varchar(300),
			card_content varchar(1000),
			card_related varchar(30),
			card_viewed varchar(3),
			send_date	varchar(16),
			PRIMARY KEY (id)
		) $charset_coolate;";


		/* 
		 * Create message category table
		 * It manages categories of suggested messages
		 * It will managed admin area
		 */
		$sql_msg_cats = "CREATE TABLE `{$wpdb->prefix}msg_category` (
			id MEDIUMINT NOT NULL AUTO_INCREMENT,
			cat_name varchar(40),
			PRIMARY KEY (id)
		) $charset_coolate;";

		/* 
		 * Create Suggested messages table
		 * These messages are predefined messages to send.
		 */
		$sql_messages = "CREATE TABLE `{$wpdb->prefix}card_messages` (
			id MEDIUMINT NOT NULL AUTO_INCREMENT,
			cat_id varchar(40),
			message_content varchar(3000),
			PRIMARY KEY (id)
		) $charset_coolate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_cards );
		dbDelta( $sql_msg_cats );
		dbDelta( $sql_messages );

		// Create ECard Page.
		$page_detail = array(
			'post_title' 	=> 'ECard View',
			'post_content' 	=> '[ecard_view][/ecard_view]',
			'post_status'	=> 'publish',
			'post_author'	=> 1,
			'post_type'		=> 'page'
		);

		$page_id = wp_insert_post( $page_detail );
		add_option( 'card_view_page_id', $page_id );
	}

	/**
	 * Plugin activate method.
	 *
	 * Delete card table and created ecard page.
	 * This method is unused temporary.
	 *
	 * @global $wpdb
	 */
	public function plugin_deactivate() {
		global $wpdb;
		$tbl_cards = $wpdb->prefix . 'ecards';

		$wpdb->query( "DROP TABLE IF EXISTS " . $tbl_cards );

		$page_id = get_option( 'card_view_page_id' );
		wp_delete_post( $page_id, true );
		delete_option( 'card_view_page_id' );

		$timestamp = wp_next_scheduled( 'cron_delete_cards' );
		wp_unschedule_event( $timestamp, 'cron_delete_cards' );
	}

	/**
	 * Plugin Init method.
	 *
	 * Define rewrite rule for ecard view
	 * Called by init action
	 *
	 */
	public function plugin_init() {
		$page_id = get_option( 'card_view_page_id' );

		// Redirect all ecard request to ECard page.
		add_rewrite_rule( '^ecard/(.*)/?', 'index.php?page_id='.$page_id.'&card_id=$matches[1]', 'top' );
		add_rewrite_tag('%card_id%', '([^&/]+)');
		flush_rewrite_rules();
	}

	/**
	 * Plugin Admin Menu 
	 *
	 * Define Menus for plugin in Admin area
	 * Called by admin_menu action
	 *
	 */
	public function plugin_admin_menu() {
		add_menu_page( 'Cards', 'Cards', 'manage_options', 'card_manager', array( $this, 'plugin_card_view' ), 'dashicons-email-alt2' );
		add_submenu_page( 'card_manager', 'Cards', 'Sent Cards', 'manage_options', 'card_manager', array( $this, 'plugin_card_view' ) );
		add_submenu_page( 'card_manager', 'Messages', 'Messages', 'manage_options', 'message_manager', array( $this, 'plugin_message_manager' ) );
		add_submenu_page( 'card_manager', 'Messages Categories', 'Messages Categories', 'manage_options', 'message_categories', array( $this, 'plugin_message_categories' ) );
		add_submenu_page( 'card_manager', 'Settings', 'Settings', 'manage_options', 'card_view', array($this, 'plugin_setting'));
		add_submenu_page( NULL, 'Edit message category', 'Edit message category', 'manage_options', 'edit_msg_category', array( $this, 'plugin_edit_msg_category' ) );
		add_submenu_page( NULL, 'Edit message', 'Edit message', 'manage_options', 'edit_card_message', array( $this, 'plugin_edit_card_message' ) );
	}

	/**
	 * Plugin Styles Register
	 *
	 * Include all styles and scripts which uses in frontend for plugin
	 * Called by wp_enqueue_scripts action
	 *
	 */
	public function plugin_register_styles() {
		wp_enqueue_style( 'bootstrap4', 'https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/css/bootstrap.min.css' );
		wp_enqueue_style( 'fontawesome', 'https://netdna.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css' );
		wp_enqueue_script( 'popper', 'https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js', array('jquery'), '', true );
		wp_enqueue_script( 'bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.1.1/js/bootstrap.min.js', array('jquery', 'popper'), '', true );
		wp_enqueue_script( 'googlefont-js', 'https://ajax.googleapis.com/ajax/libs/webfont/1.6.26/webfont.js' );
		wp_enqueue_style( 'plugin-css', plugin_dir_url( __FILE__ ). 'assets/css/plugin.css', array(), '1.0.1' );
		wp_enqueue_script( 'plugin-js', plugin_dir_url( __FILE__ ). 'assets/js/plugin.js', array('jquery'), '1.0.0', true );
	}

	/**
	 * Plugin Admin Styles Register
	 *
	 * Include admin js file 
	 * Called by admin_enqueue_scripts action
	 *
	 */
	public function plugin_register_admin_script(){
		wp_enqueue_script( 'card-admin-js', plugin_dir_url( __FILE__ ). 'assets/js/card-admin.js', array('jquery'), '1.0.0', true );
	}

	/**
	 * Plugin List Card Page
	 *
	 * View sent ecards
	 * Show on Cards and Sent Cards menus
	 *
	 * @global $wpdb
	 */
	public function plugin_card_view() {
		global $wpdb;
		$tbl_cards = $wpdb->prefix . 'ecards';

		$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 1;      

        $limit = 10; // number of rows in page
        $offset = ( $pagenum - 1 ) * $limit;
        $total = $wpdb->get_var( "select count(*) as total from $tbl_cards" );
        $num_of_pages = ceil( $total / $limit );

        // List all cards what has been sent.
        $cards = $wpdb->get_results( "SELECT * FROM $tbl_cards ORDER BY send_date DESC limit  $offset, $limit" );
        $rowcount = $wpdb->num_rows;

?>
<style type="text/css">
.tablenav-pages .page-numbers{
	display: inline-block;
    vertical-align: baseline;
    min-width: 30px;
    min-height: 30px;
    margin: 0;
    padding: 0 4px;
    font-size: 16px;
    line-height: 1.625;
    text-align: center;
    border-width: 1px;
    border-style: solid;
    -webkit-appearance: none;
    border-radius: 3px;
    white-space: nowrap;
    box-sizing: border-box;
    text-decoration: none;
}

.tablenav-pages .page-numbers.current, .tablenav-pages .page-numbers.dots{
	border-color:transparent;
}
</style>
		<div class="wrap">
			<h2>Sent Cards</h2>
			<table class="widefat wp-list-table" style="width:100%">
				<thead>
					<tr>
						<th>Thumbnail</th>
						<th>Url</th>
						<th>Status</th>
						<th>Date</th>
					</tr>
				</thead>
				<tbody>
<?php
		if( $rowcount > 0 ){
			foreach($cards as $card){
?>
				<tr>
					<td><?php echo !empty( $card->card_img )?'<img style="width:60px;" src="'.$card->card_img.'">':'';?></td>
					<td><a href="<?php echo site_url( 'ecard/'. $card->card_id );?>"><?php echo site_url( 'ecard/'. $card->card_id );?></a></td>
					<td><?php echo $card->card_viewed;?></td>
					<td><?php echo $card->send_date;?></td>
				</tr>
<?php
			}
		}
?>
				</tbody>
			</table>
		</div>
<?php
		$page_links = paginate_links( array(
            'base' => add_query_arg( 'pagenum', '%#%' ),
            'format' => '',
            'prev_text' => __( '&laquo;', 'text-domain' ),
            'next_text' => __( '&raquo;', 'text-domain' ),
            'total' => $num_of_pages,
            'current' => $pagenum
        ) );

        if ( $page_links ) {
            echo '<div class="tablenav" style="width: 99%;"><div class="tablenav-pages" style="margin: 1em 0">' . $page_links . '</div></div>';
        }
	}

	/**
	 * Plugin Admin Setting page.
	 *
	 * Settings : cron period - When will delete old ecards
	 * 			  Show on Cards and Sent Cards menus
	 *
	 * Called by Settings menu in admin
	 */
	public function plugin_setting(){
		if ( isset( $_REQUEST['save'] ) ){
			// Update options when user save settings
			update_option( 'card_delete_period', $_REQUEST['cron_period'] );
			update_option( 'card_show_image_shortcode', $_REQUEST['show_image'] );


			// Remove current registered cron schedule
			$timestamp = wp_next_scheduled( 'cron_delete_cards' );
			wp_unschedule_event( $timestamp, 'cron_delete_cards' );

			// Get cron period from option and set new schedule for delete cards
			$cron_period = get_option( 'card_delete_period' );
			if ( !empty( $cron_period ) ){
				if( !wp_next_scheduled( 'cron_delete_cards' ) ) {
					wp_schedule_event( time(), $cron_period . '_month', 'cron_delete_cards' );
				}
			}
?>
		<div id="message" class="notice notice-success notice-alt is-dismissible">
			<p>Settings has been saved successfully.</p>
			<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>
		</div>
<?php
		}

		$cron_period = get_option( 'card_delete_period' );
		$default_show_image = get_option( 'card_show_image_shortcode' );
?>
		<div class="wrap">
			<h2>Cron Setting</h2>
			<form action="" method="post" id="link_manager_form">
				<p>
					<label class="text-label" for="show_image">Show image by default on shortcode?</label>
					<select name="show_image">
						<option value="true" <?php selected( $default_show_image, 'true' ); ?>>Yes</option>
						<option value="false" <?php selected( $default_show_image, 'false' ); ?>>No</option>
					</select>
					Months
				</p>
				<p>
					<label class="text-label" for="cron_period">Delete sent cards per </label>
					<select name="cron_period">
						<option value="1" <?php selected( $cron_period, 1 ); ?>>1</option>
						<option value="2" <?php selected( $cron_period, 2 ); ?>>2</option>
						<option value="3" <?php selected( $cron_period, 3 ); ?>>3</option>
						<option value="3" <?php selected( $cron_period, 6 ); ?>>6</option>
					</select>
					Months
				</p>
				<p class="action">
					<input type="submit" name="save" value="Save" class="button button-primary"/>
				</p>
			</form>
		</div>
<?php
	}

	/**
	 * Plugin Admin Categories page.
	 *
	 * Manage suggested message categories
	 *
	 * Called by Messages Categories menu in admin
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_message_categories() {
		global $wpdb;
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';

		// Insert new category to table
		if ( isset( $_REQUEST['add_category'] ) ) {
			$category_name = $_REQUEST['category_name'];
			$wpdb->insert( $tbl_msg_cats, array( 'cat_name' => $category_name ) );
		}
		
		// Get all categories
		$cats_sql = "SELECT * FROM {$tbl_msg_cats} ";
		$categories = $wpdb->get_results($cats_sql);
?>
		<div class="wrap">
			<h2>Message Categories</h2>
			<br class="clear">
			<div id="col-cointainer">
				<div id="col-right">
					<div class="col-wrap">
						<table class="widefat attributes-table ui-sortable striped wp-list-table" style="width:100%">
							<thead>
								<tr>
									<th scope="col">Category Name</th>
									<th scope="col">Edit</th>
									<th scope="col">Delete</th>
								</tr>
							</thead>
							<tbody>
<?php
						if(!empty($categories)){
							foreach($categories as $category){
?>
							<tr>
								<td><?php echo $category->cat_name;?></td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=edit_msg_category&category_id='.$category->id); ?>">Edit</a>
								</td>
								<td>
									<a href="<?php echo admin_url( 'admin-ajax.php' ); ?>" class="delete_category" data-id="<?php echo $category->id;?>">Delete</a>
								</td>
							</tr>
<?php
							}
						}else{
?>
							<tr>
								<td colspan="3">There is no categories.</td>
							</tr>
<?php							
						}
?>
							</tbody>
						</table>
					</div>
				</div>
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h3>Add a new message category</h3>
							<form method="post" action="">
								<div class="form-field">
									<label for="category_name">Category name</label>
									<input name="category_name" id="category_name" type="text" value="">
									<p class="description">Name for the category.</p>
								</div>
								<p class="submit">
									<input type="submit" name="add_category" id="submit" class="button button-primary" value="Save a category" />
								</p>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
	}

	/**
	 * Plugin Admin Edit Categories page.
	 *
	 * Edit selected category
	 *
	 * Called by Edit link from category table
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_edit_msg_category() {
		global $wpdb;
		$category_id = $_REQUEST['category_id'];
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';

		// Save category
		if ( isset( $_REQUEST['save_category'] ) ){
			$category_name = $_REQUEST['category_name'];

			$wpdb->update( $tbl_msg_cats, array('cat_name' => $category_name) , array( 'id' => $category_id ) );
			$is_updated = true;
		}

		// Get Category object that user wants to edit
		$sql_msg_cats = "SELECT * FROM {$tbl_msg_cats} WHERE id=".$category_id;
		$category_obj = $wpdb->get_row( $sql_msg_cats );
?>
	<div class="wrap">
		<h2>Edit message category</h2>
<?php
		if ( $is_updated ) {
?>
		<div id="message" class="notice notice-success">
			<p><strong>Category updated.</strong></p>
			<p><a href="<?php echo admin_url( 'admin.php?page=message_categories' );?>">← Back to Categories</a></p>
		</div>
<?php
		}
?>
		<br class="clear">
		<form id="edittag" method="post" action="">
			<table class="form-table" role="presentation">
				<tbody>
					<tr class="form-field form-required term-name-wrap">
						<th scope="row">
							<label for="name">Category name</label>
						</th>
						<td>
							<input name="category_name" id="category_name" type="text" value="<?php echo $category_obj->cat_name;?>" size="40" aria-required="true">
							<p class="description">Name for the category.</p>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="edit-tag-actions">
				<input type="submit" name="save_category" class="button button-primary" value="Update">
			</div>
		</form>
	</div>
<?php
	}

	/**
	 * Plugin Admin Delete Categories page.
	 *
	 * Delete selected category
	 *
	 * Called by Delete link from category table
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_delete_msg_category() {
		global $wpdb;
		$category_id = $_REQUEST['category_id'];
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';
		$sql = "DELETE FROM {$tbl_msg_cats} WHERE id={$category_id}";
		$wpdb->query($sql);
		exit;
	}

	/**
	 * Plugin Admin Message management page.
	 *
	 * Manage suggested message for card
	 *
	 * Called by Messages menu from Admin
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_message_manager() {
		global $wpdb;
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';
		$tbl_message = $wpdb->prefix . 'card_messages';

		// Add message to table
		if ( isset( $_REQUEST['add_message'] ) ) {
			$message_content = $_REQUEST['message_content'];
			$category_id = $_REQUEST['cat_id'];
			$wpdb->insert( $tbl_message, array( 'cat_id' => $category_id, 'message_content' => $message_content ) );
		}

		// Get all messages by category		
		$message_sql = "SELECT a.id, a.message_content, b.cat_name FROM {$tbl_message} a JOIN {$tbl_msg_cats} b ON a.cat_id = b.id ORDER BY b.id ASC, a.cat_id DESC";
		$messages = $wpdb->get_results($message_sql);

		$sql_categories = "SELECT * FROM {$tbl_msg_cats} ";
		$categories = $wpdb->get_results($sql_categories);
?>
		<div class="wrap">
			<h2>Suggested Messages</h2>
			<br class="clear">
			<div id="col-cointainer">
				<div id="col-right">
					<div class="col-wrap">
						<table class="widefat attributes-table ui-sortable striped wp-list-table" style="width:100%">
							<thead>
								<tr>
									<th scope="col">Category</th>
									<th scope="col">Message</th>
									<th scope="col">Edit</th>
									<th scope="col">Delete</th>
								</tr>
							</thead>
							<tbody>
<?php
						if(!empty($messages)){
							foreach($messages as $message){
?>
							<tr>
								<td><?php echo $message->cat_name;?></td>
								<td><?php echo stripslashes( $message->message_content );?></td>
								<td>
									<a href="<?php echo admin_url( 'admin.php?page=edit_card_message&msg_id='.$message->id); ?>">Edit</a>
								</td>
								<td>
									<a href="<?php echo admin_url( 'admin-ajax.php' );?>" data-id="<?php echo $message->id; ?>" class="delete_message">Delete</a>
								</td>
							</tr>
<?php
							}
						}else{
?>
							<tr>
								<td colspan="4">There is no messages.</td>
							</tr>
<?php							
						}
?>
							</tbody>
						</table>
					</div>
				</div>
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h3>Add a new message.</h3>
							<form method="post" action="">
								<div class="form-field">
									<label for="cat_id">Category</label>
									<select name="cat_id" id="cat_id">
									<?php
									foreach ($categories as $key => $category) {
									?>
										<option value="<?php echo $category->id;?>"><?php echo $category->cat_name;?></option>
									<?php
									}
									?>
									</select>
									<p class="description">Select a category from dropdown.</p>
								</div>
								<div class="form-field">
									<label for="message_content">Message</label>
									<textarea name="message_content" value="message_content" rows="5"></textarea>
									<p class="description">Input a message.</p>
								</div>
								<p class="submit">
									<input type="submit" name="add_message" id="submit" class="button button-primary" value="Save a message" />
								</p>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
<?php
	}

	/**
	 * Plugin Admin Edit Message page.
	 *
	 * Edit selected suggested message for card
	 *
	 * Called by Edit link from Messages list
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_edit_card_message() {
		global $wpdb;
		
		$msg_id = $_REQUEST['msg_id'];
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';
		$tbl_message = $wpdb->prefix . 'card_messages';

		if ( isset( $_REQUEST['save_message'] ) ) {
			$message_content = $_REQUEST['message_content'];
			$category_id = $_REQUEST['cat_id'];
			$wpdb->update( $tbl_message, array( 'cat_id' => $category_id, 'message_content' => $message_content ), array( 'id' => $msg_id ) );

			$is_updated = true;
		}

		$sql_categories = "SELECT * FROM {$tbl_msg_cats} ";
		$categories = $wpdb->get_results($sql_categories);

		$sql_message = "SELECT * FROM {$tbl_message} WHERE id=".$msg_id;
		$msg_obj = $wpdb->get_row( $sql_message );
?>
	<div class="wrap">
		<h2>Edit Message</h2>
<?php
		if ( $is_updated ) {
?>
		<div id="message" class="notice notice-success">
			<p><strong>Card Message updated.</strong></p>
			<p><a href="<?php echo admin_url( 'admin.php?page=message_manager' );?>">← Back to Messages</a></p>
		</div>
<?php
		}
?>
		<br class="clear">
		<form id="edittag" method="post" action="">
			<table class="form-table" role="presentation">
				<tbody>
					<tr class="form-field form-required term-name-wrap">
						<th scope="row">
							<label for="cat_id">Category</label>
						</th>
						<td>
							<select name="cat_id" id="cat_id">
							<?php
							foreach ($categories as $key => $category) {
							?>
								<option value="<?php echo $category->id;?>" <?php selected( $category->id, $msg_obj->cat_id );?> ><?php echo $category->cat_name;?></option>
							<?php
							}
							?>
							</select>
							<p class="description">Name for the category.</p>
						</td>
					</tr>
					<tr class="form-field form-required term-name-wrap">
						<th scope="row">
							<label for="message_content">Message</label>
						</th>
						<td>
							<textarea name="message_content" value="message_content" rows="5"><?php echo stripslashes($msg_obj->message_content);?></textarea>
							<p class="description">Input a message.</p>
						</td>
					</tr>
				</tbody>
			</table>
			<div class="edit-tag-actions">
				<input type="submit" name="save_message" class="button button-primary" value="Update">
			</div>
		</form>
	</div>
<?php
	}

	/**
	 * Plugin Admin Delete Message page.
	 *
	 * Delete selected suggested message for card
	 *
	 * Called by Delete link from Messages list
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_delete_card_message() {
		global $wpdb;
		$message_id = $_REQUEST['message_id'];
		$tbl_card_msg = $wpdb->prefix . 'card_messages';
		$sql = "DELETE FROM {$tbl_card_msg} WHERE id={$message_id}";
		$wpdb->query($sql);
		exit;
	}

	/**
	 * Plugin Ecard shortcode for sender.
	 *
	 * Display card image and send button and design form.
	 * Shortcode - [ecard show_image=true related="3,4,2"]
	 * 			show_image : display card image to page or not
	 *			related : Related posts to show on ecard view page
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_shortcode_ecard( $attributes, $image = null ) {
		global $wpdb;
		
		// Prepare suggested messages and its categories for "Suggested messages" on design form
		$tbl_msg_cats = $wpdb->prefix . 'msg_category';
		$tbl_message = $wpdb->prefix . 'card_messages';

		$sql_categories = "SELECT * FROM {$tbl_msg_cats} ";
		$categories = $wpdb->get_results($sql_categories);

		$sql_messages = "SELECT * FROM {$tbl_message} ORDER BY cat_id ASC, id DESC";
		$messages = $wpdb->get_results( $sql_messages );

		$ajaxObj = array(
	        'ajaxurl' => admin_url( 'admin-ajax.php' )
	    );
		wp_localize_script( 'plugin-js', 'ajaxObj', $ajaxObj );

		// Fonts for message on design form
		$fonts = array("Abril Fatface", "Acme", "Alfa Slab One", "Amatic SC", "Amiri", "Bangers", "Bebas Neue", "Bree Serif", "Cookie", "Dancing Script", "Fredoka One", "Great Vibes", "Lateef", "Lobster", "Luckiest Guy", "Merriweather", "Pacifico", "Passion One", "Playfair Display", "Rancho", "Roboto", "Roboto Slab", "Rochester", "Sacramento", "Sen");

		// Colors for message on design form
		$colors = array( "#000000", "#FFB400", "#E53D39", "#2C91DE", '#FFFFFF', '#970E65', '#650633', '#F3A2A3', '#D44F70', '#CA0B15', '#97070E', '#650508', '#F35D19', '#9E5727', '#D8B571', '#978454', '#65320C', '#8793D6', '#866AA5', '#552978', '#1D549F', '#073464', '#55B2AE', '#686868', '#D5D5D5', '#FFFAA3', '#C0CFAF', '#469E57' );
		
		$modal_id = uniqid( rand(), false );

		$default_show_image = get_option( 'card_show_image_shortcode', 'true' );

		list($card_img_width, $card_img_height, $type, $attr) = getimagesize($image);

		// Determine design form dimension based on card image
		if ( $card_img_width > $card_img_height ) {
			$width = 320;
			$height = 320 * $card_img_height / $card_img_width;
		} else {
			$width = 320;
			$height = 320 * $card_img_height / $card_img_width;
		}

		// Get shortcode attributes

		extract(shortcode_atts(array(
	      'show_image' => $default_show_image,
	      'related'	=> '',
	   	), $attributes));

		$content = '<div class="card-container">';

		if ( $show_image == 'true' ){
			$content .= '<div class="card-thumbnail">';
			$content .= '<img src="' . $image . '"/>';
			$content .= '</div>';
		}

		$content .= '<button class="button btn-primary card-sender" data-toggle="modal" data-target="#card-send-dlg-'.$modal_id.'">Send</button>';
		$content .= '</div>';
		$content .= '
			<div id="card-send-dlg-' . $modal_id .'" class="modal card-send-dlg card-modal" role="dialog">
				<div class="modal-dialog modal-dialog-centered" role="document">
					<div class="modal-content">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span area-hidden="true">&times;</span>
						</button>
						<div class="modal-body">
							<div class="container-fluid">
								<div class="content-editor">
									<div class="editor_description before_button">Description Here before the buttons.</div>
									<div class="editor_buttons">
										<div class="row-font">
											<div class="btn-group">
												<button class="button btn-default dropdown-toggle font-display" type="button" id="fonts-' . $modal_id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span>Font</span></button>
												<div class="dropdown-menu btn-fonts" aria-labelledby="fonts-' . $modal_id . '">';
											foreach ($fonts as $key => $font) {
												$content .= '<button class="dropdown-item" type="button" style="font-family:' . $font . '">' . $font . '</button>';
											}
									$content .=	'</div>
											</div>
											<div class="btn-group">
												<button class="button btn-default dropdown-toggle font-size-display" type="button" id="font-size-' . $modal_id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span>Size</span></button>
												<div class="dropdown-menu btn-font-size" aria-labelledby="font-size-' . $modal_id . '">';
											for ($font_size = 12; $font_size < 35; $font_size++) {
												$content .= '<button class="dropdown-item" type="button">' . $font_size . 'px</button>';
											}
									$content .=	'</div>
											</div>
											<div class="btn-group">
												<button class="button btn-default dropdown-toggle text-align-display" type="button" id="text-align-' . $modal_id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span><i class="fa fa-align-left"></i></span></button>
												<div class="dropdown-menu btn-text-align" aria-labelledby="text-align-' . $modal_id . '">
													<button class="dropdown-item" type="button" data-align="left"><i class="fa fa-align-left"></i></button>
													<button class="dropdown-item" type="button" data-align="center"><i class="fa fa-align-center"></i></button>
													<button class="dropdown-item" type="button" data-align="right"><i class="fa fa-align-right"></i></button>
												</div>
											</div>
										</div>
										<div class="row-color">
											<div class="colors">
												<div class="color-wrap">';
									if ( sizeof( $colors ) > 10 ) 
									{
										for ( $i = 0; $i < 9; $i++ )
										{
											$white_class = '';
											if ( $colors[$i] == "#FFFFFF" ){
												$white_class = 'white-color';
											}
											$content .= '<input id="cr_'.$i.'" type="radio" name="color"/>
													<label class="text-color '. $white_class.'" for="cr_'.$i.'"><span class="color-thumb" style="background-color:'.$colors[$i].'"></span></label>';
										}
											$content .= '
													<div class="icon-more"><i class="fa fa-ellipsis-h"></i></div>
												</div>
												<div class="more_colors">
													<div class="color-wrap">';
										for ( $i = 9; $i < sizeof( $colors ); $i++ ) {
											$white_class = '';
											if ( $colors[$i] == "#FFFFFF" ){
												$white_class = 'white-color';
											}
											$content .= '<input id="cr_'.$i.'" type="radio" name="color"/>
												<label class="text-color '. $white_class.'" for="cr_'.$i.'"><span class="color-thumb" style="background-color:'.$colors[$i].'"></span></label>';
										}
										$content .= '
													</div>
												</div>';
									}else{
										for ( $i = 0; $i < sizeof( $colors ); $i++ ) {
											$white_class = '';
											if ( $colors[$i] == "#FFFFFF" ){
												$white_class = 'white-color';
											}
											$content .= '<input id="cr_'.$i.'" type="radio" name="color"/>
												<label class="text-color '. $white_class.'" for="cr_'.$i.'"><span class="color-thumb" style="background-color:'.$colors[$i].'"></span></label>';
										}
										$content .= '</div>';
									}
										$content .= '
											</div>
										</div>';

								if ( $messages && sizeof($messages) > 0 ){
									$content .= '
										<div class="row_message">
											Suggested Messages
										</div>';
								}
								$content .='
									</div>
									<!--div class="editor_description after_button">Description Here after the buttons.</div-->
									<div class="editor_wrapper" style="width:'.$width.'px;height:'.$height.'px;">
										<div class="editor_container">
						 					<textarea class="editor" style="text-align:center;" placeholder="Add Text"></textarea>
						 				</div>
						 			</div>
									<div class="card-action-wrapper text-center">
										<p>Send via whatsapp or Facebook/messenger</p> 
										<input type="hidden" class="card_img" value="' . $image . '" />
										<input type="hidden" class="card_related" value="' . $related. '" />
										<button type="button" class="button btn-info btn-whatsapp">Whatsapp</button>
										<button type="button" class="button btn-info btn-facebook">Facebook</button>
										<button type="button" class="button btn-info btn-copy-link">Copy Link</button>
									</div>
								</div>
							</div>
						</div>';
				if ( $messages && sizeof($messages) > 0 ){
					$content .= '
						<div class="suggested_messages">
							<h3>Suggested Messages<span class="close_message">&times;Close</span></h3>
							<div class="message_category">
								<button class="button btn-default dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span>Font</span></button>
								<div class="dropdown-menu msg-category" aria-labelledby="msg-cat-' . $modal_id . '">';
							foreach ($categories as $ind => $category) {
								$content .= '<button class="dropdown-item" type="button" data-cat="'.$category->id.'">'.$category->cat_name.'</button>';
							}
							$content .='
								</div>
							</div>
							<div class="card_messages">';
						foreach ( $messages as $ind => $message ) {
							$content .= '
								<div class="message cat-'.$message->cat_id.'">'. stripslashes($message->message_content) .'</div>';
						}
							$content .='
							</div>
						</div>';
				}
					$content .= '
					</div>
				</div>
			</div>';
		
		return $content;
	}

	/**
	 * Plugin Save Ecard content
	 *
	 * Save Ecard content to send
	 * Called by wp_ajax_save_card_content
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_save_card_content(){
		global $wpdb;

		$tbl_cards = $wpdb->prefix . 'ecards';

		$card_img 		= $_REQUEST['card_img'];
		$content 		= $_REQUEST['content'];
		$card_related 	= $_REQUEST['card_related'];

		// Generate ecard id that included to url
		$card_id = 'e' . str_replace( '.', 'c', uniqid( '', true ) );

		$ins_res = $wpdb->insert($tbl_cards, 
			array(
				'card_img' 		=> $card_img,
				'card_id'		=> $card_id,
				'card_content' 	=> $content,
				'card_related'	=> $card_related,
				'card_viewed'	=> 'No',
				'send_date'		=> date('d/m/Y H:i')
			)
		);

		$result = array();

		if ( is_wp_error( $ins_res ) ){
			$result['result'] = 'error';
		}else{
			$result['result'] = 'success';
			$result['url'] = site_url( 'ecard/'. $card_id );
		}

		echo json_encode($result);
		exit;
	}

	/**
	 * Plugin Ecard View shortcode for receiver.
	 *
	 * Display received card with animation on receiver's side
	 *
	 * @global $wpdb
	 *
	 */
	public function plugin_shortcode_ecardview() {
		global $wpdb;
		$card_page_id = get_option( 'card_view_page_id' );
		$card_id = get_query_var( 'card_id' );
		$tbl_ecard = $wpdb->prefix . 'ecards';

		// Update card state to viewed
		$wpdb->update( $tbl_ecard, array( 'card_viewed' => 'Yes' ), array( 'card_id' => $card_id ) );

		// Get card object to show
		$card_sql = "SELECT * FROM {$tbl_ecard} WHERE card_id='".$card_id."'";
		$card_data = $wpdb->get_row( $card_sql );

		$card_img = $card_data->card_img;
		$card_related = explode( ',', $card_data->card_related );
		
		// Get related posts for this card
		$related_args = array(
			'post_type' => 'post',
			'post__in'	=> $card_related,
		);

		$related_posts = new WP_Query( $related_args );

		// Determine card type by its dimension
		list($card_img_width, $card_img_height, $type, $attr) = getimagesize($card_img);

		$direction = ( $card_img_width > $card_img_height )?'layout-landscape':'layout-portrait';

		$card_content = preg_replace( '/\s+/', '', strip_tags( $card_data->card_content ) );

		$content = '
			<div class="card-wrapper">
				<div class="layout layout-horizontal ' . $direction . '">';
			if ( $card_content != "" ) {
				$content .= '<a class="flip">return the card <br/>to read the message</a>';
			}
			$content .= '
					<div class="envelope first-hidden">
						<div class="card">
							<div class="card-inner">
					';
			if ( $card_content != "" ){
				$content .= '
								<div class="card-side card-back">'. nl2br( stripslashes( $card_data->card_content ) ) .'</div>';
			}
			$content .= '
								<div class="card-side card-front"><img src="'.$card_img.'"/></div>
							</div>
						</div>
					</div>
				</div>
				<p class="card-date">'.$card_data->send_date.'</p>
				<div class="related-cards">';
		if ( $related_posts->have_posts() ){
			$content .= '<h3>Related Cards</h3><div class="card-items-wrapper">';
			while ( $related_posts->have_posts() ) : 
				$related_posts->the_post();
				$content .= '<div class="card-item">'.get_the_post_thumbnail().'<h4><a href="'.get_the_permalink().'">'.get_the_title().'</a></h4></div>';
			endwhile;
		}
		$content .=	'</div>
				</div>
			</div>
		'; 

		return $content;
	}

	/**
	 * Plugin Cron Interval method
	 *
	 * Define cron event scheduler for plugin. 
	 * Available period : 1 Month, 2 Months, 3 Months, 6 Months
	 *
	 */
	public function plugin_add_cron_interval( $schedules ) {
		$schedules['1_month'] = array(
	        'interval' => 3600 * 24 * 30,
	        'display'  => esc_html__( 'Every 1 Month' ),
	    );

	    $schedules['2_month'] = array(
	        'interval' => 3600 * 24 * 60,
	        'display'  => esc_html__( 'Every 2 Months' ),
	    );

	    $schedules['3_month'] = array(
	        'interval' => 3600 * 24 * 30 * 3,
	        'display'  => esc_html__( 'Every 3 Months' ),
	    );

	    $schedules['6_month'] = array(
	        'interval' => 3600 * 24 * 30 * 6,
	        'display'  => esc_html__( 'Every 6 Months' ),
	    );
	 
	    return $schedules;
	}

	/**
	 * Plugin Delete Cards Method
	 *
	 * Delete created cards for cron event scheduler
	 *
	 * @global $wpdb
	 */
	public function plugin_delete_cards(){
		global $wpdb;
		$tbl_cards = $wpdb->prefix . 'ecards';

		$wpdb->query('TRUNCATE TABLE {$tbl_cards}');
	}

}


// Create Plugin instance and start
$card_obj = new wp_card_manager();
$card_obj->instance();

endif;
