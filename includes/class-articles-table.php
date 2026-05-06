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
			'status'    => 'Jekyll',
			'approved'  => 'Approved',
			'last_push' => 'Pushed',
			'actions'   => 'Actions',
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

	public function column_status( $post ) {
		$last_push = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		if ( ! $last_push ) {
			return '<span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#d63638;"></span> Not synced</span>';
		}
		$push_time = strtotime( $last_push );
		$mod_time  = strtotime( $post->post_modified_gmt );
		if ( $mod_time > $push_time ) {
			return '<span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#dba617;"></span> Outdated</span>';
		}
		return '<span style="display:inline-flex;align-items:center;gap:4px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#00a32a;"></span> Synced</span>';
	}

	public function column_approved( $post ) {
		$approved = WPJS_Publisher::is_approved( $post->ID );
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpjs_toggle_approve&post_id=' . $post->ID ),
			'wpjs_toggle_approve_' . $post->ID
		);
		if ( $approved ) {
			return sprintf( '<a href="%s" title="Click to unapprove"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;font-size:20px;width:20px;height:20px;"></span></a>', esc_url( $url ) );
		}
		return sprintf( '<a href="%s" title="Click to approve"><span class="dashicons dashicons-marker" style="color:#c3c4c7;font-size:20px;width:20px;height:20px;"></span></a>', esc_url( $url ) );
	}

	public function column_last_push( $post ) {
		$t = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		if ( ! $t ) { return '<span class="description">—</span>'; }
		$time = strtotime( $t );
		$ago  = human_time_diff( $time, current_time( 'timestamp' ) );
		return '<span title="' . esc_attr( $t ) . '">' . esc_html( $ago ) . ' ago</span>';
	}

	public function column_actions( $post ) {
		$is_pushed = (bool) get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		$buttons   = array();

		if ( $post->post_status === 'publish' ) {
			// Push.
			$push_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=wpjs_publish_one&post_id=' . $post->ID ),
				'wpjs_publish_one_' . $post->ID
			);
			$push_icon  = $is_pushed ? 'dashicons-update' : 'dashicons-cloud-upload';
			$push_title = $is_pushed ? 'Re-push to Jekyll' : 'Push to Jekyll';
			$buttons[] = sprintf(
				'<a href="%s" class="button" title="%s" style="padding:0 6px;min-width:auto;"><span class="dashicons %s" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></a>',
				esc_url( $push_url ), esc_attr( $push_title ), esc_attr( $push_icon )
			);

			// Preview.
			$buttons[] = sprintf(
				'<button type="button" class="button wpjs-preview-btn" data-post-id="%d" title="Preview Markdown" style="padding:0 6px;min-width:auto;"><span class="dashicons dashicons-visibility" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button>',
				$post->ID
			);

			// AI.
			if ( WPJS_AI_Client::is_available() ) {
				$buttons[] = sprintf(
					'<button type="button" class="button wpjs-ai-btn" data-post-id="%d" title="Generate AI description &amp; alt text" style="padding:0 6px;min-width:auto;"><span class="dashicons dashicons-superhero-alt" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button>',
					$post->ID
				);
			}

			// Diff (only for pushed).
			if ( $is_pushed ) {
				$buttons[] = sprintf(
					'<button type="button" class="button wpjs-diff-btn" data-post-id="%d" title="Diff with Jekyll" style="padding:0 6px;min-width:auto;"><span class="dashicons dashicons-editor-code" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></button>',
					$post->ID
				);
			}
		} else {
			$buttons[] = '<span class="description" style="font-size:12px;">Draft</span>';
		}

		// Delete (only for pushed).
		if ( $is_pushed ) {
			$del_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=wpjs_delete_one&post_id=' . $post->ID ),
				'wpjs_delete_one_' . $post->ID
			);
			$buttons[] = sprintf(
				'<a href="%s" class="button" title="Delete from Jekyll" style="padding:0 6px;min-width:auto;color:#d63638;" onclick="return confirm(\'Delete from Jekyll?\');"><span class="dashicons dashicons-trash" style="font-size:16px;width:16px;height:16px;line-height:28px;"></span></a>',
				esc_url( $del_url )
			);
		}

		return '<div style="display:flex;gap:2px;align-items:center;">' . implode( '', $buttons ) . '</div>';
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
