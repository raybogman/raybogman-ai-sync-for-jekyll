# WordPress.org listing assets

Drop the files below into this `.wordpress-org/` folder. The GitHub Actions
deploy (`10up/action-wordpress-plugin-deploy`) publishes them to the plugin's
SVN `/assets/` directory on the next `v*` tag. This folder is excluded from the
distributed plugin zip via `.distignore`.

Public page: https://wordpress.org/plugins/raybogman-ai-sync-for-jekyll-github-pages

## Icons & banners

| File | Size | Notes |
| --- | --- | --- |
| `icon-256x256.png` | 256×256 | Primary icon |
| `icon-128x128.png` | 128×128 | Standard-DPI fallback |
| `icon.svg` | vector | Optional; preferred by wp.org if present |
| `banner-1544x500.png` | 1544×500 | Retina banner |
| `banner-772x250.png` | 772×250 | Standard banner |

## Screenshots (must match the readme.txt `== Screenshots ==` order)

| File | Caption |
| --- | --- |
| `screenshot-1.png` | Articles — manage posts/pages with push, preview, AI, diff, delete, verify |
| `screenshot-2.png` | Dashboard — stats overview with total/synced/outdated + activity log |
| `screenshot-3.png` | Connection Settings — GitHub OAuth, repo/branch picker, AI provider |
| `screenshot-4.png` | Content Settings — content mapping, URL rewriting, author selection |
| `screenshot-5.png` | Formatting — style detection with front matter and Markdown analysis |
| `screenshot-6.png` | AI Panel — inline description editor and image alt text generator |
| `screenshot-7.png` | Diff View — current WordPress content vs what is live on Jekyll |
| `screenshot-8.png` | Pull from Jekyll — import Jekyll posts back into WordPress |

Rules: lowercase filenames, PNG or JPG, consistent width (~1280px recommended).

## Publishing after adding assets

```
git add .wordpress-org/
git commit -m "Add wp.org listing assets (icon, banner, screenshots)"
git push origin main
# bump version first if also shipping code, then:
git tag vX.Y.Z && git push origin vX.Y.Z   # triggers SVN deploy incl. /assets/
```
