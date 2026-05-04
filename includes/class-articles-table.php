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
			'author'    => 'Author',
			'type'      => 'Type',
			'date'      => 'Date',
			'status'    => 'Jekyll Status',
			'approved'  => 'Approved',
			'last_push' => 'Last pushed',
			'actions'   => 'Actions',
		);
	}

	protected function get_bulk_actions() {
		return array(
			'bulk_approve'   => 'Approve',
			'bulk_unapprove' => 'Unapprove',
			'bulk_push'      => 'Push to Jekyll',
			'bulk_delete'    => 'Delete from Jekyll',
		);
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
		$edit  = get_edit_post_link( $post->ID );
		$title = esc_html( $post->post_title ?: '(no title)' );
		$img   = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$img = '<span class="dashicons dashicons-format-image" style="color:#00a32a;font-size:14px;width:14px;height:14px;margin-right:4px;" title="Has featured image"></span>';
		}
		return sprintf(
			'<span style="display:inline-flex;align-items:center;">%s<strong><a href="%s">%s</a></strong></span>',
			$img, esc_url( $edit ), $title
		);
	}

	public function column_author( $post ) {
		return esc_html( get_the_author_meta( 'display_name', $post->post_author ) );
	}

	public function column_type( $post ) {
		return esc_html( $post->post_type );
	}

	public function column_date( $post ) {
		return esc_html( get_the_date( 'Y-m-d', $post ) );
	}

	public function column_status( $post ) {
		$last_push = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		if ( ! $last_push ) {
			return '<span style="color:#d63638;font-weight:600;">Not published</span>';
		}
		$push_time = strtotime( $last_push );
		$mod_time  = strtotime( $post->post_modified_gmt );
		if ( $mod_time > $push_time ) {
			return '<span style="color:#dba617;font-weight:600;">Outdated</span>';
		}
		return '<span style="color:#00a32a;font-weight:600;">Published</span>';
	}

	public function column_approved( $post ) {
		$approved = WPJS_Publisher::is_approved( $post->ID );
		$label    = $approved ? 'Approved' : 'Not approved';
		$class    = $approved ? 'button-primary' : 'button-secondary';
		$dot      = $approved
			? '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#00a32a;margin-right:6px;vertical-align:middle;"></span>'
			: '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dcdcde;margin-right:6px;vertical-align:middle;"></span>';
		// Use link instead of nested form.
		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpjs_toggle_approve&post_id=' . $post->ID ),
			'wpjs_toggle_approve_' . $post->ID
		);
		return sprintf( '<a href="%s" class="button %s">%s%s</a>', esc_url( $url ), esc_attr( $class ), $dot, esc_html( $label ) );
	}

	public function column_last_push( $post ) {
		$t = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		return $t ? esc_html( $t ) : '—';
	}

	public function column_actions( $post ) {
		$is_pushed = (bool) get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		$buttons   = array();

		if ( $post->post_status === 'publish' ) {
			$push_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=wpjs_publish_one&post_id=' . $post->ID ),
				'wpjs_publish_one_' . $post->ID
			);
			$buttons[] = sprintf( '<a href="%s" class="button">%s</a>', esc_url( $push_url ), $is_pushed ? 'Re-push' : 'Push now' );
			$buttons[] = sprintf( '<button type="button" class="button wpjs-preview-btn" data-post-id="%d">Preview</button>', $post->ID );
		} else {
			$buttons[] = '<em>publish to enable</em>';
		}

		if ( $is_pushed ) {
			$del_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=wpjs_delete_one&post_id=' . $post->ID ),
				'wpjs_delete_one_' . $post->ID
			);
			$buttons[] = sprintf(
				'<a href="%s" class="button" style="color:#d63638;" onclick="return confirm(\'Delete this file from Jekyll?\');">Delete</a>',
				esc_url( $del_url )
			);
		}

		return '<div style="display:flex;gap:4px;flex-wrap:nowrap;align-items:center;">' . implode( '', $buttons ) . '</div>';
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
