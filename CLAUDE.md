# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

**News Crawler** — a WordPress plugin that auto-fetches articles from configured news sources (and YouTube videos) and publishes them as posts, with OpenAI-based summarization, SEO metadata generation, OGP tags, and auto-sharing to X. GitHub: `KantanPro/news-crawler`. Standalone product — no relation to KantanPro/KantanProEX/KantanBiz beyond shared authorship and the shared license backend below.

## Licensing integration

This plugin is one of the products licensed through **KantanPro License Manager** (`wordpress/wp-content/plugins/KantanPro-License-Manager`, a separate plugin/repo). `includes/class-nc-license-client.php` calls that plugin's REST API at `{NC_LICENSE_API_BASE}/wp-json/ktp-license/v1/verify` — license keys here use the `NCR` prefix (e.g. `NCRL-...` for Lifetime). If you're changing license validation behavior, check the License Manager's REST contract (`class-ktp-license-api.php` there) rather than assuming this client's expectations are the source of truth.

## Architecture

`news-crawler.php` is the bootstrap: defines constants (`NEWS_CRAWLER_VERSION`, `NEWS_CRAWLER_PLUGIN_DIR`, etc.), wires plugin-row-meta/admin-footer terms links, then `require_once`s each `includes/class-*.php`. ~28 single-purpose classes, notably:

- **Crawling/content**: `class-youtube-crawler.php`, `class-youtube-channel-resolver.php`, `class-pending-article-queue.php` (queued articles awaiting processing/approval)
- **AI/content generation**: `class-openai-summarizer.php`, `class-seo-title-generator.php`, `class-featured-image-generator.php`, `class-eyecatch-generator.php` / `class-eyecatch-admin.php`
- **SEO/OGP**: `class-seo-settings.php`, `class-ogp-manager.php`
- **X (Twitter) auto-posting**: `class-x-poster.php`, `class-x-oauth.php`, `class-x-crypto.php`, `class-x-share-log.php` — a separate, independent X integration from the `xLabo` plugin; don't assume shared state or try to unify them without being asked.
- **Licensing**: `class-license-manager.php`, `class-license-settings.php`, `class-nc-license-client.php` (see above)
- **Cross-cutting**: `class-security-manager.php`, `class-secure-logger.php`, `class-settings-manager.php`, `class-cron-settings.php`, `class-genre-settings.php`, `class-updater.php` / `class-updater-backup.php` (GitHub-based self-update, see release notes below), `class-i18n.php`, `functions-links.php`, `functions-version.php`

## Root-level debug/test scripts

The repo root has many `test-*.php`, `debug-*.php`, `enable-license-skip.php`, `register-license.php`, `emergency-moyashi-end.php` etc. — these are ad hoc manual-testing/ops scripts accumulated over time, not an automated test suite and not part of the shipped plugin (excluded from release ZIPs). Don't treat them as canonical usage examples of the plugin's API; check the actual `includes/class-*.php` for current behavior instead.

## Update system caution

The GitHub-based update checker (`class-updater.php`) has been rewritten and reverted multiple times (see `CHANGELOG.md` — 2.3.92→2.3.94 reverted a rewrite back to the "stable 2.3.91" logic, then 2.4.6 fixed duplicate filter hooks). If touching update-check logic, check `CHANGELOG.md` history first — this area regresses easily and has already round-tripped through several "fixes."

## Releases

GitHub-only release (no ZIP asset attached — `assets: []` is expected and verified as part of the release). Full procedure and the **required completion-report format** are in `.cursor/rules/release-workflow.mdc` — read it before releasing. Key points:
- Only release when the user explicitly asks; don't bump the version for a no-op request ("機能変更なしのリリース" must be stated explicitly if there's no functional change).
- Version bump: `news-crawler.php` + `readme.txt`. Commit (Japanese) → tag → push → `gh release create` (no asset) → `./create_release_zip.sh` (local ZIP only, not attached to the release) → verify via `gh release view {tag} --json zipballUrl,assets`.
- ZIP output: `~/Desktop/news-crawler_TEST_UP/news-crawler_{version}_{date}.zip`, extracted folder name `news-crawler`.
- Completion report must include: version (old→new), release type, GitHub Release URL, zipballUrl, confirmation `assets: []`, ZIP path, extracted folder name, commit hash.

## Commit messages

Always write commit messages in Japanese, concise form like `〇〇を修正（v3.1.6）` — never English one-liners.
