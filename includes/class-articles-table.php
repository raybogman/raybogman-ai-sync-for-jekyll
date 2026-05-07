<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class WPJS_Articles_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'article',
			'plural'   => 'articles',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'title'     => 'Title',
			'actions'   => 'Actions',
			'status'    => 'Status',
			'last_push' => 'Synced',
		);
	}

	protected function get_bulk_actions() {
		$actions = array(
			'bulk_approve'   => 'Approve',
			'bulk_unapprove' => 'Unapprove',
			'bulk_push'      => 'Push to Jekyll',
			'bulk_delete'    => 'Delete from Jekyll',
		);
		if ( WPJS_AI_Client::is_available() ) {
			$actions['bulk_ai'] = 'Generate AI Metadata';
		}
		return $actions;
	}

	public function column_cb( $post ) {
		return sprintf( '<input type="checkbox" name="post_ids[]" value="%d" />', $post->ID );
	}

	public function prepare_items() {
		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Pagination/filter params, no data processing.
		$paged    = max( 1, absint( wp_unslash( $_GET['paged'] ?? 1 ) ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search   = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter   = sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ?? '' ) );

		$types = array();
		if ( $filter ) {
			$types = array( $filter );
		} else {
			if ( WPJS_Settings::get( 'sync_posts', '1' ) === '1' ) { $types[] = 'post'; }
			if ( WPJS_Settings::get( 'sync_pages', '1' ) === '1' ) { $types[] = 'page'; }
			if ( empty( $types ) ) { $types = array( 'post', 'page' ); }
		}

		$args = array(
			'post_type'      => $types,
			'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			's'              => $search,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$q = new WP_Query( $args );
		$this->items = $q->posts;

		$this->set_pagination_args( array(
			'total_items' => $q->found_posts,
			'per_page'    => $per_page,
			'total_pages' => $q->max_num_pages,
		) );

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	public function column_title( $post ) {
		$edit   = get_edit_post_link( $post->ID );
		$title  = esc_html( $post->post_title ?: '(no title)' );
		$img    = has_post_thumbnail( $post->ID )
			? '<span class="dashicons dashicons-format-image" style="color:#00a32a;font-size:14px;width:14px;height:14px;margin-right:4px;" title="Has featured image"></span>'
			: '';
		$author = esc_html( get_the_author_meta( 'display_name', $post->post_author ) );
		$date   = esc_html( get_the_date( 'M j, Y', $post ) );
		$type   = esc_html( $post->post_type );

		return sprintf(
			'<span style="display:inline-flex;align-items:center;">%s<strong><a href="%s">%s</a></strong></span><br><span class="description">%s &middot; %s &middot; %s</span>',
			$img, esc_url( $edit ), $title, $type, $author, $date
		);
	}

	public function column_actions( $post ) {
		$is_pushed = (bool) get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		$links     = array();

		if ( $post->post_status === 'publish' ) {
			$push_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=wpjs_publish_one&post_id=' . $post->ID ),
				'wpjs_publish_one_' . $post->ID
			);
			$links[] = sprintf( '<a href="%s">%s</a>', esc_url( $push_url ), $is_pushed ? 'Re-push' : 'Push' );
			$links[] = sprintf( '<a href="#" class="wpjs-preview-btn" data-post-id="%d">Preview</a>', $post->ID );
			$links[] = sprintf( '<a href="#" class="wpjs-ai-btn" data-post-id="%d">AI</a>', $post->ID );
			if ( $is_pushed ) {
				$links[] = sprintf( '<a href="#" class="wpjs-diff-btn" data-post-id="%d">Diff</a>', $post->ID );
				$del_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=wpjs_delete_one&post_id=' . $post->ID ),
					'wpjs_delete_one_' . $post->ID
				);
				$links[] = sprintf( '<a href="%s" style="color:#d63638;" onclick="return confirm(\'Delete from Jekyll?\');">Delete</a>', esc_url( $del_url ) );
			}
		} else {
			$links[] = '<span class="description">Draft</span>';
		}

		return implode( ' | ', $links );
	}

	public function column_status( $post ) {
		$last_push = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		$approved  = WPJS_Publisher::is_approved( $post->ID );
		$approve_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpjs_toggle_approve&post_id=' . $post->ID ),
			'wpjs_toggle_approve_' . $post->ID
		);

		// Sync status.
		if ( ! $last_push ) {
			$dot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#d63638;"></span>';
			$label = 'Not synced';
		} elseif ( strtotime( $post->post_modified_gmt ) > strtotime( $last_push ) ) {
			$dot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#dba617;"></span>';
			$label = 'Outdated';
		} else {
			$dot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#00a32a;"></span>';
			$label = 'Synced';
		}

		// Approve toggle.
		if ( $approved ) {
			$approve = sprintf( '<a href="%s" title="Click to unapprove" style="text-decoration:none;color:#00a32a;">✓ Ready</a>', esc_url( $approve_url ) );
		} else {
			$approve = sprintf( '<a href="%s" title="Click to approve" style="text-decoration:none;color:#9ca1a7;">○ Queued</a>', esc_url( $approve_url ) );
		}

		return sprintf( '<span style="display:inline-flex;align-items:center;gap:4px;">%s %s</span><br>%s', $dot, $label, $approve );
	}

	public function column_last_push( $post ) {
		$t = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		if ( ! $t ) { return '<span class="description">—</span>'; }
		$time = strtotime( $t );
		$ago  = human_time_diff( $time, current_time( 'timestamp' ) );
		return '<span title="' . esc_attr( $t ) . '">' . esc_html( $ago ) . ' ago</span>';
	}



	protected function extra_tablenav( $which ) {
		if ( $which !== 'top' ) { return; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filter display, no data processing.
		$current = sanitize_text_field( wp_unslash( $_GET['post_type_filter'] ?? '' ) );
		?>
		<div class="alignleft actions">
			<select name="post_type_filter">
				<option value="">All types</option>
				<option value="post" <?php selected( $current, 'post' ); ?>>Posts</option>
				<option value="page" <?php selected( $current, 'page' ); ?>>Pages</option>
			</select>
			<?php submit_button( 'Filter', '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
