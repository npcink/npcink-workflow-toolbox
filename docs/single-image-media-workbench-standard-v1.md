# Single-Image Media Workbench Standard v1

Status: active product and engineering standard.

## Purpose

Use one compact, visually confirmed workbench for a single existing WordPress
image. The workbench may prepare format conversion, resizing, crop, watermark,
and an approved output filename. After exact visual verification and a second
explicit confirmation, it may replace that one attachment through the existing
Toolkit transaction with mandatory backup and rollback evidence.

The operator path is:

1. Open **Optimize this image** from the Media Library.
2. Choose format and optional crop or watermark template.
3. Choose custom, MD5, or time-based output naming; enter a basename only for
   the custom option.
4. Generate and visually verify the short-lived derivative preview.
5. Confirm the replacement and the automatic-backup statement. Backup is mandatory and cannot be disabled for this transaction.
6. Apply the verified derivative to the selected Media Library attachment.
7. Inspect the returned backup, reference-repair, and verification receipt. If
   needed, use **Restore original image** in the completed result area; the
   current optimized file is backed up automatically before restore. The backup
   is kept outside the Media Library and is not a second visible attachment.

## Ownership

- Toolbox owns the contextual entry, convenience templates, browser preview,
  filename suggestion, and explicit operator confirmation.
- Cloud Addon owns signed transport and verified derivative delivery.
- Cloud owns derivative generation and short-lived artifact evidence.
- Core remains the approval and audit owner for batch, background, delegated,
  external-client, or multi-object replacement.
- Abilities Toolkit owns local execution, backup, reference
  repair, final filename sanitization/uniqueness, and rollback.

Toolbox must never implement replacement mechanics, rename files directly, or
update `_wp_attached_file` or attachment metadata itself. Its local route may
authorize only `npcink-abilities-toolkit/adopt-cloud-media-derivative` or
`npcink-abilities-toolkit/restore-media-backup`, only for the current exact
request, and must remove that authorization in `finally`. Backup discovery is
read-only through `npcink-abilities-toolkit/list-media-backups`; Toolbox must
not create a parallel backup registry or enable restore before the selected
same-origin backup preview has loaded with non-zero dimensions.

## Watermark Templates

Templates are a bounded Toolbox convenience catalog. A template expands in the
browser to the existing canonical watermark object; Cloud does not receive a
Toolbox-only template id.

The built-in catalog is:

- no watermark;
- current Toolbox default;
- subtle bottom-right text;
- prominent bottom-right text;
- restrained bottom-right configured logo;
- custom values for the current run.

Toolbox also owns a bounded local user-template catalog. Administrators may
copy a built-in preset, create a text or logo template, rename it, edit its
canonical watermark fields, delete it, and choose one saved template as the
default. User templates are stored in a dedicated Settings API option, are
limited to twenty entries, and may reference only a local Media Library image
attachment for a logo. Built-in presets remain immutable.

Template management lives under **Image Handling → Watermark Templates**, not
inside the high-frequency single-image form. The manager shows preset and user
rows beside one immediate browser-side effect preview. It may use a temporary
Media Library image as the preview background, but that choice is not saved.
The Settings API return URL must preserve this exact tab and tool after save;
the page shows the standard save result instead of returning to Overview.
The current default renders on first paint, the custom-template count stays
visible against the twenty-template limit, and deleted templates remain
undoable until the settings form is saved. Missing or deleted logo attachments
fail closed and cannot remain the selected custom default.

Templates do not create a provider preset registry, workflow registry, or
execution policy. Logo templates may reference only the existing local
watermark attachment selector. Watermark remains off unless the operator picks
a template or an enabled Toolbox default.

The single-image workbench shows an immediate browser-side watermark effect
preview over the selected image. This preview is guidance only. The exact
Cloud-generated derivative remains the evidence that must load successfully
before local replacement can be confirmed. Custom watermark controls reveal
only the fields relevant to the selected text, logo, or off mode.

## Filename Rules

The single-image UI offers exactly three naming choices:

- custom basename;
- deterministic MD5 name derived from the current attachment revision facts;
- local date-and-time name captured when preview generation starts.

For a custom basename, the UI accepts a basename, not a path. It removes path
separators, control characters, unsafe filename punctuation, surrounding
separators, and an operator-supplied extension. The extension is derived from
the verified artifact MIME type. WordPress remains responsible for final
collision handling for every naming choice.

The resulting `file_name` is reviewed local-confirmation evidence only. The
Toolkit write ability remains responsible for final `sanitize_file_name`, MIME/extension
agreement, collision avoidance, and the actual uploads-relative filename.

Do not rename the physical file before the final local confirmation, and do not
present a browser-generated filename as canonical WordPress truth.

## Replacement Versus Save As New

The current strong-local-confirmation contract replaces the selected attachment main file while
recording backup, reference-repair, verification, and rollback evidence. The UI
must say this explicitly and require confirmation after exact preview verification.

Do not offer **Save as new media item** until Abilities Toolkit defines a
separate governed create-attachment ability for a verified derivative artifact.
That future ability must define attachment creation, metadata generation,
duplicate handling, artifact expiry, compensation cleanup, and audit evidence.
Toolbox must not approximate it with direct upload, filesystem copy, or the
external-image adoption exception.

## UX Rules

- Keep one Media Library optimization entry; do not add separate inline
  conversion, watermark, and rename buttons.
- Enter the single-image workbench only when exactly one attachment is supplied
  by the Media Library action.
- Keep batch planning as the default Image Handling surface for zero or multiple
  selected attachments.
- Preview verification is mandatory before **Apply to Media Library** enables.
- Replacement confirmation is mandatory and the final button click performs
  the one bounded local transaction.
- The original-image backup is created automatically for every replacement;
  the operator is not asked to choose whether to back it up, and Toolbox does
  not expose a disable-backup option.
- Restore is available only from the completed single-image result while the
  selected backup file is still within retention. Expired backups remain in
  history as summaries but cannot be restored.
- Image Handling exposes one simple retention choice: **30 days (recommended)**
  or **90 days**. Toolbox stores this local setting and projects it to Toolkit;
  no Cloud or Core setting is involved.
- Keep optional crop and detailed watermark fields secondary to the quick
  choices.
- Opening **More settings** reveals only compact filename, watermark-detail,
  and crop summary rows. Expand at most one row at a time.
- Keep the watermark template in the default settings view and translate every
  watermark-position and crop-anchor label for the active locale.
- Keep template creation and maintenance in the dedicated watermark-template
  tab. The single-image workbench only selects a default or one-run template.
- Keep the original and generated preview in the same comparison column.
- Batch mode retains Core proposal wording and behavior.

## Verification

## Recovery comparison UI rules

The completed single-image recovery view is a focused visual confirmation
surface, not a second settings page. Keep these rules stable when adjusting
the layout:

- The current attachment is always the left comparison subject; the retained
  original backup is always the right comparison subject. Use the same order
  in side-by-side cards and in slider labels.
- Expose only two top-level comparison modes: **Side-by-side comparison** and
  **Slider comparison**. The former shows the two complete cards; the latter
  shows one shared canvas with a draggable divider.
- The slider-only **Backup original / Current image** buttons belong beside the
  mode buttons and must be hidden in side-by-side mode. They set the slider to
  100% or 0% respectively and must visibly update the active state.
- Do not place labels, filenames, metadata, or actions over image pixels. Card
  headings and **View large image** stay outside the image frame; compact
  metadata sits below the image.
- Empty result containers must be `display:none` and must not retain borders,
  padding, minimum height, or background. Only actionable warning/error
  feedback may allocate a result row.
- A top-level Toolbox tab is mutually exclusive. Activation must update
  `is-active`, the shared active class, `aria-selected`, `aria-current`, and
  panel `hidden` state together; stale active classes are not allowed.

These rules came from repeated operator visual checks: controls that overlay
image pixels reduce confidence, empty result shells consume vertical space,
and stale active classes make two top-level surfaces appear open at once.

Static contracts must prove:

- contextual single-image routing does not restore a second top-level tool;
- templates expand to canonical text/image watermark fields;
- custom filenames are bound to the preview state and become `file_name` only
  in the exact local-confirmation request;
- the exact Cloud Addon local artifact descriptor is reused;
- the request-scoped filter can authorize no other Toolkit ability;
- batch mode remains Core-governed and no save-as-new claim is added.

Release verification continues to follow
[Media Optimization Release Checklist](media-optimization-release-checklist.md).
