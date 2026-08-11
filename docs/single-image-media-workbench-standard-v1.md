# Single-Image Media Workbench Standard v1

Status: active product and engineering standard.

## Purpose

Use one compact, visually confirmed workbench for a single existing WordPress
image. The workbench may prepare format conversion, resizing, crop, watermark,
and an approved output filename, but it does not write or replace media.

The operator path is:

1. Open **Optimize this image** from the Media Library.
2. Choose format and optional crop or watermark template.
3. Optionally enter an output basename.
4. Generate and visually verify the short-lived derivative preview.
5. Confirm the replacement and rollback statement.
6. Submit one `adopt-cloud-media-derivative` proposal to Core.
7. Approve, execute, inspect evidence, or restore from the governed Core and
   Adapter path.

## Ownership

- Toolbox owns the contextual entry, convenience templates, browser preview,
  filename suggestion, and explicit operator confirmation.
- Cloud Addon owns signed transport and verified derivative delivery.
- Cloud owns derivative generation and short-lived artifact evidence.
- Core owns proposal, approval, preflight, audit, and final decision truth.
- Adapter and Abilities Toolkit own allowlisted execution, backup, reference
  repair, final filename sanitization/uniqueness, and rollback.

Toolbox must never rename files directly, update `_wp_attached_file`, create
attachment metadata, or call a WordPress media write route from this workbench.

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

## Filename Rules

The UI accepts an optional basename, not a path. It removes path separators,
control characters, unsafe filename punctuation, surrounding separators, and
an operator-supplied extension. The extension is derived from the verified
artifact MIME type.

The resulting `file_name` is proposal evidence only. The approved Toolkit write
ability remains responsible for final `sanitize_file_name`, MIME/extension
agreement, collision avoidance, and the actual uploads-relative filename.

Do not rename the physical file before Core approval, and do not present a
browser-generated filename as canonical WordPress truth.

## Replacement Versus Save As New

The current governed contract replaces the selected attachment main file while
recording backup, reference-repair, verification, and rollback evidence. The UI
must say this explicitly and require a confirmation before proposal submission.

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
- Preview verification is mandatory before the Core proposal button enables.
- Replacement confirmation is mandatory and does not itself execute a write.
- Keep optional crop and detailed watermark fields secondary to the quick
  choices.

## Verification

Static contracts must prove:

- contextual single-image routing does not restore a second top-level tool;
- templates expand to canonical text/image watermark fields;
- custom filenames are bound to the preview state and become `file_name` only
  in the governed adoption proposal;
- the exact Cloud Addon local artifact descriptor is reused;
- no Toolbox direct media write or save-as-new claim is added.

Release verification continues to follow
[Media Optimization Release Checklist](media-optimization-release-checklist.md).
