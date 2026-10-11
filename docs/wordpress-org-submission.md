# WordPress.org Submission Pack

Status: published. Version 0.6.0 was released to WordPress.org SVN as
revision 3739544 on 2026-10-11 (security and performance hardening:
object-level post-context authorization, Cloud URL scheme
normalization, editor post-type gating, first minified-asset package).
The WordPress.org API reports stable 0.6.0 and the live zip carries the
nine .min asset siblings with the sha256 audit log below. Version 0.4.0
was released as revision 3730454 on 2026-10-06 (five-review-set arc,
REST controller restructure, editor JS part-file split, static
analysis required gates).
Version 0.3.0 was released as revision 3723781 on 2026-10-02; the publication and verification record is
[`WordPress.org 0.3.0 Publication Closeout - 2026-10-02`](archive/2026-10/wordpress-org-publication-0-3-0-closeout-2026-10-02.md).
The 0.1.1 approval, publication, and zh_CN translation history is
recorded in the closeouts linked below.

The current release-readiness closeout is recorded in
[`WordPress.org Release Readiness Closeout - 2026-06-29`](archive/2026-06/wordpress-org-release-readiness-closeout-2026-06-29.md).
The post-approval publication and zh_CN translation closeout is recorded in
[`WordPress.org Publication And Translation Closeout - 2026-07-03`](archive/2026-07/wordpress-org-publication-translation-closeout-2026-07-03.md).

## Plugin Details

- Plugin name: Npcink Workflow Toolbox
- Suggested slug: npcink-workflow-toolbox
- Version: 0.6.0
- Requires at least: 6.9
- Tested up to: 7.1
- Requires PHP: 8.0
- License: GPLv2 or later
- Tags: ai, seo, editorial-workflow, media, content
- Development URL: https://github.com/muze-page/npcink-workflow-toolbox

## Short Description

Fixed AI workflow buttons for WordPress operators, with review-only suggestions and governed handoff plans.

## Reviewer Note

Npcink Workflow Toolbox is a review-only WordPress operator surface for fixed AI-assisted content and site-operations workflows. It returns suggestions, candidates, previews, and governed handoff plans. It does not publish posts, approve proposals, import media, create terms, update SEO metadata, mutate media metadata, or run a local workflow queue as part of the default content-support flow.

Cloud-backed features require a connected Npcink Cloud Addon or compatible host runtime. Npcink Cloud is the official Npcink hosted runtime service. The plugin does not include or hard-code a Cloud service endpoint; administrators connect the runtime through a companion connector or host-provided filters.

## Name And Ownership Note

The public name and slug intentionally use `Npcink` because this is the
official Npcink Workflow Toolbox product surface. If WordPress.org asks for
brand ownership clarification, reply with the official owner account or email
evidence, or request transfer to the official owner account before continuing
review. Do not rename the plugin in code unless the product owner decides this
is no longer an official Npcink listing.

The WordPress.org-facing `readme.txt` Contributors line must include the
submitting account `muze233` before upload.

## External Services Disclosure

The WordPress.org-facing `readme.txt` includes the external services disclosure for:

- Npcink Cloud runtime
- Unsplash
- Pixabay
- Pexels

Npcink Cloud service documents:

- Terms of Service: https://cloud.npc.ink/terms/en/terms.html
- Privacy Policy: https://cloud.npc.ink/terms/en/privacy.html
- Data Retention: https://cloud.npc.ink/terms/en/data-retention.html

## Directory Assets

Source files are kept in `wporg-assets/source/`. WordPress.org-ready PNGs are prepared in `wporg-assets/`.

Upload or copy these files to the WordPress.org plugin SVN top-level `assets/` directory:

- `wporg-assets/icon-128x128.png` -> `assets/icon-128x128.png`
- `wporg-assets/icon-256x256.png` -> `assets/icon-256x256.png`
- `wporg-assets/banner-772x250.png` -> `assets/banner-772x250.png`
- `wporg-assets/banner-1544x500.png` -> `assets/banner-1544x500.png`
- `wporg-assets/screenshot-1.png` -> `assets/screenshot-1.png`
- `wporg-assets/screenshot-2.png` -> `assets/screenshot-2.png`
- `wporg-assets/screenshot-3.png` -> `assets/screenshot-3.png`
- `wporg-assets/screenshot-4.png` -> `assets/screenshot-4.png`

These assets are intentionally excluded from the plugin release zip by `.distignore`.
Do not upload local metadata files such as `.DS_Store`.

## Release Package

Use the release zip generated from the intended release commit:

```bash
composer package:release
```

Packaging also runs `scripts/minify-assets.sh` on the packaged copy, so
the zip ships `.min.js`/`.min.css` siblings for the admin and editor
bundles next to the readable sources (the release zip measured ~1.04MB of
assets shrinking to ~636KB minified). The step requires `node` (terser
and clean-css-cli are fetched via npx at pinned versions and not
committed; the artifacts still come from the npm registry at release
time, so run releases from a trusted network); if it fails,
fix the tooling before submitting rather than shipping unminified assets
silently.

For a mixed worktree, generate the release package from a clean worktree or release tag so unrelated local edits do not enter the zip.

The accepted submission package path is:

```text
/Users/muze/gitee/npcink-workflow-toolbox/build/npcink-workflow-toolbox.zip
```

The package root must be `npcink-workflow-toolbox/`.

## Validation Commands

Before submitting the plugin zip:

```bash
composer check:wporg
composer test:all
```

Then run the Plugin Check package gate with the workstation environment
documented in the
[WordPress.org Release Gate](wordpress-org-release-gate.md)
(wp-cli.phar plus the Local site path and MySQL socket); the 0.3.0 publication
used:

```bash
SOCK="$HOME/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock"
WP_CLI=/tmp/wp-cli.phar WP_CLI_PHP=/opt/homebrew/bin/php \
WP_PATH="/Users/muze/Local Sites/magick-ai/app/public" \
WP_DB_SOCKET="$SOCK" composer plugin-check:release
```

## 0.6.0 Release Record (2026-10-11, SVN r3739544)

- Version PR #248 (header + constant + readme changelog, version
  assertions now compare the constant against the parsed header).
- `composer package:release` produced the first minified package:
  assets 1,042,163 -> 638,240 bytes with the per-bundle sha256 audit
  log in the build output; nine `.min` siblings verified inside the
  accepted zip.
- Plugin Check passed without errors on the five-plugin site
  (`~/wp-sites/npcink-five`, socket
  `~/Library/Application Support/Local/run/s63K4c8XP/mysql/mysqld.sock`);
  the only warnings are the pre-existing uninstall direct-query notices.
- Browser verification on the packaged build: the post editor loads all
  five editor scripts and the stylesheet from their `.min` siblings
  (SCRIPT_DEBUG off), the content-support sidebar mounts with the
  namespace present and zero console errors; the site editor loads no
  Toolbox bundle (post-type gating negative case); the admin page loads
  `admin.min.js`/`admin.min.css` and renders.
- The live API reports stable 0.6.0 and the downloaded
  `npcink-workflow-toolbox.0.6.0.zip` carries the `.min` set and the
  0.6.0 header.
- Trunk note: tags/0.5.3 predated the Summary_Terms/Writing_Pack/
  Progressive/Media_Alt editor splits, so this trunk update adds those
  include files alongside the 0.6.0 changes.
