(function ($) {
	'use strict';

	/* ─── Preview modal ─────────────────────────────── */

	var overlay = $('#wpjs-preview-overlay');
	var content = $('#wpjs-preview-content');
	var title   = $('#wpjs-preview-title');

	$(document).on('click', '.wpjs-preview-btn', function () {
		var postId = $(this).data('post-id');
		content.text('Loading...');
		title.text('Markdown Preview');
		overlay.css('display', 'flex');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_preview',
			_wpnonce: wpjs.nonce,
			post_id:  postId
		}, function (res) {
			if (!res.success) {
				content.text('Error: ' + res.data);
				return;
			}
			title.text(res.data.filename);
			content.text(res.data.markdown);
		}).fail(function () {
			content.text('Request failed.');
		});
	});

	$('#wpjs-preview-close').on('click', function () {
		overlay.hide();
	});

	overlay.on('click', function (e) {
		if (e.target === this) {
			overlay.hide();
		}
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			overlay.hide();
		}
	});

	/* ─── Auto-push toggle ──────────────────────────── */

	$('#wpjs-auto-push').on('change', function () {
		var enabled = $(this).is(':checked') ? '1' : '0';
		$.post(wpjs.ajax_url, {
			action:   'wpjs_toggle_auto_push',
			_wpnonce: wpjs.nonce,
			enabled:  enabled
		});
	});

})(jQuery);
