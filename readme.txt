=== RayAI – Jekyll Sync ===
Contributors: raybogman
Tags: jekyll, github, github-pages, static-site, sync
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Push WordPress posts and pages to a Jekyll GitHub Pages repository as Markdown with YAML front matter.

== Description ==

RayAI Jekyll Sync lets you edit content in WordPress and publish it to a Jekyll site hosted on GitHub Pages.

Features:

* **One-click "Login with GitHub"** — OAuth login, no manual token pasting
* **Live repository picker** — all your repos load instantly after login via AJAX
* **Live branch picker** — select a repo and branches load automatically, no page refresh
* Connected state shows your GitHub avatar, username, and a Disconnect button
* Top-level **Jekyll Sync** admin menu with Articles and Settings subpages
* Articles page with Approved toggle per item, type filter, search, per-row "Push now"
* Bulk **Publish approved to Jekyll** button
* Converts post HTML to Markdown with Jekyll YAML front matter
* Commits directly to GitHub via the Contents API
* Per-post sidebar meta box

== Installation ==

1. Upload the `wp-jekyll-sync` folder to `/wp-content/plugins/` (or upload the zip via Plugins → Add New → Upload).
2. Activate the plugin.
3. Go to **Jekyll Sync → Settings**:
   * Create a GitHub OAuth App at [github.com/settings/developers](https://github.com/settings/developers).
   * Set the **Authorization callback URL** to the URL shown on the settings page.
   * Enter Client ID and Client Secret, click **Save Credentials**.
   * Click **Login with GitHub** — you will be redirected to GitHub to authorize.
4. After login, pick your repository and branch from the dropdowns — they load live.
5. Configure Jekyll paths and click **Save Settings**.
6. Go to **Jekyll Sync → Articles**, approve items, and click **Publish approved to Jekyll**.

== Frequently Asked Questions ==

= How does the GitHub login work? =

The plugin uses the standard GitHub OAuth App flow. You register a free OAuth App on GitHub, enter the Client ID and Secret, and click Login. GitHub handles authorization and redirects you back. No tokens to copy and paste.

= Do I have to reload the page to pick a repo or branch? =

No. Repos load via AJAX as soon as the page opens. Branches load instantly when you switch repos.

= Are images uploaded to GitHub? =

No — image URLs remain pointing at your WordPress media library.

= Does it auto-sync on save? =

No. You control what gets published via the Approved toggle + explicit push.

== Changelog ==

= 3.3.0 =
* Added **More by RayAI** tab showcasing the RayAI plugin ecosystem.
* Content Orchestrator product card with features, pricing table, and install detection.
* Jekyll Sync card with ACTIVE badge.
* RayAI Ecosystem visual pipeline: Create → Publish → Live.

= 3.2.0 =
* Fixed duplicate FAQ tab in navigation.
* Branded page title: "RayAI – Jekyll Sync — Settings" with dashicon on all pages.
* Renamed "Content Style" tab to "Formatting".
* FAQ and About tabs now use full page width.

= 3.1.0 =
* Added **FAQ tab** with 18 questions covering setup, features, troubleshooting, and limits.
* Added **About tab** with plugin overview, feature table, author bio, certifications, and social links.
* FAQ and About tabs visible even when not connected to GitHub.

= 3.0.0 =
* Renamed plugin to "RayAI Jekyll Sync" with slug `rayai-jekyll-sync` for WP.org trademark compliance.
* Fixed all Plugin Check warnings with proper phpcs:ignore annotations.
* Major version bump for the rename.

= 2.5.0 =
* **Fixed URL rewriting root cause** — Settings update method was overwriting `jekyll_base_url`, `sync_posts`, `sync_pages`, and `conversion_mode` when saving from a different tab. Now only updates fields actually present in the submitted form.
* Prevents cross-tab setting corruption (saving Connection tab no longer resets Content tab checkboxes to off).

= 2.4.0 =
* Replaced all `strip_tags()` calls with `wp_strip_all_tags()` or `wp_kses()` for Plugin Check compliance.
* Replaced `wp_redirect()` with `wp_safe_redirect()` in OAuth handler.
* Added `wp_unslash()` and proper sanitization to all superglobal access (`$_GET`, `$_POST`).
* Updated "Tested up to" to 6.9.
* Reduced plugin tags to maximum of 5.
* Renamed display name from "WP Jekyll Sync" to "Jekyll Sync" for trademark compliance.

= 2.3.0 =
* URL rewriting reads settings directly from wp_options (bypasses any caching/getter issues).
* Checks all WP URL sources: site_url(), home_url(), and raw siteurl/home options from database.
* Content tab shows all WP URLs being rewritten and the target Jekyll URL.

= 2.2.0 =
* **Fixed bulk actions** — row actions changed from nested forms to GET links, eliminating the HTML form conflict that broke bulk approve/push/delete.
* **Fixed URL rewriting** — manual setting now takes priority over auto-detected, tries both `site_url()` and `home_url()` as WP sources, clear green/yellow status indicator on Content tab.
* Row action handlers updated from POST to GET (toggle_approve, publish_one, delete_one).
* Content tab shows success/warning notice for URL rewriting status.

= 2.1.0 =
* **Featured images now uploaded to Jekyll repo** — image file pushed to `assets/images/` (or auto-detected path) with Jekyll-native paths in front matter.
* Images path auto-detected from existing posts' `featured_image` values during style detection.
* Fallback: checks if `assets/images` directory exists in the repo.
* `featured_image` front matter now uses relative Jekyll paths (e.g. `/assets/images/my-post.jpg`) instead of WP URLs.
* Images path shown in Detected Profile on Content Style tab.

= 2.0.3 =
* Fixed bulk actions not working — hidden `action` field was conflicting with WP_List_Table's bulk action dropdown. Now uses `admin_init` with a separate nonce field.

= 2.0.2 =
* Fixed URL rewriting to cover the ENTIRE output — front matter (featured_image, image URLs) AND body links are now all rewritten in one pass.

= 2.0.1 =
* Fixed column widths — Title, Author, Type, Date, Status, Approved, Last pushed, Actions all sized proportionally to eliminate excess spacing.

= 2.0.0 =
* Fixed URL rewriting — now correctly replaces all `wp.bogman.info` links with the Jekyll base URL.
* Content tab shows active rewrite rule: `wp.bogman.info → raybogman.com`.
* Re-push, Preview, Delete buttons aligned on one line (nowrap).
* `get_jekyll_base_url()` made public for display on settings page.

= 1.9.0 =
* Fixed URL detection — now correctly reads `url: "https://raybogman.com"` from _config.yml (was showing github.io).
* Parser only reads top-level YAML keys (skips indented/nested lines to avoid false matches).
* Empty `baseurl: ""` handled properly (no longer treated as a value).
* Extracts posts permalink from `collections.posts.permalink` (e.g. `/blog/:slug/`).
* Jekyll Base URL field auto-filled with detected URL when present.
* Permalink builder now supports `:slug` variable.

= 1.8.0 =
* **Internal link rewriting** — WP base URL replaced with Jekyll base URL in all Markdown links and images.
* Jekyll base URL auto-detected from `_config.yml` (`url` + `baseurl` fields) during style detection.
* Manual fallback: Jekyll Base URL field on the Content tab.
* Style detector now extracts `url` and `baseurl` from `_config.yml`.
* Image icon now inline with title on a single line.

= 1.7.0 =
* Push and Preview buttons now side-by-side on the same row (flexbox layout).
* Green image icon next to title when the post/page has a featured image.
* Delete button aligned inline with Push/Preview when visible.

= 1.6.0 =
* **Stale indicator** — yellow "Outdated" status when a WP post was modified after the last push.
* **Bulk checkboxes** — select multiple posts and use Bulk Actions: Approve, Unapprove, Push to Jekyll, Delete from Jekyll.
* **Auto-push on approve** — checkbox toggle to automatically push when clicking Approve.
* **Preview button** — see the generated Markdown in a modal before pushing.
* Per-row actions now show Preview button for published posts.

= 1.5.0 =
* Fixed missing content on Jekyll site — WP excerpt now maps to `description` front matter field.
* Added `featured_image` front matter from WP featured image (post thumbnail).
* Style-aware mode data map now includes `description`, `featured_image`, and `image` aliases for theme compatibility.
* Standard mode also outputs `description` and `featured_image` when available.

= 1.4.0 =
* Fixed Approved toggle not working — nonces now use per-post unique tokens.
* Added **Delete** button per article — removes the file from the Jekyll repo and resets the publish status.
* Push button shows "Re-push" for already-published articles.
* Delete button only visible for published-to-Jekyll articles, with confirmation dialog.
* Push and delete nonces also per-post for reliability.

= 1.3.0 =
* Added **Author** and **Date** columns to the Articles list.
* **Jekyll Status** column replaces generic Status — shows green "Published" if pushed to Jekyll, red "Not published" if not.
* Approved buttons now use colored dots (green = approved, grey = not approved).

= 1.2.0 =
* Added **Content Style** as its own tab (3 tabs: Connection, Content, Content Style).
* Style tab has: Conversion Mode card, Detect Style card, and Detected Profile card.
* Cleaner separation of settings by concern.

= 1.1.0 =
* Settings page restructured into **Connection** and **Content** tabs.
* Content Style section moved to the Content tab with its own save button ("Save Style Settings").
* Content Mapping + Author settings now on the Content tab.
* Tab state preserved after saving.

= 1.0.0 =
* **Style-aware conversion mode** — reads your Jekyll site to detect front matter schema, Markdown conventions, and permalink patterns.
* One-click "Detect Style from Jekyll Site" button reads `_config.yml` + samples up to 5 posts.
* Detected profile shows front matter fields (name, type, required), array style (block/inline), permalink pattern, and Markdown conventions (headings, lists, emphasis, code fences, HR).
* Profile stored and applied automatically on every sync when style-aware mode is active.
* Standard conversion mode remains unchanged as the default.
* GitHub client can now read file contents and list directories from the repo.

= 0.9.0 =
* Content Mapping section with sync toggles: enable/disable syncing WP Posts and Pages independently.
* Author field now a dropdown: "Use post/page author" (default) or pick a specific WP user.
* Articles list respects sync toggles — disabled content types are hidden.
* Clearer visual mapping: Posts -> _posts, Pages -> _pages with editable paths.

= 0.8.0 =
* Switched OAuth callback from admin_init to admin-post.php for reliable callback handling.
* Added debug panel showing connection status at top of settings page.
* Better error messages for all OAuth failure scenarios.

= 0.7.0 =
* Fixed JS not loading — enqueue hook now uses the correct page handle from add_submenu_page.
* Fixed OAuth state transient being overwritten on every page render.
* Moved "Login with GitHub" button outside the form to prevent click interference.
* Added fallback URL field so users can manually copy the OAuth authorize URL.
* Changed wp_safe_redirect to wp_redirect in OAuth callback for broader host compatibility.
* Added GitHub error parameter handling in OAuth callback.

= 0.6.0 =
* Added "Validate Connection" button — checks repo access, permissions (push/pull/admin), and branch existence via AJAX.
* Validation results shown inline with detailed status (user, repo, permissions, branch).

= 0.5.0 =
* AJAX-powered repository dropdown — repos load live on page open, no save needed.
* AJAX-powered branch dropdown — branches load instantly when a repo is selected.
* Streamlined settings page: single card when not connected, clean layout when connected.
* Removed multi-step save dance — pick repo, pick branch, save once.

= 0.4.0 =
* GitHub OAuth login flow — "Login with GitHub" button replaces manual token entry.
* Connected state displays GitHub avatar, username, and Disconnect button.
* Settings page restructured into guided steps.
* CSRF-protected OAuth state parameter.

= 0.3.0 =
* Repository dropdown from GitHub API (server-side).
* Branch dropdown for selected repo.
* Fallback to manual text input.

= 0.2.0 =
* Top-level Jekyll Sync admin menu.
* Articles page (WP_List_Table) with Approved toggle, filters, search, Push action.
* Publisher class refactor.

= 0.1.0 =
* Initial release.
