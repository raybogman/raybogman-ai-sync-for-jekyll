=== Ray Bogman Jekyll Sync ===
Contributors: raybogman
Donate link: https://raybogman.com
Tags: github, github-pages, static-site, markdown, sync
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 7.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Push WordPress posts and pages to a Jekyll GitHub Pages repository as Markdown with YAML front matter.

== Description ==

Ray Bogman Jekyll Sync lets you edit content in WordPress and publish it to a Jekyll site hosted on GitHub Pages.

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

= What does this plugin do? =

Ray Bogman Jekyll Sync lets you publish WordPress posts and pages to a Jekyll site hosted on GitHub Pages. It converts your HTML content to Markdown with YAML front matter, uploads featured images, rewrites internal links, and commits everything directly to your GitHub repository — all from within the WordPress admin.

= How does the GitHub login work? =

The plugin uses the standard GitHub OAuth App flow. You create a free OAuth App on GitHub (under Settings > Developer settings), enter the Client ID and Secret in the plugin, and click "Login with GitHub". GitHub handles authorization and redirects you back. No tokens to copy and paste.

= What is the OAuth callback URL? =

The callback URL is shown on the Connection tab when you first set up the plugin. You must enter this exact URL in your GitHub OAuth App settings as the "Authorization callback URL".

= What is Style-aware conversion? =

Style-aware mode reads your existing Jekyll site to detect its conventions — front matter fields, their order, Markdown heading style (ATX vs Setext), list markers, emphasis markers, code fences, and more. It then uses this "style profile" for every sync, ensuring your pushed content matches the rest of your Jekyll site exactly.

= How does URL rewriting work? =

When you push a post, all internal links pointing to your WordPress domain are automatically replaced with your Jekyll site's URL. The Jekyll URL is read from your _config.yml (the url field) or can be set manually on the Content tab.

= How are featured images handled? =

When you push a post that has a featured image, the plugin uploads the image to your Jekyll repository (default path: assets/images/) and sets the featured_image front matter to the Jekyll-native relative path (e.g. /assets/images/my-post.jpg). The images path is auto-detected from your existing Jekyll posts.

= What does the Approve button do? =

The Approve button marks posts for batch publishing. You can approve multiple posts during the week, then click "Publish all approved to Jekyll" to push them all at once. It's a selection mechanism — approving alone does not push unless auto-push is enabled.

= Can I auto-publish when approving? =

Yes. Check the "Auto-push when approving" checkbox above the articles list. When enabled, clicking Approve on a published post will immediately push it to Jekyll in one click.

= Can I push or delete multiple posts at once? =

Yes. Use the checkboxes in the articles list to select multiple posts, then choose a bulk action from the dropdown: Approve, Unapprove, Push to Jekyll, or Delete from Jekyll. Click Apply to execute.

= Can I preview the Markdown before pushing? =

Yes. Each row in the articles list has a Preview button that opens a modal showing the exact Markdown and YAML front matter that will be committed to your repository. This lets you verify the output before pushing.

= What does "Outdated" status mean? =

A yellow "Outdated" status means the WordPress post was modified after it was last pushed to Jekyll. You should re-push it to sync the latest changes. "Published" (green) means it's up to date, and "Not published" (red) means it has never been pushed.

= Can I delete a post from Jekyll? =

Yes. The Delete button (visible for published posts) removes the Markdown file from your GitHub repository via the Contents API. It also resets the local publish status so you can re-push later after making changes.

= What is read from _config.yml? =

The style detection reads: url (site base URL), baseurl, permalink pattern, markdown processor, and the posts permalink from collections.posts.permalink. It also detects the images directory from existing posts' featured_image paths.

= How are permalinks generated? =

In Style-aware mode, the permalink front matter field is generated using the pattern from your _config.yml. Variables like :year, :month, :day, :title, :slug, and :categories are substituted with the post's actual data.

= Why did my settings disappear? =

In earlier versions, saving from one settings tab could overwrite settings from another tab. This was fixed in v2.5.0 — settings now only update fields present in the submitted form. If you experience this, re-enter your settings and save from the correct tab.

= What content types are supported? =

Posts and pages. You can enable or disable each type independently on the Content tab. Posts are saved to the Jekyll _posts/ directory (as YYYY-MM-DD-slug.md), and pages to _pages/ (as slug.md).

= Are inline images uploaded to Jekyll? =

Yes. Both featured images and inline body images are uploaded to your Jekyll repository. All images with /wp-content/uploads/ URLs are uploaded to assets/images/ and their URLs rewritten to Jekyll-native paths.

= Are there GitHub API rate limits? =

GitHub allows 5,000 API requests per hour for authenticated users. Each post push uses 2-3 requests (check existing file + create/update), and style detection uses about 7 requests. Normal usage is well within limits. Bulk-pushing 50 posts at once uses approximately 100-150 requests.

= What is the Dashboard? =

Stats overview: total posts, synced, outdated, not published, approved. Shows recent sync activity log and quick action buttons.

= What is the Sync History Log? =

Tracks every push, delete, and pull action with timestamp, user, post title, path, and result. View on the Log tab. Max 500 entries with Clear Log button.

= What is the Diff view? =

Compares current WP content against what's live on Jekyll. Shows color-coded additions (green) and deletions (red) before re-pushing.

= How does AI description generation work? =

If enabled and an API key is configured (Claude or OpenAI), clicking AI on a post generates a 1-2 sentence SEO description (max 160 chars). You can edit, regenerate, or save. Saved as WP excerpt.

= How does AI image alt text work? =

Uses AI vision (Claude or OpenAI) to describe images. Works for featured and inline images. Alt text saved to WP attachment meta and used in Jekyll output.

= What is the GitHub Actions trigger? =

Optional. Enter a workflow filename (e.g. jekyll.yml) on the Connection tab. After each push, the plugin triggers that workflow via the GitHub API to rebuild your Jekyll site.

= What is auto-push on publish? =

When enabled on the Connection tab, publishing or updating a post in WordPress automatically pushes it to Jekyll. No manual push needed.

= What is scheduled auto-sync? =

WP-Cron based. Configure an interval (1/6/12/24 hours) and mode (approved and outdated only, or all published) on the Connection tab. The plugin automatically pushes content on schedule.

= How does Pull from Jekyll work? =

The Pull tab lists all Markdown files in your Jekyll _posts directory. You can import individual posts or all new posts at once. Creates WP posts as drafts with parsed content.

= What does Verify do? =

Compares the stored push hash against the actual Jekyll file on GitHub. Shows if content matches, if WP was modified after push, if the Jekyll file was externally edited, or if the file is missing.

= Are inline body images synced? =

Yes. All images in the post body with /wp-content/uploads/ URLs are uploaded to Jekyll assets/images/ and their URLs rewritten to Jekyll-native paths.

= Is image alignment preserved? =

Yes. WordPress alignment classes (alignright, alignleft, aligncenter) are converted to inline CSS styles in the HTML img tag, preserving the layout in Jekyll.

= Does it support Yoast SEO and RankMath? =

Yes. The plugin auto-detects meta description and focus keywords from Yoast SEO and RankMath and maps them to Jekyll front matter fields.

== External Services ==

This plugin connects to external third-party services depending on your configuration:

= GitHub API =

* Service: [GitHub REST API](https://docs.github.com/en/rest)
* Used for: OAuth authentication, listing repositories and branches, reading and writing Jekyll post files, uploading images, deleting files, triggering GitHub Actions workflows.
* When: Every time you push, pull, delete, verify, or detect styles from your Jekyll repository.
* Data sent: Your GitHub OAuth token, post content as Markdown, image files, commit messages.
* [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)
* [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)

= Anthropic Claude API (Optional) =

* Service: [Anthropic Messages API](https://docs.anthropic.com/en/docs/about-claude/models)
* Used for: AI-generated SEO descriptions and image alt text.
* When: Only when you click "Generate" in the AI panel or enable auto-generation. Never called without user action.
* Data sent: Post text content (up to 2000 characters) for descriptions. Image data (base64 encoded) for alt text generation.
* Requires: A valid Claude API key entered by the user.
* [Anthropic Terms of Service](https://www.anthropic.com/legal/consumer-terms)
* [Anthropic Privacy Policy](https://www.anthropic.com/legal/privacy)

= OpenAI API (Optional) =

* Service: [OpenAI Chat Completions API](https://platform.openai.com/docs/api-reference/chat)
* Used for: AI-generated SEO descriptions and image alt text (alternative to Claude).
* When: Only when you click "Generate" in the AI panel or enable auto-generation. Never called without user action.
* Data sent: Post text content (up to 2000 characters) for descriptions. Image data (base64 encoded) for alt text generation.
* Requires: A valid OpenAI API key entered by the user.
* [OpenAI Terms of Use](https://openai.com/policies/terms-of-use/)
* [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/)

== Screenshots ==

1. **Articles** — manage posts and pages with push, preview, AI, diff, delete, and verify actions.
2. **Dashboard** — stats overview with total posts, synced, outdated, and recent activity log.
3. **Connection Settings** — GitHub OAuth login, repo/branch picker, AI provider configuration.
4. **Content Settings** — content mapping, URL rewriting, author selection.
5. **Formatting** — style detection from your Jekyll site with front matter and Markdown analysis.
6. **AI Panel** — inline description editor and image alt text generator with regenerate and save.
7. **Diff View** — compare current WordPress content with what is live on Jekyll.
8. **Pull from Jekyll** — import Jekyll posts back into WordPress.

== Upgrade Notice ==

= 7.0.0 =
WP.org compliance release. External services documented. Inline scripts replaced with wp_add_inline_script. file_get_contents replaced with WP_Filesystem.

== Changelog ==

= 7.0.0 =
* **WP.org compliance release** — ready for plugin directory submission.
* Replaced inline `<script>` with `wp_add_inline_script()`.
* Replaced `file_get_contents()` with `WP_Filesystem` API (3 locations).
* Added complete **External Services** section documenting GitHub, Claude, and OpenAI API usage with terms/privacy links.

= 6.7.0 =
* Added All | Posts | Pages filter tabs.

= 6.6.0 =
* **Fixed false "Out of sync" on verify** — now compares stored push hash vs Jekyll file hash instead of regenerating markdown (which differs due to image paths).
* Stores MD5 hash of pushed content on every push (`_wpjs_push_hash` meta).
* Verify now shows accurate states: "Verified — Jekyll matches last push", "WP modified after push", "Jekyll file was modified externally", "Not on Jekyll".

= 6.5.0 =
* **Realtime sync verification** — "Verify" link per post checks WP content against the actual Jekyll file on GitHub.
* Shows: "In sync" (green), "Out of sync — X lines differ" (yellow), "Not on Jekyll" (red).
* **Verify all synced** button at the top — checks all posts sequentially.
* Compares MD5 hashes of generated markdown vs GitHub file content.

= 6.3.0 =
* **Fixed AI alt text not appearing after re-push** — converter now reads latest alt text from WP attachment meta (`_wp_attachment_image_alt`) instead of the stale HTML `alt` attribute.
* Looks up attachment by `wp-image-{ID}` CSS class or URL-to-attachment-ID fallback.

= 6.1.0 =
* AI panel now always shows existing description/alt text for editing — works with or without AI API key.
* Description uses textarea instead of single-line input.
* "Generate" button (AI) and "Save" button shown separately — generate first, edit, then save.
* Character counter turns red when over 160.
* Fixed HTML entity encoding in description display.
* AI link always visible (not gated behind API key) — manual editing always available.

= 6.0.1 =
* Fixed JS not loading on Articles page — enqueue now uses both hook comparison AND page query param as fallback.
* Fixed AI link not showing — `is_available()` now checks both Claude and OpenAI keys regardless of selected provider.

= 6.0.0 =
* **AI panel redesigned** — clicking AI opens a persistent inline panel below the row (never auto-hides).
* Description field: editable text input with character counter (160 max), Regenerate (↻) and Save buttons.
* Image alt text: each image shown with filename, featured badge, editable input, Regenerate and Save buttons.
* Source labels: (AI generated), (from SEO plugin), (from excerpt), (existing).
* Close button to dismiss the panel.
* New AJAX endpoints: regen_description, save_description, regen_alt, save_alt.

= 5.9.0 =
* Actions column restored as always-visible text links between Title and Status.
* Layout: Title (40%) | Actions (auto) | Status (100px) | Synced (90px).
* Actions show as: Push | Preview | AI | Diff | Delete — always visible, no hover needed.

= 5.8.0 =
* Actions moved to row actions under the title (standard WP pattern — Edit | Push | Preview | AI | Diff | Delete).
* Removed separate Actions column — 3 columns only: Title, Status, Synced.
* Row actions appear on hover, fitting any screen width.

= 5.7.0 =
* Reduced to 4 columns: Title, Status, Synced, Actions — fits any screen.
* Status column now combines sync status + approve toggle (✓ Ready / ○ Queued).
* Removed separate Approved column.
* Actions column widened to 200px for icon buttons.
* AI feedback shown as inline row notice instead of off-screen tooltip.

= 5.6.0 =
* **Redesigned Articles table** — compact icon-based actions, fewer columns, better UX.
* Merged Author, Type, Date into Title column subtitle (type · author · date).
* Actions as icon buttons with tooltips: Push, Preview, AI, Diff, Delete.
* Approved column as single icon toggle (checkmark / circle).
* Last pushed shows relative time ("3 hours ago") with full date on hover.
* Jekyll Status with colored dots (green=synced, yellow=outdated, red=not synced).

= 5.5.0 =
* **AI button per article** — generate AI description + image alt text before pushing, then preview the result.
* **Bulk action "Generate AI Metadata"** — run AI on multiple selected posts at once.
* AI generation now independent from push — run AI first, preview, then push when satisfied.
* Tooltip shows AI summary after generation (description text, number of alt texts generated).

= 5.4.0 =
* AI settings redesigned — separate sections for Claude (Anthropic) and OpenAI (GPT), matching Content Orchestrator layout.
* Separate API key, model dropdown, and Validate button per provider.
* Claude models: Sonnet 4.6 (recommended), Opus 4.6, Haiku 4.5.
* OpenAI models: GPT-4o (recommended), GPT-4o Mini, GPT-4 Turbo, GPT-4.1, GPT-4.1 Mini, GPT-4.1 Nano.
* Provider selector chooses which AI is used for description/alt text generation.

= 5.3.0 =
* Display name changed to "Ray Bogman Jekyll Sync" everywhere (no dashes, no RayBogman compound).

= 5.2.0 =
* AI features now fully independent — no dependency on RayAI Content Orchestrator.
* Own API key, provider (Claude/OpenAI), and model fields on Connection tab.
* Validate button for API key testing.
* AI Model field added (customizable, defaults to claude-sonnet-4-6 / gpt-4o).

= 5.1.0 =
* Renamed plugin from "RayAI – Jekyll Sync" to "Ray Bogman Jekyll Sync" with slug `raybogman-jekyll-sync`.
* Updated all display strings, page titles, and metadata to reflect the new brand name.
* Kept internal class names, option keys, and constants unchanged.

= 5.0.0 =
* **AI Description Generator** — auto-generates SEO meta descriptions for posts missing excerpts and Yoast/RankMath descriptions. AI summarizes the post in 1-2 sentences (max 160 chars), saved as WP excerpt + Jekyll front matter.
* **AI Image Alt Text** — auto-generates alt text for images missing it using AI vision (Claude/OpenAI). Works for featured images and inline body images. Alt text saved back to WP attachment meta.
* **Shared API keys** — automatically detects and uses Claude/OpenAI API keys from RayAI Content Orchestrator if installed. Own API key field shown as fallback.
* New AI Features card on Connection tab with toggles, provider auto-detection, API key validation.
* New `class-ai-client.php` with Claude Messages API and OpenAI Chat Completions API including vision support.

= 4.6.0 =
* Fixed inline images disappearing — images are now processed BEFORE inline formatting (bold/italic) to prevent `<strong>` wrapping from consuming the `<img>` tag.
* Shared `convert_img_tag()` method used by both standard and styled converters.
* `finalize_markdown()` helper preserves HTML `<img>` tags through the final `wp_strip_all_tags()` using placeholders.
* Removed duplicate pre/link/img handlers in styled converter.

= 4.5.0 =
* **Image alignment preserved** — WP classes `alignright`, `alignleft`, `aligncenter` converted to inline CSS styles in HTML `<img>` tags instead of plain Markdown `![]()`
* Width and height attributes preserved from original HTML.
* Both standard and style-aware converters updated.

= 4.4.0 =
* Fixed inline image sync — now catches `/wp-content/uploads/` URLs regardless of domain (WP or already-rewritten Jekyll domain).
* Converts rewritten Jekyll URLs back to WP URLs for media library lookup.
* Handles WordPress image size suffixes (e.g. `image-300x300.png` → finds `image.png` in media library).
* Falls back to HTTP download if local file not found.

= 4.3.0 =
* **Inline body images now synced to Jekyll** — all images inside post content are uploaded to `assets/images/` and their URLs rewritten to Jekyll-native paths.
* Supports images from WP media library (read from disk) and remote WP URLs (downloaded).
* Handles jpg, jpeg, png, gif, webp, svg formats.
* Each inline image committed individually with descriptive commit message.

= 4.2.0 =
* Fixed Pull tab JS not loading — articles.js now enqueued on Settings page too.
* Added "Import All New" button to pull all new Jekyll posts at once.
* Green summary banner after pulling: "Imported/updated X post(s) from Jekyll" with links to View Draft Posts and View Articles.

= 4.1.0 =
* **Scheduled Auto-Sync** — WP-Cron based, configurable interval (1/6/12/24 hours). Modes: approved & outdated only, or all published. Shows next run time on Connection tab.
* **Two-way Sync (Pull from Jekyll)** — new "Pull from Jekyll" tab lists all posts in the Jekyll repo, shows which exist in WP, Import/Update buttons. Markdown converted back to HTML with front matter parsed.
* Cron rescheduled automatically when settings are saved.

= 4.0.0 =
* **Dashboard** — stats overview: Total Posts/Pages, Published, Outdated, Not Published, Approved with color-coded cards.
* **Sync History Log** — tracks every push/delete with timestamp, user, post, path, result. New "Log" tab in Settings. Max 500 entries with Clear Log button.
* **Diff View** — compare current WP content with what's on Jekyll before pushing. Color-coded additions (green) and deletions (red).
* **SEO Metadata Mapping** — auto-detects Yoast SEO and RankMath. Maps meta title, description, and focus keywords to Jekyll front matter.
* **GitHub Actions Trigger** — optionally trigger a Jekyll build workflow after each push. Configure workflow filename on Connection tab.
* **Auto-push on Publish** — hook into WordPress publish action to auto-push posts/pages when published or updated.
* Dashboard is now the main landing page under the Jekyll Sync menu.
* Delete actions refactored to use Publisher::delete() with logging.

= 3.3.0 =
* Added **More by Ray Bogman** tab showcasing the Ray Bogman plugin ecosystem.
* Content Orchestrator product card with features, pricing table, and install detection.
* Jekyll Sync card with ACTIVE badge.
* Ray Bogman Ecosystem visual pipeline: Create → Publish → Live.

= 3.2.0 =
* Fixed duplicate FAQ tab in navigation.
* Branded page title: "Ray Bogman Jekyll Sync — Settings" with dashicon on all pages.
* Renamed "Content Style" tab to "Formatting".
* FAQ and About tabs now use full page width.

= 3.1.0 =
* Added **FAQ tab** with 18 questions covering setup, features, troubleshooting, and limits.
* Added **About tab** with plugin overview, feature table, author bio, certifications, and social links.
* FAQ and About tabs visible even when not connected to GitHub.

= 3.0.0 =
* Renamed plugin to "Ray Bogman Jekyll Sync" with slug `raybogman-jekyll-sync` for WP.org trademark compliance.
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
