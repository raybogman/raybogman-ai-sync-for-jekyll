(function ($) {
	'use strict';

	/* ─── Preview modal ─────────────────────────────── */

	var overlay = $('#wpjs-preview-overlay');
	var content = $('#wpjs-preview-content');
	var title   = $('#wpjs-preview-title');

	$(document).on('click', '.wpjs-preview-btn', function () {
		var postId = $(this).data('post-id');
		content.html('Loading...');
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

	/* ─── Diff modal ────────────────────────────────── */

	$(document).on('click', '.wpjs-diff-btn', function () {
		var postId = $(this).data('post-id');
		content.html('Loading diff...');
		title.text('Diff View');
		overlay.css('display', 'flex');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_diff',
			_wpnonce: wpjs.nonce,
			post_id:  postId
		}, function (res) {
			if (!res.success) {
				content.html('<span style="color:#d63638;">Error: ' + $('<span>').text(res.data).html() + '</span>');
				return;
			}
			title.text('Diff: ' + res.data.filename);
			content.html(res.data.html);
		}).fail(function () {
			content.html('<span style="color:#d63638;">Request failed.</span>');
		});
	});

	/* ─── Modal close ───────────────────────────────── */

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

	/* ─── AI Generate ───────────────────────────────── */

	$(document).on('click', '.wpjs-ai-btn', function () {
		var btn    = $(this);
		var postId = btn.data('post-id');
		var orig   = btn.text();
		btn.prop('disabled', true).text('Generating...');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_generate_ai',
			_wpnonce: wpjs.nonce,
			post_id:  postId
		}, function (res) {
			if (!res.success) {
				btn.prop('disabled', false).text(orig);
				alert('AI failed: ' + res.data);
				return;
			}
			btn.text('Done').css('color', '#00a32a');
			setTimeout(function () {
				btn.prop('disabled', false).text(orig).css('color', '');
			}, 3000);
			// Show summary in a tooltip-style popup.
			var tip = $('<span style="position:absolute;background:#1d2327;color:#fff;padding:6px 10px;border-radius:4px;font-size:12px;z-index:9999;white-space:nowrap;"></span>');
			tip.text(res.data.summary);
			btn.parent().css('position', 'relative').append(tip);
			tip.css({ bottom: '100%', left: 0, marginBottom: '4px' });
			setTimeout(function () { tip.fadeOut(function () { tip.remove(); }); }, 4000);
		}).fail(function () {
			btn.prop('disabled', false).text(orig);
		});
	});

	/* ─── Clear log ─────────────────────────────────── */

	$(document).on('click', '#wpjs-clear-log', function () {
		var btn = $(this);
		btn.prop('disabled', true).text('Clearing...');
		$.post(wpjs.ajax_url, {
			action:   'wpjs_clear_log',
			_wpnonce: wpjs.nonce
		}, function () {
			location.reload();
		}).fail(function () {
			btn.prop('disabled', false).text('Clear Log');
		});
	});

	/* ─── AI Validate (per provider) ────────────────── */

	$(document).on('click', '.wpjs-validate-ai-btn', function () {
		var btn      = $(this);
		var provider = btn.data('provider');
		var status   = $('.wpjs-ai-validate-status[data-provider="' + provider + '"]');
		status.text('Validating...');
		$.post(wpjs.ajax_url, {
			action:   'wpjs_validate_ai',
			_wpnonce: wpjs.nonce,
			provider: provider
		}, function (res) {
			if (!res.success) {
				status.html('<span style="color:#d63638;">Failed: ' + $('<span>').text(res.data).html() + '</span>');
			} else {
				status.html('<span style="color:#00a32a;">Connected (' + $('<span>').text(res.data.model).html() + ')</span>');
			}
		}).fail(function () {
			status.html('<span style="color:#d63638;">Request failed.</span>');
		});
	});

	/* ─── Pull from Jekyll ──────────────────────────── */

	$('#wpjs-load-jekyll-posts').on('click', function () {
		var spinner = $('#wpjs-pull-spinner');
		var status  = $('#wpjs-pull-status');
		var list    = $('#wpjs-pull-list');
		var tbody   = $('#wpjs-pull-table tbody');
		spinner.addClass('is-active');
		status.text('Loading...');
		tbody.html('');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_list_jekyll_posts',
			_wpnonce: wpjs.nonce
		}, function (res) {
			spinner.removeClass('is-active');
			if (!res.success) {
				status.text('Error: ' + res.data);
				return;
			}
			status.text(res.data.length + ' files found.');
			list.show();
			$.each(res.data, function (i, f) {
				var wpCol = f.wp_exists
					? '<span style="color:#00a32a;">Yes</span> — <a href="post.php?post=' + f.wp_id + '&action=edit">' + $('<span>').text(f.wp_title).html() + '</a>'
					: '<span style="color:#d63638;">No</span>';
				var btn = '<button type="button" class="button wpjs-pull-btn" data-path="' + $('<span>').text(f.path).html() + '">' + (f.wp_exists ? 'Update' : 'Import') + '</button>';
				tbody.append('<tr><td><code>' + $('<span>').text(f.name).html() + '</code></td><td>' + $('<span>').text(f.slug).html() + '</td><td>' + wpCol + '</td><td>' + btn + '</td></tr>');
			});
		}).fail(function () {
			spinner.removeClass('is-active');
			status.text('Request failed.');
		});
	});

	var pullCount = 0;

	$(document).on('click', '.wpjs-pull-btn', function () {
		var btn  = $(this);
		var path = btn.data('path');
		btn.prop('disabled', true).text('Pulling...');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_pull_post',
			_wpnonce: wpjs.nonce,
			path:     path
		}, function (res) {
			if (!res.success) {
				btn.prop('disabled', false).text('Error');
				alert('Pull failed: ' + res.data);
				return;
			}
			pullCount++;
			btn.text('Done').css('color', '#00a32a');
			var td = btn.closest('tr').find('td:eq(2)');
			td.html('<span style="color:#00a32a;">Yes</span> — <a href="' + res.data.edit_url + '">Edit</a>');
			updatePullSummary();
		}).fail(function () {
			btn.prop('disabled', false).text('Error');
		});
	});

	// Import All New — clicks all Import buttons sequentially.
	$(document).on('click', '#wpjs-pull-all', function () {
		var btn = $(this);
		var imports = $('#wpjs-pull-table .wpjs-pull-btn').filter(function () {
			return $(this).text() === 'Import';
		});
		if (!imports.length) {
			alert('No new posts to import.');
			return;
		}
		btn.prop('disabled', true).text('Importing ' + imports.length + '...');
		var queue = imports.toArray();
		function next() {
			if (!queue.length) {
				btn.text('Done').css('color', '#00a32a');
				return;
			}
			$(queue.shift()).trigger('click');
			setTimeout(next, 1500);
		}
		next();
	});

	function updatePullSummary() {
		var summary = $('#wpjs-pull-summary');
		var text    = $('#wpjs-pull-summary-text');
		summary.show();
		text.text('Imported/updated ' + pullCount + ' post(s) from Jekyll.');
	}

})(jQuery);
