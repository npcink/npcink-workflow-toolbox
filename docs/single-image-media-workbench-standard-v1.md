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
5. Confirm the replacement and rollback statement.
6. Apply the verified derivative to the selected Media Library attachment.
7. Inspect the returned backup, reference-repair, and verification receipt;
   restore through the existing Toolkit recovery path if necessary.

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
authorize only `npcink-abilities-toolkit/adopt-cloud-media-derivative`, only for
the current exact request, and must remove that authorization in `finally`.

## Watermark Templates

Templates are a bounded Toolbox convenience catalog. A template expands in the
browser to the existing canonical watermark object; Cloud does not receive a
Toolbox-only template id.

The initial catalog is:

- no watermark;
- current Toolbox default;
- subtle bottom-right text;
- prominent bottom-right text;
- restrained bottom-right configured logo;
- custom values for the current run.

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
- Keep optional crop and detailed watermark fields secondary to the quick
  choices.
- Opening **More settings** reveals only compact filename, watermark-detail,
  and crop summary rows. Expand at most one row at a time.
- Keep the watermark template in the default settings view and translate every
  watermark-position and crop-anchor label for the active locale.
- Keep the original and generated preview in the same comparison column.
- Batch mode retains Core proposal wording and behavior.

## Verification

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
