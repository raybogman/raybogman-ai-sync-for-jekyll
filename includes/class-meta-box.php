<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'admin_post_wpjs_push', array( $this, 'handle_push' ) );
	}

	public function register() {
		foreach ( array( 'post', 'page' ) as $type ) {
			add_meta_box( 'wpjs_push_box', 'Jekyll Sync', array( $this, 'render' ), $type, 'side', 'default' );
		}
	}

	public function render( WP_Post $post ) {
		$last     = get_post_meta( $post->ID, WPJS_Publisher::META_LAST_PUSH, true );
		$approved = WPJS_Publisher::is_approved( $post->ID );
		$url      = admin_url( 'admin-post.php' );
		?>
		<p><?php echo $last ? 'Last pushed: ' . esc_html( $last ) : 'Not yet pushed.'; ?></p>
		<p><?php echo $approved ? '✅ Approved for Jekyll' : '⬜ Not approved'; ?></p>
		<form method="post" action="<?php echo esc_url( $url ); ?>">
			<input type="hidden" name="action" value="wpjs_push" />
			<input type="hidden" name="post_id" value="<?php echo (int) $post->ID; ?>" />
			<?php wp_nonce_field( 'wpjs_push_' . $post->ID, 'wpjs_nonce' ); ?>
			<button type="submit" class="button button-primary" <?php echo $post->post_status === 'publish' ? '' : 'disabled'; ?>>
				Push to Jekyll
			</button>
			<?php if ( $post->post_status !== 'publish' ) : ?>
				<p class="description">Publish the post first.</p>
			<?php endif; ?>
		</form>
		<p style="margin-top:8px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-articles' ) ); ?>">Manage approvals →</a>
		</p>
		<?php
	}

	public function handle_push() {
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_die( 'Permission denied.' ); }
		if ( ! isset( $_POST['wpjs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpjs_nonce'] ) ), 'wpjs_push_' . $post_id ) ) {
			wp_die( 'Invalid nonce.' );
		}
		$post = get_post( $post_id );
		if ( ! $post ) { wp_die( 'Post not found.' ); }

		$result = WPJS_Publisher::publish( $post );
		$key    = 'wpjs_notice_' . get_current_user_id();
		if ( is_wp_error( $result ) ) {
			set_transient( $key, array( 'type' => 'error', 'message' => $result->get_error_message() ), 60 );
		} else {
			set_transient( $key, array( 'type' => 'success', 'message' => 'Pushed to ' . esc_html( $result ) ), 60 );
		}
		wp_safe_redirect( get_edit_post_link( $post_id, 'raw' ) );
		exit;
	}
}
