(function ($) {
	'use strict';

	var repoSelect      = $('#wpjs-repo-select');
	var branchSelect    = $('#wpjs-branch-select');
	var repoSpinner     = $('#wpjs-repo-spinner');
	var branchSpinner   = $('#wpjs-branch-spinner');
	var validateBtn     = $('#wpjs-validate-btn');
	var validateSpinner = $('#wpjs-validate-spinner');
	var validateResult  = $('#wpjs-validate-result');

	// Only run repo/branch logic on Connection tab.
	if (repoSelect.length) {
		loadRepos();
	}

	repoSelect.length && repoSelect.on('change', function () {
		var repo = $(this).val();
		validateResult.html('');
		if (repo) {
			loadBranches(repo);
		} else {
			branchSelect.html('<option value="">Select a repository first</option>').prop('disabled', true);
		}
	});

	validateBtn.on('click', function () {
		var repo   = repoSelect.val();
		var branch = branchSelect.val();
		if (!repo) {
			validateResult.html('<div class="notice notice-warning inline"><p>Select a repository first.</p></div>');
			return;
		}
		validateSpinner.addClass('is-active');
		validateResult.html('');
		$.post(wpjs.ajax_url, {
			action:   'wpjs_validate',
			_wpnonce: wpjs.nonce,
			repo:     repo,
			branch:   branch
		}, function (res) {
			validateSpinner.removeClass('is-active');
			if (!res.success) {
				validateResult.html('<div class="notice notice-error inline" style="margin:0;"><p>' + escHtml(res.data) + '</p></div>');
				return;
			}
			var d    = res.data;
			var ok   = d.repo_access && d.branch_ok;
			var type = ok ? 'success' : 'warning';
			var html = '<div class="notice notice-' + type + ' inline" style="margin:0;padding:8px 12px;">';
			html += '<p style="margin:4px 0;"><strong>User:</strong> ' + escHtml(d.login) + '</p>';
			html += '<p style="margin:4px 0;"><strong>Repository:</strong> ' + escHtml(d.repo) + ' — ' + (d.repo_access ? 'accessible' : 'NOT accessible') + '</p>';
			if (d.repo_access && d.permissions) {
				var perms = [];
				if (d.permissions.push) perms.push('push');
				if (d.permissions.pull) perms.push('pull');
				if (d.permissions.admin) perms.push('admin');
				html += '<p style="margin:4px 0;"><strong>Permissions:</strong> ' + escHtml(perms.join(', ')) + '</p>';
			}
			html += '<p style="margin:4px 0;"><strong>Branch:</strong> ' + escHtml(d.branch || '(none)') + ' — ' + (d.branch_ok ? 'exists' : 'NOT found') + '</p>';
			html += '</div>';
			validateResult.html(html);
		}).fail(function () {
			validateSpinner.removeClass('is-active');
			validateResult.html('<div class="notice notice-error inline" style="margin:0;"><p>Request failed.</p></div>');
		});
	});

	function loadRepos() {
		repoSpinner.addClass('is-active');
		$.post(wpjs.ajax_url, {
			action:   'wpjs_get_repos',
			_wpnonce: wpjs.nonce
		}, function (res) {
			repoSpinner.removeClass('is-active');
			if (!res.success) {
				repoSelect.html('<option value="">Error: ' + escHtml(res.data) + '</option>');
				return;
			}
			var current = repoSelect.data('current') || '';
			var html    = '<option value="">\u2014 Select a repository \u2014</option>';
			$.each(res.data, function (i, r) {
				var sel   = (r.full_name === current) ? ' selected' : '';
				var label = r.full_name + (r['private'] ? ' (private)' : '');
				html += '<option value="' + escAttr(r.full_name) + '"' + sel + '>' + escHtml(label) + '</option>';
			});
			repoSelect.html(html);

			// Auto-load branches for the currently selected repo.
			if (current && repoSelect.val()) {
				loadBranches(current);
			}
		}).fail(function () {
			repoSpinner.removeClass('is-active');
			repoSelect.html('<option value="">Failed to load repositories</option>');
		});
	}

	function loadBranches(repo) {
		branchSpinner.addClass('is-active');
		branchSelect.html('<option value="">Loading branches...</option>').prop('disabled', true);
		$.post(wpjs.ajax_url, {
			action:   'wpjs_get_branches',
			_wpnonce: wpjs.nonce,
			repo:     repo
		}, function (res) {
			branchSpinner.removeClass('is-active');
			if (!res.success) {
				branchSelect.html('<option value="">Error: ' + escHtml(res.data) + '</option>');
				return;
			}
			var current = branchSelect.data('current') || 'main';
			var html    = '';
			$.each(res.data, function (i, name) {
				var sel = (name === current) ? ' selected' : '';
				html += '<option value="' + escAttr(name) + '"' + sel + '>' + escHtml(name) + '</option>';
			});
			branchSelect.html(html).prop('disabled', false);
		}).fail(function () {
			branchSpinner.removeClass('is-active');
			branchSelect.html('<option value="">Failed to load branches</option>');
		});
	}

	/* ─── Style detection ───────────────────────────── */

	var detectBtn     = $('#wpjs-detect-style');
	var detectSpinner = $('#wpjs-detect-spinner');
	var detectStatus  = $('#wpjs-detect-status');
	var profileWrap   = $('#wpjs-style-profile');
	var profileMeta   = $('#wpjs-profile-meta');
	var profileDetail = $('#wpjs-profile-details');

	detectBtn.on('click', function () {
		detectSpinner.addClass('is-active');
		detectStatus.text('Reading Jekyll site...');
		profileDetail.html('');

		$.post(wpjs.ajax_url, {
			action:   'wpjs_detect_style',
			_wpnonce: wpjs.nonce
		}, function (res) {
			detectSpinner.removeClass('is-active');
			if (!res.success) {
				detectStatus.text('Error: ' + res.data);
				return;
			}
			detectStatus.text('Style detected successfully.');
			profileWrap.show();
			renderProfile(res.data);
		}).fail(function () {
			detectSpinner.removeClass('is-active');
			detectStatus.text('Request failed.');
		});
	});

	// Render profile on page load if one exists.
	if (wpjs.style_profile && wpjs.style_profile.front_matter && wpjs.style_profile.front_matter.fields && wpjs.style_profile.front_matter.fields.length) {
		profileWrap.show();
	}

	function renderProfile(profile) {
		var fm  = profile.front_matter || {};
		var md  = profile.markdown || {};
		var cfg = profile.config || {};
		var html = '';

		// Update meta line.
		profileMeta.html('<p class="description">Detected: ' + escHtml(profile.detected_at || '') + ' | Sources: ' + (profile.source_files || []).length + ' files</p>');

		// Front matter table.
		html += '<h4 style="margin-top:16px;">Front Matter Fields</h4>';
		html += '<table class="widefat" style="max-width:500px;">';
		html += '<thead><tr><th>Field</th><th>Type</th><th>Required</th></tr></thead><tbody>';
		$.each(fm.fields || [], function (i, f) {
			html += '<tr><td><code>' + escHtml(f.key) + '</code></td><td>' + escHtml(f.type) + '</td><td>' + (f.required ? 'Yes' : 'No') + '</td></tr>';
		});
		html += '</tbody></table>';
		html += '<p><strong>Array style:</strong> ' + escHtml(fm.array_style || 'block') + '</p>';

		if (cfg.permalink) {
			html += '<p><strong>Permalink:</strong> <code>' + escHtml(cfg.permalink) + '</code></p>';
		}
		if (cfg.markdown) {
			html += '<p><strong>Markdown processor:</strong> ' + escHtml(cfg.markdown) + '</p>';
		}

		// Markdown style table.
		html += '<h4>Markdown Style</h4>';
		html += '<table class="widefat" style="max-width:500px;"><tbody>';
		html += '<tr><td>Headings</td><td>' + escHtml(md.heading_style || 'atx') + ' (<code>' + (md.heading_style === 'setext' ? '== / --' : '#') + '</code>)</td></tr>';
		html += '<tr><td>List marker</td><td><code>' + escHtml(md.ul_marker || '-') + '</code></td></tr>';
		html += '<tr><td>Emphasis</td><td><code>' + escHtml(md.emphasis_marker || '*') + '</code></td></tr>';
		html += '<tr><td>Strong</td><td><code>' + escHtml(md.strong_marker || '**') + '</code></td></tr>';
		html += '<tr><td>Code fence</td><td><code>' + escHtml(md.code_fence || '```') + '</code></td></tr>';
		html += '<tr><td>Horizontal rule</td><td><code>' + escHtml(md.hr_style || '---') + '</code></td></tr>';
		html += '</tbody></table>';

		profileDetail.html(html);
	}

	/* ─── Helpers ────────────────────────────────────── */

	function escHtml(s) {
		return $('<span>').text(s).html();
	}

	function escAttr(s) {
		return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
	}

})(jQuery);
