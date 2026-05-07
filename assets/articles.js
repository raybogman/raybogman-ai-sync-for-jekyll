(function ($) {
	'use strict';

	/* ─── Preview modal ─────────────────────────────── */

	var overlay = $('#wpjs-preview-overlay');
	var content = $('#wpjs-preview-content');
	var title   = $('#wpjs-preview-title');

	$(document).on('click', '.wpjs-preview-btn', function (e) {
		e.preventDefault();
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

	$(document).on('click', '.wpjs-diff-btn', function (e) {
		e.preventDefault();
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

	$(document).on('click', '.wpjs-ai-btn', function (e) {
		e.preventDefault();
		var btn    = $(this);
		var postId = btn.data('post-id');
		var row    = btn.closest('tr');
		var cols   = row.find('td').length;

		// Remove any existing AI panel for this row.
		row.next('.wpjs-ai-panel').remove();
		btn.text('...').prop('disabled', true);

		$.post(wpjs.ajax_url, {
			action:   'wpjs_generate_ai',
			_wpnonce: wpjs.nonce,
			post_id:  postId
		}, function (res) {
			btn.text('AI').prop('disabled', false);
			if (!res.success) { alert('Failed: ' + res.data); return; }
			var d = res.data;
			var hasAI = d.ai_available;
			var html = '<tr class="wpjs-ai-panel"><td colspan="' + cols + '" style="background:#f8f9fa;padding:12px 16px;border-left:3px solid #2271b1;">';

			// Description section.
			var srcLabels = { ai: 'AI generated', seo: 'from SEO plugin', excerpt: 'from excerpt' };
			var src = srcLabels[d.description_source] ? ' <em style="color:#666;">(' + srcLabels[d.description_source] + ')</em>' : '';
			html += '<div style="margin-bottom:8px;"><strong>Description</strong>' + src + '</div>';
			html += '<div style="display:flex;gap:8px;align-items:flex-start;">';
			html += '<textarea class="wpjs-ai-desc-input" data-post-id="' + postId + '" style="flex:1;padding:6px 8px;min-height:50px;resize:vertical;" maxlength="160">' + escHtml(d.description || '') + '</textarea>';
			html += '<div style="display:flex;flex-direction:column;gap:4px;">';
			if (hasAI) {
				html += '<button type="button" class="button wpjs-ai-regen-desc" data-post-id="' + postId + '" title="Generate with AI">Generate</button>';
			}
			html += '<button type="button" class="button button-primary wpjs-ai-save-desc" data-post-id="' + postId + '">Save</button>';
			html += '</div></div>';
			html += '<p class="description" style="margin:4px 0 0;"><span class="wpjs-ai-desc-count">' + (d.description || '').length + '</span>/160 characters</p>';

			// Images section.
			if (d.images && d.images.length) {
				html += '<div style="margin-top:16px;border-top:1px solid #dcdcde;padding-top:12px;"><strong>Image Alt Text</strong> (' + d.images.length + ')</div>';
				$.each(d.images, function (i, img) {
					var badge = img.featured ? ' <span style="background:#2271b1;color:#fff;font-size:10px;padding:2px 6px;border-radius:2px;vertical-align:middle;">featured</span>' : '';
					var srcLabel = img.source === 'ai' ? ' <em style="color:#2271b1;">(AI)</em>' : img.source === 'none' ? ' <em style="color:#999;">(empty)</em>' : '';
					html += '<div style="margin-top:10px;"><span class="description">' + escHtml(img.filename) + '</span>' + badge + srcLabel + '</div>';
					html += '<div style="display:flex;gap:8px;align-items:center;margin-top:4px;">';
					html += '<input type="text" class="wpjs-ai-alt-input" data-att-id="' + img.id + '" value="' + escAttr(img.alt || '') + '" style="flex:1;padding:4px 8px;" maxlength="125" placeholder="Enter alt text..." />';
					if (hasAI) {
						html += '<button type="button" class="button wpjs-ai-regen-alt" data-att-id="' + img.id + '" title="Generate with AI">Generate</button>';
					}
					html += '<button type="button" class="button wpjs-ai-save-alt" data-att-id="' + img.id + '">Save</button>';
					html += '</div>';
				});
			}

			html += '<div style="margin-top:12px;border-top:1px solid #dcdcde;padding-top:12px;"><button type="button" class="button wpjs-ai-close">Close</button></div>';
			html += '</td></tr>';
			row.after(html);
		}).fail(function () {
			btn.text('AI').prop('disabled', false);
		});
	});

	// Character count for description textarea.
	$(document).on('input', '.wpjs-ai-desc-input', function () {
		var len = $(this).val().length;
		var counter = $(this).closest('td').find('.wpjs-ai-desc-count');
		counter.text(len);
		counter.css('color', len > 160 ? '#d63638' : '');
	});

	// Close AI panel.
	$(document).on('click', '.wpjs-ai-close', function () {
		$(this).closest('.wpjs-ai-panel').remove();
	});

	// Regenerate description.
	$(document).on('click', '.wpjs-ai-regen-desc', function () {
		var btn = $(this);
		var postId = btn.data('post-id');
		var input = btn.closest('td').find('.wpjs-ai-desc-input');
		btn.prop('disabled', true).text('...');
		$.post(wpjs.ajax_url, { action: 'wpjs_regen_description', _wpnonce: wpjs.nonce, post_id: postId }, function (res) {
			btn.prop('disabled', false).text('Generate');
			if (res.success) { input.val(res.data.description).trigger('input'); }
			else { alert(res.data); }
		}).fail(function () { btn.prop('disabled', false).text('Generate'); });
	});

	// Save description.
	$(document).on('click', '.wpjs-ai-save-desc', function () {
		var btn = $(this);
		var postId = btn.data('post-id');
		var desc = btn.parent().find('.wpjs-ai-desc-input').val();
		btn.prop('disabled', true).text('...');
		$.post(wpjs.ajax_url, { action: 'wpjs_save_description', _wpnonce: wpjs.nonce, post_id: postId, description: desc }, function (res) {
			btn.prop('disabled', false).text(res.success ? 'Saved' : 'Error');
			if (res.success) { setTimeout(function () { btn.text('Save'); }, 2000); }
		}).fail(function () { btn.prop('disabled', false).text('Save'); });
	});

	// Regenerate alt text.
	$(document).on('click', '.wpjs-ai-regen-alt', function () {
		var btn = $(this);
		var attId = btn.data('att-id');
		var input = btn.parent().find('.wpjs-ai-alt-input');
		btn.prop('disabled', true).html('&#x21bb;...');
		$.post(wpjs.ajax_url, { action: 'wpjs_regen_alt', _wpnonce: wpjs.nonce, attachment_id: attId }, function (res) {
			btn.prop('disabled', false).text('Generate');
			if (res.success) { input.val(res.data.alt); }
			else { alert(res.data); }
		}).fail(function () { btn.prop('disabled', false).text('Generate'); });
	});

	// Save alt text.
	$(document).on('click', '.wpjs-ai-save-alt', function () {
		var btn = $(this);
		var attId = btn.data('att-id');
		var alt = btn.parent().find('.wpjs-ai-alt-input').val();
		btn.prop('disabled', true).text('...');
		$.post(wpjs.ajax_url, { action: 'wpjs_save_alt', _wpnonce: wpjs.nonce, attachment_id: attId, alt: alt }, function (res) {
			btn.prop('disabled', false).text(res.success ? 'Saved' : 'Error');
			if (res.success) { setTimeout(function () { btn.text('Save'); }, 2000); }
		}).fail(function () { btn.prop('disabled', false).text('Save'); });
	});

	function escAttr(s) { return s ? s.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : ''; }

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
