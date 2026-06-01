=== Ray Bogman AI Sync for Jekyll & GitHub Pages ===
Contributors: raybogman
Donate link: https://raybogman.com
Tags: jekyll, markdown, static site, sync, deployment
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish WordPress posts to Jekyll on GitHub Pages as Markdown with YAML front matter. Optional AI for SEO descriptions and image alt text.

== Description ==

Ray Bogman AI Sync for Jekyll & GitHub Pages lets you edit content in WordPress and publish it to a Jekyll site hosted on GitHub Pages. Jekyll and GitHub are projects/trademarks of their respective owners; this plugin is an independent integration and is not affiliated with or endorsed by either.

Features:

* **One-click "Login with GitHub"** — OAuth login, no manual token pasting
* **Live repository picker** — all your repos load instantly after login via AJAX
* **Live branch picker** — select a repo and branches load automatically, no page refresh
* Top-level **Jekyll Sync** admin menu with Dashboard, Articles, and Settings subpages
* Articles list with Approved toggle per item, type filter, search, per-row push/preview/AI/diff/delete/verify
* Bulk **Publish approved to Jekyll** button + bulk actions for approve, push, delete
* Converts post HTML to Markdown with Jekyll YAML front matter
* **Style-aware conversion** — reads your existing Jekyll site's `_config.yml` and sample posts to match its front matter schema, permalink pattern, and Markdown conventions
* **Featured & inline image sync** — uploads images to your Jekyll repo and rewrites URLs to Jekyll-native paths
* **Internal link rewriting** — WordPress domain links automatically rewritten to your Jekyll site URL
* **AI SEO descriptions** (optional) — Claude or OpenAI generates 1-2 sentence descriptions on demand
* **AI image alt text** (optional) — Claude or OpenAI vision describes images missing alt text
* **Two-way sync** — pull Jekyll posts back into WordPress as drafts
* **Scheduled auto-sync** — WP-Cron based intervals (1/6/12/24 hours)
* **Auto-push on publish** — push automatically when WordPress posts are published or updated
* **Sync verification** — compare stored push hash against the live Jekyll file
* **Diff view** — preview color-coded additions/deletions before re-pushing
* **Sync history log** — every push, pull, and delete recorded with user, post, path, and result
* **GitHub Actions trigger** — optionally dispatch a build workflow after each push
* **SEO plugin support** — auto-detects Yoast SEO and RankMath meta descriptions and focus keywords
* Per-post sidebar meta box

== Installation ==

1. Upload the `raybogman-ai-sync-for-jekyll` folder to `/wp-content/plugins/` (or upload the zip via Plugins → Add New → Upload).
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

This plugin lets you publish WordPress posts and pages to a Jekyll site hosted on GitHub Pages. It converts your HTML content to Markdown with YAML front matter, uploads featured images, rewrites internal links, and commits everything directly to your GitHub repository — all from within the WordPress admin. Optional AI (Claude or OpenAI) generates SEO descriptions and image alt text on demand.

= How does the GitHub login work? =

The plugin uses the standard GitHub OAuth App flow. You create a free OAuth App on GitHub (under Settings > Developer settings), enter the Client ID and Secret in the plugin, and click "Login with GitHub". GitHub handles authorization and redirects you back. No tokens to copy and paste.

= What is the OAuth callback URL? =

The callback URL is shown on the Connection tab when you first set up the plugin. You must enter this exact URL in your GitHub OAuth App settings as the "Authorization callback URL".

= What is Style-aware conversion? =

Style-aware mode reads your existing Jekyll site to detect its conventions — front matter fields, their order, Markdown heading style (ATX vs Setext), list markers, emphasis markers, code fences, and more. It then uses this "style profile" for every sync, ensuring your pushed content matches the rest of your Jekyll site exactly.

= How does URL rewriting work? =

When you push a post, all internal links pointing to your WordPress domain are automatically replaced with your Jekyll site's URL. The Jekyll URL is read from your _config.yml (the url field) or can be set manually on the Content tab. For example, `https://wp.example.com/my-post/` becomes `https://example.com/my-post/`.

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

= Does saving one settings tab overwrite another? =

No. Each tab's save only updates the fields actually present in its form, so settings on other tabs are preserved.

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

== Changelog ==

= 1.0.0 =
* Initial public release.
* WordPress-to-Jekyll publishing on GitHub Pages with Markdown + YAML front matter.
* Style-aware conversion that reads your Jekyll site and matches its conventions.
* GitHub OAuth login, live repo/branch pickers.
* Featured and inline image sync to the Jekyll repository.
* Internal link rewriting from WordPress domain to Jekyll site URL.
* Optional AI (Claude / OpenAI) for SEO descriptions and image alt text on demand.
* Two-way sync — pull Jekyll posts back into WordPress as drafts.
* Scheduled auto-sync (WP-Cron) and auto-push on publish.
* Sync verification, diff view, and full sync history log.
* GitHub Actions workflow trigger after each push.
* Yoast SEO and RankMath meta detection.
