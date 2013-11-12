<?php
/**
 * Campaigns
 *
 * All things to do with campaigns as a whole.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 */

class ATCF_Campaigns {

	/**
	 * Start things up.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'setup' ), -1 );
	}

	/**
	 * Some basic tweaking.
	 *
	 * Set the archive slug, and remove formatting from prices.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return void
	 */
	function setup() {
		define( 'EDD_SLUG', apply_filters( 'atcf_edd_slug', 'campaigns' ) );
		
		add_filter( 'edd_download_labels', array( $this, 'download_labels' ) );
		add_filter( 'edd_default_downloads_name', array( $this, 'download_names' ) );
		add_filter( 'edd_download_supports', array( $this, 'download_supports' ) );
		
		do_action( 'atcf_campaigns_actions' );
		
		if ( ! is_admin() )
			return;
		
		add_filter( 'edd_price_options_heading', 'atcf_edd_price_options_heading' );
		add_filter( 'edd_variable_pricing_toggle_text', 'atcf_edd_variable_pricing_toggle_text' );

		add_filter( 'manage_edit-download_columns', array( $this, 'dashboard_columns' ), 11, 1 );
		add_filter( 'manage_download_posts_custom_column', array( $this, 'dashboard_column_item' ), 11, 2 );
		
		add_action( 'add_meta_boxes', array( $this, 'remove_meta_boxes' ), 11 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_filter( 'edd_metabox_fields_save', array( $this, 'meta_boxes_save' ) );
		add_filter( 'edd_metabox_save_campaign_end_date', 'atcf_campaign_save_end_date' );

		remove_action( 'edd_meta_box_fields', 'edd_render_product_type_field', 10 );

		add_action( 'edd_download_price_table_head', 'atcf_pledge_limit_head', 9 );
		add_action( 'edd_download_price_table_row', 'atcf_pledge_limit_column', 9, 3 );

		add_action( 'edd_after_price_field', 'atcf_after_price_field' );

		add_action( 'admin_action_atcf-collect-funds', array( $this, 'collect_funds' ) );
		add_action( 'admin_action_atcf-reinstate', array( $this, 'reinstate' ) );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );

		add_action( 'wp_insert_post', array( $this, 'update_post_date_on_publish' ) );

		do_action( 'atcf_campaigns_actions_admin' );
	}

	/**
	 * Download labels. Change it to "Campaigns".
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $labels The preset labels
	 * @return array $labels The modified labels
	 */
	function download_labels( $labels ) {
		$labels =  apply_filters( 'atcf_campaign_labels', array(
			'name' 				=> __( 'Campaigns', 'atcf' ),
			'singular_name' 	=> __( 'Campaign', 'atcf' ),
			'add_new' 			=> __( 'Add New', 'atcf' ),
			'add_new_item' 		=> __( 'Add New Campaign', 'atcf' ),
			'edit_item' 		=> __( 'Edit Campaign', 'atcf' ),
			'new_item' 			=> __( 'New Campaign', 'atcf' ),
			'all_items' 		=> __( 'All Campaigns', 'atcf' ),
			'view_item' 		=> __( 'View Campaign', 'atcf' ),
			'search_items' 		=> __( 'Search Campaigns', 'atcf' ),
			'not_found' 		=> __( 'No Campaigns found', 'atcf' ),
			'not_found_in_trash'=> __( 'No Campaigns found in Trash', 'atcf' ),
			'parent_item_colon' => '',
			'menu_name' 		=> __( 'Campaigns', 'atcf' )
		) );

		return $labels;
	}

	/**
	 * Further change "Download" & "Downloads" to "Campaign" and "Campaigns"
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $labels The preset labels
	 * @return array $labels The modified labels
	 */
	function download_names( $labels ) {
		$cpt_labels = $this->download_labels( array() );

		$labels = array(
			'singular' => $cpt_labels[ 'singular_name' ],
			'plural'   => $cpt_labels[ 'name' ]
		);

		return $labels;
	}

	/**
	 * Add excerpt support for downloads.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $supports The post type supports
	 * @return array $supports The modified post type supports
	 */
	function download_supports( $supports ) {
		$supports[] = 'excerpt';
		$supports[] = 'comments';
		$supports[] = 'author';

		if ( ! atcf_theme_supports( 'campaign-featured-image' ) ) {
			if ( ( $key = array_search( 'thumbnail', $supports ) ) !== false ) {
				unset( $supports[$key]);
			}
		}

		return $supports;
	}

	/**
	 * Download Columns
	 *
	 * Add "Amount Funded" and "Expires" to the main campaign table listing. 
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $supports The post type supports
	 * @return array $supports The modified post type supports
	 */
	function dashboard_columns( $columns ) {
		$columns = apply_filters( 'atcf_dashboard_columns', array(
			'cb'                => '<input type="checkbox"/>',
			'title'             => __( 'Name', 'atcf' ),
			'type'              => __( 'Type', 'atcf' ),
			'backers'           => __( 'Backers', 'atcf' ),
			'funded'            => __( 'Amount Funded', 'atcf' ),
			'expires'           => __( 'Days Remaining', 'atcf' )
		) );

		return $columns;
	}

	/**
	 * Download Column Items
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $supports The post type supports
	 * @return array $supports The modified post type supports
	 */
	function dashboard_column_item( $column, $post_id ) {
		$campaign = atcf_get_campaign( $post_id );

		switch ( $column ) {
			case 'funded' :
				printf( _x( '%s of %s', 'funded of goal', 'atcf' ), $campaign->current_amount(true), $campaign->goal(true) );

				break;
			case 'expires' :
				echo $campaign->is_endless() ? '&mdash;' : $campaign->days_remaining();

				break;
			case 'type' :
				echo ucfirst( $campaign->type() );

				break;
			case 'backers' :
				echo $campaign->backers_count();

				break;
			default : 
				break;
		}
	}

	/**
	 * Remove some metaboxes that we don't need to worry about. Sales
	 * and download stats, aren't really important. 
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return void
	 */
	function remove_meta_boxes() {
		$boxes = array( 
			'edd_file_download_log' => 'normal',
			'edd_purchase_log'      => 'normal',
			'edd_download_stats'    => 'side'
		);

		foreach ( $boxes as $box => $context ) {
			remove_meta_box( $box, 'download', $context );
		}
	}

	/**
	 * Add our custom metaboxes.
	 *
	 * - Collect Funds
	 * - Campaign Stats
	 * - Campaign Video
	 *
	 * As well as some other information plugged into EDD in the Download Configuration
	 * metabox that already exists.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return void
	 */
	function add_meta_boxes() {
		global $post;

		if ( ! is_object( $post ) )
			return;

		$campaign = atcf_get_campaign( $post );

		if ( 
			( ! $campaign->is_collected() && 
			( 'flexible' == $campaign->type() || $campaign->is_funded() ) &&
			atcf_has_preapproval_gateway() /*&&
			$campaign->backers_count() > 0*/ ) ||
			$campaign->failed_payments()
		)
			add_meta_box( 'atcf_campaign_funds', __( 'Campaign Funds', 'atcf' ), '_atcf_metabox_campaign_funds', 'download', 'side', 'high' );

		if ( $campaign->is_collected() && ! $campaign->failed_payments() ) {
			add_meta_box( 'atcf_campaign_reinstate', __( 'Reinstate Campaign', 'atcf' ), '_atcf_metabox_campaign_reinstate', 'download', 'side', 'high' );
		}

		add_meta_box( 'atcf_campaign_stats', __( 'Campaign Stats', 'atcf' ), '_atcf_metabox_campaign_stats', 'download', 'side', 'high' );
		add_meta_box( 'atcf_campaign_updates', __( 'Campaign Updates', 'atcf' ), '_atcf_metabox_campaign_updates', 'download', 'normal', 'high' );

		if ( atcf_theme_supports( 'campaign-video' ) )
			add_meta_box( 'atcf_campaign_video', __( 'Campaign Video', 'atcf' ), '_atcf_metabox_campaign_video', 'download', 'normal', 'high' );

		add_action( 'edd_meta_box_fields', '_atcf_metabox_campaign_info', 5 );
	}

	/**
	 * Campaign Information
	 *
	 * Hook in to EDD and add a few more things that will be saved. Use
	 * this so we are already cleared/validated.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $fields An array of fields to save
	 * @return array $fields An updated array of fields to save
	 */
	function meta_boxes_save( $fields ) {
		$fields[] = '_campaign_featured';
		$fields[] = '_campaign_physical';
		$fields[] = 'campaign_goal';
		$fields[] = 'campaign_contact_email';
		$fields[] = 'campaign_end_date';
		$fields[] = 'campaign_endless';
		$fields[] = 'campaign_norewards';
		$fields[] = 'campaign_video';
		$fields[] = 'campaign_location';
		$fields[] = 'campaign_author';
		$fields[] = 'campaign_type';
		$fields[] = 'campaign_updates';

		return $fields;
	}

	/**
	 * Collect Funds
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @return void
	 */
	function collect_funds() {
		global $edd_options, $failed_payments;

		$campaign = absint( $_GET[ 'campaign' ] );
		$campaign = atcf_get_campaign( $campaign );

		/** check nonce */
		if ( ! check_admin_referer( 'atcf-collect-funds' ) ) {
			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
			exit();
		}

		/** check roles */
		if ( ! current_user_can( 'update_core' ) ) {
			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit', 'message' => 12 ), admin_url( 'post.php' ) ) );
			exit();
		}

		$backers          = $campaign->backers();
		$gateways         = edd_get_enabled_payment_gateways();
		$failed_payments  = array();

		if ( empty( $backers ) ) {
			delete_post_meta( $campaign->ID, '_campaign_failed_payments' );
			
			wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit', 'message' => 14 ), admin_url( 'post.php' ) ) );
			exit();
		}

		
		foreach ( $backers as $backer ) {
			$payment_id = get_post_meta( $backer->ID, '_edd_log_payment_id', true );
			$gateway    = get_post_meta( $payment_id, '_edd_payment_gateway', true );

			if ( 'publish' == get_post_field( 'post_status', $payment_id ) || ! $payment_id )
				continue;

			$gateways[ $gateway ][ 'payments' ][] = $payment_id;
		}

		$process = $gateways;
		
		foreach ( $process as $gateway => $gateway_args ) {
			do_action( 'atcf_collect_funds_' . $gateway, $gateway, $gateway_args, $campaign, $failed_payments );
		}

		if ( ! empty( $failed_payments ) ) {
			$failed_count = 0;

			foreach ( $failed_payments as $gateway => $payments ) {
				/** Loop through each gateway's failed payments */
				foreach ( $payments[ 'payments' ] as $payment_id ) {
					edd_insert_payment_note( $payment_id, apply_filters( 'atcf_failed_payment_note', sprintf( __( 'Error processing preapproved payment via %s when collecting funds.', 'atcf' ), $gateway ) ) );

					$failed_count++;

					do_action( 'atcf_failed_payment', $payment_id, $gateway );
				}
			}

			update_post_meta( $campaign->ID, '_campaign_failed_payments', $failed_payments );

			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit', 'message' => 15, 'failed' => $failed_count ), admin_url( 'post.php' ) ) );
			exit();
		} else {
			update_post_meta( $campaign->ID, '_campaign_bulk_collected', 1 );
			delete_post_meta( $campaign->ID, '_campaign_failed_payments' );

			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit', 'message' => 13, 'collected' => $campaign->backers_count() ), admin_url( 'post.php' ) ) );
			exit();
		}
	}

	/**
	 * Reinstate Campaign
	 *
	 * @since Astoundify Crowdfunding 1.5
	 *
	 * @return void
	 */
	function reinstate() {
		global $edd_options;

		$campaign = absint( $_GET[ 'campaign' ] );
		$campaign = atcf_get_campaign( $campaign );

		/** check nonce */
		if ( ! check_admin_referer( 'atcf-reinstate' ) ) {
			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
			exit();
		}

		/** check roles */
		if ( ! current_user_can( 'update_core' ) ) {
			return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
			exit();
		}

		delete_post_meta( $campaign->ID, '_campaign_bulk_collected' );
		delete_post_meta( $campaign->ID, '_campaign_failed_payments' );
		delete_post_meta( $campaign->ID, '_campaign_expired' );

		return wp_safe_redirect( add_query_arg( array( 'post' => $campaign->ID, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
		exit();
	}

	/**
	 * Custom messages for various actions when managing campaigns.
	 *
	 * @since Astoundify Crowdfunding 0.1-alpha
	 *
	 * @param array $messages An array of messages to display
	 * @return array $messages An updated array of messages to display
	 */
	function messages( $messages ) {
		$messages[ 'download' ][11] = sprintf( __( 'This %s has not reached its funding goal.', 'atcf' ), strtolower( edd_get_label_singular() ) );
		$messages[ 'download' ][12] = sprintf( __( 'You do not have permission to collect funds for %s.', 'atcf' ), strtolower( edd_get_label_plural() ) );
		$messages[ 'download' ][13] = sprintf( __( 'All payments have been processed and collected for this %s.', 'atcf' ), strtolower( edd_get_label_singular() ) );
		$messages[ 'download' ][14] = sprintf( __( 'There are no payments for this %s.', 'atcf' ), strtolower( edd_get_label_singular() ) );
		$messages[ 'download' ][15] = sprintf( __( '<strong>Payments processed.</strong> %d payments failed to process. Please check <a href="%s">gateway logs</a> before trying again.', 'atcf' ), isset ( $_GET[ 'failed' ] ) ? intval( $_GET[ 'failed' ] ) : 0, admin_url( 'edit.php?view=gateway_errors&post_type=download&page=edd-reports&tab=logs' ) );

		return $messages;
	}

	/**
	 * When a campaign is published, reset the campaign end date based
	 * on the original number of days set when submitting.
	 *
	 * @since Astoundify Crowdfunding 1.6
	 *
	 * @return void
	 */
	public function update_post_date_on_publish() {
		global $post;

		if ( ! isset ( $post ) )
			return;

		if ( 'pending' != $post->post_status )
			return $post;

		$length = $post->campaign_length;

		$end_date = strtotime( sprintf( '+%d days', $length ) );
		$end_date = get_gmt_from_date( date( 'Y-m-d H:i:s', $end_date ) );

		update_post_meta( $post->ID, 'campaign_end_date', sanitize_text_field( $end_date ) );
	}
}

new ATCF_Campaigns;

/**
 * Filter the expiration date for a campaign.
 *
 * A hidden/fake input field so the filter is triggered, then
 * add all the other date fields together to create the MySQL date.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @param string $date
 * @return string $end_date Formatted date
 */
function atcf_campaign_save_end_date( $new ) {
	global $post;

	if ( ! isset( $_POST[ 'end-aa' ] ) ) {
		if ( $_POST[ 'campaign_endless' ] == 0 ) {
			delete_post_meta( $post->ID, 'campaign_endless' );
		}

		return;
	}

	$aa = $_POST['end-aa'];
	$mm = $_POST['end-mm'];
	$jj = $_POST['end-jj'];
	$hh = $_POST['end-hh'];
	$mn = $_POST['end-mn'];
	$ss = $_POST['end-ss'];

	$aa = ($aa <= 0 ) ? date('Y') : $aa;
	$mm = ($mm <= 0 ) ? date('n') : $mm;
	$jj = ($jj > 31 ) ? 31 : $jj;
	$jj = ($jj <= 0 ) ? date('j') : $jj;

	$hh = ($hh > 23 ) ? $hh -24 : $hh;
	$mn = ($mn > 59 ) ? $mn -60 : $mn;
	$ss = ($ss > 59 ) ? $ss -60 : $ss;

	$end_date = sprintf( "%04d-%02d-%02d %02d:%02d:%02d", $aa, $mm, $jj, $hh, $mn, $ss );
	
	$valid_date = wp_checkdate( $mm, $jj, $aa, $end_date );
	
	if ( ! $valid_date ) {
		return new WP_Error( 'invalid_date', __( 'Whoops, the provided date is invalid.', 'atcf' ) );
	}

	if ( mysql2date( 'G', $end_date ) > current_time( 'timestamp' ) ) {
		delete_post_meta( $post->ID, '_campaign_expired' );
	}

	return $end_date;
}

/**
 * Price row head
 *
 * @since Astoundify Crowdfunding 0.9
 *
 * @return void
 */
function atcf_pledge_limit_head() {
?>
	<th style="width: 30px"><?php _e( 'Limit', 'atcf' ); ?></th>
	<th style="width: 30px"><?php _e( 'Backers', 'atcf' ); ?></th>
<?php
}

/**
 * Price row columns
 *
 * @since Astoundify Crowdfunding 0.9
 *
 * @return void
 */
function atcf_pledge_limit_column( $post_id, $key, $args ) {
?>
	<td>
		<input type="text" class="edd_repeatable_name_field" name="edd_variable_prices[<?php echo $key; ?>][limit]" id="edd_variable_prices[<?php echo $key; ?>][limit]" value="<?php echo isset ( $args[ 'limit' ] ) ? $args[ 'limit' ] : null; ?>" style="width:100%" />
	</td>
	<td>
		<input type="text" class="edd_repeatable_name_field" name="edd_variable_prices[<?php echo $key; ?>][bought]" id="edd_variable_prices[<?php echo $key; ?>][bought]" value="<?php echo isset ( $args[ 'bought' ] ) ? $args[ 'bought' ] : null; ?>" style="width:100%" />
	</td>
<?php
}

/**
 * Price row fields
 *
 * @since Astoundify Crowdfunding 0.9
 *
 * @return void
 */
function atcf_price_row_args( $args, $value ) {
	$args[ 'limit' ] = isset( $value[ 'limit' ] ) ? $value[ 'limit' ] : '';
	$args[ 'bought' ] = isset( $value[ 'bought' ] ) ? $value[ 'bought' ] : 0;

	return $args;
}
add_filter( 'edd_price_row_args', 'atcf_price_row_args', 10, 2 );

/**
 * Campaign Stats Box
 *
 * These are read-only stats/info for the current campaign.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @return void
 */
function _atcf_metabox_campaign_stats() {
	global $post;

	$campaign = atcf_get_campaign( $post );

	do_action( 'atcf_metabox_campaign_stats_before', $campaign );
?>
	<p>
		<strong><?php _e( 'Current Amount:', 'atcf' ); ?></strong>
		<?php echo $campaign->current_amount(); ?> &mdash; <?php echo $campaign->percent_completed(); ?>
	</p>

	<p>
		<strong><?php _e( 'Backers:' ,'atcf' ); ?></strong>
		<?php echo $campaign->backers_count(); ?>
	</p>

	<?php if ( ! $campaign->is_endless() ) : ?>
	<p>
		<strong><?php _e( 'Days Remaining:', 'atcf' ); ?></strong>
		<?php echo $campaign->days_remaining(); ?>
	</p>
	<?php endif; ?>
<?php
	do_action( 'atcf_metabox_campaign_stats_after', $campaign );
}

/**
 * Campaign Collect Funds Box
 *
 * If a campaign is fully funded (or expired and fully funded) show this box.
 * Includes a button to collect funds.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @return void
 */
function _atcf_metabox_campaign_funds() {
	global $post;

	$campaign        = atcf_get_campaign( $post );
	$failed_payments = $campaign->failed_payments();
	$count           = 0;

	if ( $failed_payments ) {
		foreach ( $failed_payments as $gateways ) {
			foreach ( $gateways as $gateway ) {
				$count = $count + count( $gateway );
			}
		}
	}

	do_action( 'atcf_metabox_campaign_funds_before', $campaign );
?>
	<?php if ( 'fixed' == $campaign->type() ) : ?>
	<p><?php printf( __( 'This %1$s has reached its funding goal. You may now send the funds to the owner. This will end the %1$s.', 'atcf' ), strtolower( edd_get_label_singular() ) ); ?></p>
	<?php else : ?>
	<p><?php printf( __( 'This %1$s is flexible. You may collect the funds at any time. This will end the %1$s.', 'atcf' ), strtolower( edd_get_label_singular() ) ); ?></p>
	<?php endif; ?>

	<?php if ( $failed_payments ) : ?>
		<p><strong><?php printf( _n( '%d payment failed to process.', '%d payments failed to process.', $count, 'atcf' ), $count ); ?></strong> <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'edd-reports', 'tab' => 'logs', 'view' => 'gateway_errors', 'post_type' => 'download' ), admin_url( 'edit.php' ) ) ); ?>"><?php _e( 'View gateway errors', 'atcf' ); ?></a>.</p>

		<ul>
		<?php foreach ( $failed_payments as $gateway => $payments ) : ?>
			<li><strong><?php echo edd_get_gateway_admin_label( $gateway ); ?></strong>

				<ul>
					<?php foreach ( $payments[ 'payments' ] as $payment ) : ?>
						<li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=edit-payment&purchase_id=' . $payment ) ); ?>">#<?php echo $payment; ?></a></li>
					<?php endforeach; ?>
				</ul>
			</li>
		<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! apply_filters( 'atcf_hide_collect_funds_button', false ) ) : ?>
	<p><a href="<?php echo wp_nonce_url( add_query_arg( array( 'action' => 'atcf-collect-funds', 'campaign' => $campaign->ID ), admin_url() ), 'atcf-collect-funds' ); ?>" class="button button-primary"><?php _e( 'Collect Funds', 'atcf' ); ?></a></p>
	<?php endif; ?>
<?php
	do_action( 'atcf_metabox_campaign_funds_after', $campaign );
}

/**
 * Reinstate Campaign Box
 *
 * If a campaign has collected funds, but wants to run again, show a button.
 * This still requires updating expiration information, etc, and can only done
 * by teh admin.
 *
 * @since Astoundify Crowdfunding 1.5
 *
 * @return void
 */
function _atcf_metabox_campaign_reinstate() {
	global $post;

	$campaign = atcf_get_campaign( $post );
	
	do_action( 'atcf_metabox_campaign_reinstate_before', $campaign );
?>
	<p><a href="<?php echo wp_nonce_url( add_query_arg( array( 'action' => 'atcf-reinstate', 'campaign' => $campaign->ID ), admin_url() ), 'atcf-reinstate' ); ?>" class="button button-primary"><?php _e( 'Reinstate Campaign', 'atcf' ); ?></a></p>

	<p><?php _e( '<strong>Note:</strong> This will only allow funds to be collected again. All dates, etc must manually be updated.', 'atcf' ); ?></p>
<?php
	do_action( 'atcf_metabox_campaign_reinstate_after', $campaign );
}

/**
 * Campaign Video Box
 *
 * oEmbed campaign video.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @return void
 */
function _atcf_metabox_campaign_video() {
	global $post;

	$campaign = atcf_get_campaign( $post );

	do_action( 'atcf_metabox_campaign_video_before', $campaign );
?>
	<input type="text" name="campaign_video" id="campaign_video" class="widefat" value="<?php echo esc_url( $campaign->video() ); ?>" />
	<p class="description"><?php _e( 'oEmbed supported video links.', 'atcf' ); ?></p>
<?php
	do_action( 'atcf_metabox_campaign_video_after', $campaign );
}

/**
 * Campaign Updates Box
 *
 * @since Astoundify Crowdfunding 0.9
 *
 * @return void
 */
function _atcf_metabox_campaign_updates() {
	global $post;

	$campaign = atcf_get_campaign( $post );

	do_action( 'atcf_metabox_campaign_updates_before', $campaign );
?>
	<textarea name="campaign_updates" rows="4" class="widefat"><?php echo esc_textarea( $campaign->updates() ); ?></textarea>
	<p class="description"><?php _e( 'Notes and updates about the campaign.', 'atcf' ); ?></p>
<?php
	do_action( 'atcf_metabox_campaign_updates_after', $campaign );
}

/**
 * Campaign Configuration
 *
 * Hook into EDD Download Information and add a bit more stuff.
 * These are all things that can be updated while the campaign runs/before
 * being published.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @return void
 */
function _atcf_metabox_campaign_info() {
	global $post, $edd_options, $wp_locale;

	/** Verification Field */
	wp_nonce_field( 'cf', 'cf-save' );
	
	$campaign = atcf_get_campaign( $post );

	$end_date = $campaign->end_date();

	if ( ! $end_date && ! $campaign->is_endless() ) {
		$min = isset ( $edd_options[ 'atcf_campaign_length_min' ] ) ? $edd_options[ 'atcf_campaign_length_min' ] : 14;
		$max = isset ( $edd_options[ 'atcf_campaign_length_max' ] ) ? $edd_options[ 'atcf_campaign_length_max' ] : 48;

		$start = apply_filters( 'atcf_shortcode_submit_field_length_start', round( ( $min + $max ) / 2 ) );

		$end_date = date( 'Y-m-d h:i:s', time() + ( $start * 86400 ) );
	}

	$jj = mysql2date( 'd', $end_date );
	$mm = mysql2date( 'm', $end_date );
	$aa = mysql2date( 'Y', $end_date );
	$hh = mysql2date( 'H', $end_date );
	$mn = mysql2date( 'i', $end_date );
	$ss = mysql2date( 's', $end_date );

	do_action( 'atcf_metabox_campaign_info_before', $campaign );

	$types = atcf_campaign_types();
?>	
	<p>
		<label for="_campaign_featured">
			<input type="checkbox" name="_campaign_featured" id="_campaign_featured" value="1" <?php checked( 1, $campaign->featured() ); ?> />
			<?php _e( 'Featured campaign', 'atcf' ); ?>
		</label>
	</p>

	<p>
		<label for="_campaign_physical">
			<input type="checkbox" name="_campaign_physical" id="_campaign_physical" value="1" <?php checked( 1, $campaign->needs_shipping() ); ?> />
			<?php _e( 'Collect shipping information on checkout', 'atcf' ); ?>
		</label>
	</p>
	
	<p>
		<strong><?php _e( 'Funding Type:', 'atcf' ); ?></strong>
	</p>

	<p>
		<?php foreach ( atcf_campaign_types_active() as $key => $desc ) : ?>
		<label for="campaign_type[<?php echo esc_attr( $key ); ?>]"><input type="radio" name="campaign_type" id="campaign_type[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, $campaign->type() ); ?> /> <strong><?php echo $types[ $key ][ 'title' ]; ?></strong> &mdash; <?php echo $types[ $key ][ 'description' ]; ?></label><br />
		<?php endforeach; ?>
	</p>

	<p>
		<label for="campaign_goal"><strong><?php _e( 'Goal:', 'atcf' ); ?></strong></label><br />	
		<?php if ( ! isset( $edd_options[ 'currency_position' ] ) || $edd_options[ 'currency_position' ] == 'before' ) : ?>
			<?php echo edd_currency_filter( '' ); ?><input type="text" name="campaign_goal" id="campaign_goal" value="<?php echo edd_format_amount( $campaign->goal(false) ); ?>" style="width:80px" />
		<?php else : ?>
			<input type="text" name="campaign_goal" id="campaign_goal" value="<?php echo edd_format_amount($campaign->goal(false) ); ?>" style="width:80px" /><?php echo edd_currency_filter( '' ); ?>
		<?php endif; ?>
	</p>

	<p>
		<label for="campaign_location"><strong><?php _e( 'Location:', 'atcf' ); ?></strong></label><br />
		<input type="text" name="campaign_location" id="campaign_location" value="<?php echo esc_attr( $campaign->location() ); ?>" class="regular-text" />
	</p>

	<p>
		<label for="campaign_author"><strong><?php _e( 'Author:', 'atcf' ); ?></strong></label><br />
		<input type="text" name="campaign_author" id="campaign_author" value="<?php echo esc_attr( $campaign->author() ); ?>" class="regular-text" />
	</p>

	<p>
		<label for="campaign_email"><strong><?php _e( 'Contact Email:', 'atcf' ); ?></strong></label><br />
		<input type="text" name="campaign_contact_email" id="campaign_contact_email" value="<?php echo esc_attr( $campaign->contact_email() ); ?>" class="regular-text" />
	</p>

	<style>#end-aa { width: 3.4em } #end-jj, #end-hh, #end-mn { width: 2em; }</style>

	<p>
		<strong><?php _e( 'End Date:', 'atcf' ); ?></strong><br />

		<select id="end-mm" name="end-mm">
			<?php for ( $i = 1; $i < 13; $i = $i + 1 ) : $monthnum = zeroise($i, 2); ?>
				<option value="<?php echo $monthnum; ?>" <?php selected( $monthnum, $mm ); ?>>
				<?php printf( '%1$s-%2$s', $monthnum, $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) ); ?>
				</option>
			<?php endfor; ?>
		</select>

		<input type="text" id="end-jj" name="end-jj" value="<?php echo esc_attr( $jj ); ?>" size="2" maxlength="2" autocomplete="off" />, 
		<input type="text" id="end-aa" name="end-aa" value="<?php echo esc_attr( $aa ); ?>" size="4" maxlength="4" autocomplete="off" /> @
		<input type="text" id="end-hh" name="end-hh" value="<?php echo esc_attr( $hh ); ?>" size="2" maxlength="2" autocomplete="off" /> :
		<input type="text" id="end-mn" name="end-mn" value="<?php echo esc_attr( $mn ); ?>" size="2" maxlength="2" autocomplete="off" />
		<input type="hidden" id="end-ss" name="end-ss" value="<?php echo esc_attr( $ss ); ?>" />
		<input type="hidden" id="campaign_end_date" name="campaign_end_date" value="1" />
	</p>
	
	<p>
		<label for="campaign_endless">
			<input type="checkbox" name="campaign_endless" id="campaign_endless" value="1" <?php checked( 1, $campaign->is_endless() ); ?>> <?php printf( __( 'This %s never ends', 'atcf' ), strtolower( edd_get_label_singular() ) ); ?>
		</label>
	</p>
<?php
	do_action( 'atcf_metabox_campaign_info_after', $campaign );
}

function atcf_after_price_field() {
	global $post;

	$campaign = atcf_get_campaign( $post );
?>
	<p>
		<label for="campaign_norewards">
			<input type="checkbox" name="campaign_norewards" id="campaign_norewards" value="1" <?php checked( 1, $campaign->is_donations_only() ); ?>> <?php printf( __( 'This %s is donations only (no rewards)', 'atcf' ), strtolower( edd_get_label_singular() ) ); ?>
		</label>
	</p>
<?php
}

/**
 * Goal Save
 *
 * Sanitize goal before it is saved, to remove commas.
 *
 * @since Astoundify Crowdfunding 0.1-alpha
 *
 * @return string $price The formatted price
 */
add_filter( 'edd_metabox_save_campaign_goal', 'edd_sanitize_price_save' );

/**
 * Updates Save
 *
 * EDD trys to escape this data, and we don't want that.
 *
 * @since Astoundify Crowdfunding 0.9
 */
function atcf_sanitize_campaign_updates( $updates ) {
	$updates = $_POST[ 'campaign_updates' ];
	$updates = wp_kses_post( $updates );

	return $updates;
}
add_filter( 'edd_metabox_save_campaign_updates', 'atcf_sanitize_campaign_updates' );

/**
 * Updates Save
 *
 * EDD trys to escape this data, and we don't want that.
 *
 * @since Astoundify Crowdfunding 0.9
 */
function atcf_save_variable_prices_norewards( $prices ) {
	$norewards = isset ( $_POST[ 'campaign_norewards' ] ) ? true : false;

	if ( ! $norewards )
		return $prices;

	if ( $prices[0][ 'name' ] != '' )
		return $prices;

	$prices = array();

	$prices[0] = array(
		'name'   => apply_filters( 'atcf_default_no_rewards_name', __( 'Donation', 'atcf' ) ),
		'amount' => apply_filters( 'atcf_default_no_rewards_price', 0 ),
		'limit'  => null,
		'bought' => 0
	);

	return $prices;
}
add_filter( 'edd_metabox_save_edd_variable_prices', 'atcf_save_variable_prices_norewards' );

/**
 * Load Admin Scripts
 *
 * @since Astoundify Crowdfunding 1.3
 *
 * @return void
 */
function atcf_load_admin_scripts( $hook ) {
	global $pagenow, $typenow;
 
 	if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) )
 		return;

 	if ( 'download' != $typenow )
 		return;

 	$crowdfunding = crowdfunding();

	wp_enqueue_script( 'atcf-admin-scripts', $crowdfunding->plugin_url . '/assets/js/crowdfunding-admin.js', array( 'jquery', 'edd-admin-scripts' ) );
}
add_action( 'admin_enqueue_scripts', 'atcf_load_admin_scripts' );

function atcf_check_for_completed_campaigns() {
	$now = current_time( 'timestamp', true );

	$active_campaigns = get_posts( array(
		'post_type'             => array( 'download' ),
		'post_status'           => array( 'publish' ),
		'meta_query'            => array(
			array(
				'key'     => '_campaign_expired',
				'compare' => 'NOT EXISTS'
			)
		),
		'nopaging'               => true,
		'cache_results'          => false,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false
	) );

	$processing = get_option( 'atcf_processing' );
	
	foreach ( $active_campaigns as $campaign ) {
		$campaign = atcf_get_campaign( $campaign );

		$expiration_date = mysql2date( 'G', $campaign->end_date() );

		if ( $now > $expiration_date ) {
			update_post_meta( $campaign->ID, '_campaign_expired', current_time( 'mysql' ) );

			if ( ! in_array( $campaign->ID, $processing ) ) {
				$processing[] = $campaign->ID;
			}

			do_action( 'atcf_campaign_expired', $campaign );
		} else if ( $now < $expiration_date && get_post_meta( $campaign->ID, '_campaign_expired', true ) ) {
			delete_post_meta( $campaign->ID, '_campaign_expired' );
		}
	}

	update_option( 'atcf_processing', $processing );
}
add_action( 'atcf_check_for_completed_campaigns', 'atcf_check_for_completed_campaigns' );