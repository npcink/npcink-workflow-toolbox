# Processed image save as new v1

Operator-authorized scope, 2026-11-12.

Choose one existing WordPress image; compress/convert or watermark in Cloud;
compare original and verified result; explicitly request saving a new Media Library
image (default), or select the optional replacement of the same attachment. Format conversion belongs to compression. Quality is under More settings;
watermark templates are a separate peer task. The replacement checkbox is unchecked
by default. Automatic adoption, batch operations, broad orphan scans and a storage
manager are out of scope. The 2026-11-13 amendment below authorizes only recorded
seven-day replacement-file cleanup.

## Legacy backup settings removed — 2026-11-13

Operator-approved: remove the entire Image Processing backup-settings disclosure,
cleanup selector and save action, including empty containers and shared-backup
help text. This supersedes earlier instructions to retain that settings surface.
No replacement settings page is added. Existing stored options, per-record legacy
policies, the policy filter, Toolkit recovery and maintenance remain unchanged.
Seven-day replacement recovery/deadline, cleanup blockers and actual deleted bytes
remain beside the selected image. Watermark template management remains on demand.
See [PC verification and disposition ledger](legacy-backup-settings-removal-2026-11-13.md).

## Ownership and contracts

- Toolbox `POST /npcink-toolbox/v1/processed-image-preview`: manage_options,
  existing WP REST nonce; task `compression|watermark`, input only attachment_id,
  preferred_format, quality, watermark and watermark_attachment_id. Manual,
  preserve-dimensions processing only; compression rejects watermark fields;
  watermark requires its definition and forces original format. Existing Toolkit
  request planning and Addon signed transport are reused with the explicit
  `processing_purpose=save_as_new.v1` marker. Addon and Cloud validate the complete
  bounded request, including again before queued processing; old media jobs stay paused. Source and logo require
  object-level edit permission. Preview creates no WordPress attachments.
- Existing status, result and queryless authenticated local-review routes retain
  their contracts. The source id/fingerprint are returned on the preview receipt;
  the browser checks source checksum, task, expiry and decoded preview before
  enabling the save request. Changing processing controls invalidates the reviewed
  result; changing only the destination preserves it and starts no new Cloud run.
- The explicit save action submits either
  `npcink-abilities-toolkit/import-cloud-media-derivative` (default) or the existing
  `npcink-abilities-toolkit/adopt-cloud-media-derivative` (replacement) to Adapter
  proposals. Replacement passes `expected_current_media_fingerprint`, bound to
  the reviewed source. Toolkit schema and Adapter profile expose this existing
  runtime guard; source drift is rejected with 409 before adoption.
  Core retains final approval, preflight and audit. There is no extra planning
  ability: the single write ability's dry-run supplies its reviewable contract.
  Toolbox stops at the receipt/link, with honest pending copy. It never approves,
  executes or polls to execute. The choice of destination grants no write authority.
  The preview purpose remains `save_as_new.v1`; it identifies the bounded Cloud
  preview exception, not the eventual WordPress destination.
- Toolkit verifies the exact Cloud descriptor, source fingerprint, verified
  transfer evidence, bytes, checksum, MIME, dimensions and expiry. Only host-
  approved commit may create a distinct attachment and WordPress image sizes.
  With save-as-new, source bytes, sizes, metadata and article references remain
  read-only. With replacement, the same attachment ID gets the reviewed bytes,
  a backup, updated metadata and recognized article URL repairs through existing
  Toolkit adoption. Other uses of that attachment are affected. External hardcoded
  URLs and theme settings require separate review; the warning stays visible.

## Files and storage

WordPress's current uploads year/month directory contains a unique
`npcink-processed-<uuid>/result.<format>` per saved copy, plus its generated sizes.
Keeping an operation-owned subdirectory avoids thumbnail filename collisions and
bounds failed-import cleanup to newly created files. WordPress big-image scaling
is disabled only for this new attachment so its main file preserves reviewed bytes.

A synchronous nonblocking uploads lock serializes imports, and provenance on the
new attachment identifies an already saved artifact; repeated saves return its id.
The lock is one hidden file, not a job, lease or approval store. A changed/missing
saved result fails closed. Failed attachment deletion preserves its files and
returns recovery_required with the new attachment id.

Saving adds the result and WordPress sizes to site storage. A smaller delivered
image does not mean less server storage while the source remains. No unconfirmed
preview is inserted into the Media Library. Cloud owns preview expiry/cleanup;
there is no new Cloud or local job registry.

### Seven-day replacement recovery — 2026-11-13

The checked replacement explicitly adds `backup_lifecycle=temporary_7_days.v1`
to the existing adoption proposal. Toolkit records the original and metadata-owned
sizes as one frozen group in existing replacement history, retains them in place
for seven days and creates no duplicate original. Historical callers/backups keep
their existing directory, 30-day and manual/automatic policies.

Selecting an image reads the existing `list-media-backups` ability through Adapter.
Only managed groups show a deadline, restore request, cleanup bytes or exceptions.
Restore submits the existing `restore-media-backup` proposal; Core approval/preflight
remain mandatory. Restoring reuses original bytes and saved sizes/metadata, cancels
that group's cleanup, and gives the superseded processed version its own seven-day
window. It is not a batch restore or a new approval store.

Existing WordPress daily maintenance checks only frozen file paths after expiry.
It verifies uploads containment, regular non-symlink/non-hardlink files, identity,
size/checksum, current attachment metadata, other attachment/recovery uses and
local content/meta/options references. Queries that fail or exceed the bounded
candidate limit, changed files and deletion failures preserve files and visible
reasons. Cooperative adoption/restore/cleanup share a nonblocking uploads lock.
History persistence uses the previous value to avoid overwriting concurrent edits.
External sites, CDN copies and arbitrary plugin storage are not observable; review
external dependencies before replacing. This is conservative reference detection,
not proof of universal unreferenced status.

Old URLs continue to work during the grace period. WP-Cron may run late; expiry
ends restore eligibility, but does not promise immediate deletion. UI reports actual
deleted file sizes, not an estimate of physical filesystem savings; retained
exceptions mean no guaranteed total saving. No deletion outside recorded groups,
new queue, broad storage manager or Cloud runtime change is added.

Recognized sized article URLs are consolidated to the main image; restoring
returns them to the original main URL, not prior per-occurrence thumbnail URLs.
This is image recovery, not byte-exact post undo.

## Verification

Local source/installed candidate evidence, 2026-11-12:

- Toolbox, Toolkit, Adapter and Addon `composer test:all` pass; PHPStan and scoped
  coding standards pass. Toolkit changed-file level-4 ratchet passes with zero
  added findings (58 baseline/head findings; the new import trait has none).
- Cloud focused pause/worker tests pass: 118 tests on M4 candidate
  `412a7d96d1a2624d1e955e68ee3c61da5277ee76`, including
  both accepted tasks and unchanged rejection of old queued media jobs. Local media
  API/processor/coordination coverage has 136 passes, one unsupported-codec skip;
  the updated facade characterization and pause suite has 128 passes.
- Installed browser checks cover selection, folded quality, decoded original/result,
  desktop/mobile overflow, option invalidation, authenticated preview, proposal-only
  save, honest pending status and no automatic execution. Equal/larger compression
  results explicitly state that file size is not reduced.
- Real `magick-ai.local` → M4 Cloud → Adapter/Core → Toolkit checks pass for compression,
  text watermark and image/logo watermark. Each preview/proposal creates zero media;
  explicit test-administrator approval creates one distinct attachment. Main bytes
  and metadata match the reviewed artifact. Source bytes, pre-existing sizes and
  metadata remain identical; repeated execution returns 409. Disposable source and
  results are removed, while Core receipts remain evidence.
- Live receipts:
  - compression: Cloud `run_b3df3628c4814f1dadb62d9c95f19a47`, Core `3ca0a0ed-db2e-44d7-a8b2-607894546df5`.
  - watermark: Cloud `run_4f3a45d0bd624e7c91a3cbd5e4b94a75`, Core `4755e1aa-1f54-4a6f-acc2-3246f22189a8`.
  - logo: Cloud `run_59f59d992473412fa81a76e66fbc2706`, Core `59d0df31-3024-4be2-baac-5c79a299da0d`.


The initial real run returned `cloud_feature_paused`; the bounded purpose fixed
that integration. A later logo test used the wrong `scale` test field, was rejected
before dispatch, and passed with the existing `scale_percent` contract. These failed
receipts remain historical. M4 is a candidate; merged/accepted and production state
must be reported separately. This is no WordPress.org release.


## Optional replacement verification — 2026-11-12

Local unmerged source and installed-site evidence for the checkbox follow-up:

- Toolbox, Toolkit and Adapter `composer test:all` and their PHPStan gates pass;
  Toolbox changed PHP standards and Adapter standards pass. Browser evidence at
  1440px covers both tasks, unchecked defaults, default imports, checked adoption,
  source fingerprint, no regeneration on destination changes, visible warnings,
  honest pending approval and zero Toolbox approval/execution calls. The existing
  narrow-window smoke also passes.
- Disposable installed WordPress → accepted M4 Cloud preview → Adapter/Core →
  Toolkit replacement and governed backup restore pass. Same attachment ID,
  exact reviewed bytes, updated MIME/metadata, original backup, recognized article
  main/thumbnail repair, source fingerprint mismatch rejection and no preview or
  proposal writes are verified. Restore recovers original bytes, URL and MIME;
  article references resolve to the original main URL (the size consolidation
  limit above applies). Test fixtures and owned files are cleaned; Core receipts
  remain. Cloud `run_2bf6460819f241be897dd6d3989c799f`, replacement Core
  `73f76fd8-9f96-4a56-821c-c894682b8493`, restore Core
  `bc994564-9286-4eae-a131-181f4d15263f`.
- The first restore assertion expected byte-exact article text; inspection showed
  existing intentional thumbnail-to-main consolidation. A later concurrent
  browser fixture changed the global attachment inventory during the live check;
  isolated mutation tests pass. Failed receipts remain local history.
- This follow-up changes no Cloud or Addon runtime. M4 accepted Cloud revision
  remains `f17354f234d2852baa41a8b27e07bf73e081c5f7`; new plugin merge/CI and
  production remain separate states. No production deployment or WordPress.org
  release is performed by these tests.

## Temporary lifecycle verification — 2026-11-13

Installed disposable replacement/restore verifies unchanged attachment ID, no
extra original copy, exact original bytes and thumbnail metadata after restore.
Cloud `run_a14f21d5b93e4eae9da7f62ed89d0fe8`; replacement Core
`1ca78a0c-7607-4275-a4aa-ed8268677c9c`; restore Core
`e7858b24-f070-4cd5-8aed-2e5c5cd549d1`. Source drift still returns 409;
preview/proposal stages do not mutate media.

The installed maintenance fixture scopes the real public cleanup query to its
own attachment and restores the cursor. It covers active windows, read-only
preview, another recovery, shared attachments, theme references, changed bytes,
delete failures, filter redirection, retry, exact deleted bytes and idempotence.
Current files and historical manual backups survive; successful cleanup leaves
no frozen original/thumbnail files. Disposable fixtures are removed.

See [PC visual receipt](temporary-image-backup-visual-receipt-2026-11-13.md).
Local/plugin CI/merged/production are separate states; these tests perform no
production deployment or WordPress.org release.
