# ADR-010: Allow Strong Local Confirmation For Single-Article Image Adoption

## Status

Accepted.

## Date

2026-08-11.

## Context

Toolbox already lets an editor review external image-source candidates,
AI-generated image candidates, and existing Media Library attachments in the
current article editor. The previous boundary allowed a direct featured-image
write only for an existing attachment, only for an administrator, and only
when Governance Core supplied audit events. External and AI-generated images
had to become Core/Adapter/Abilities proposals before import.

That boundary protected against hidden and batch writes, but it also made a
simple present-editor transaction require several additional plugins. The
operator has already reviewed one exact image and its intended use. Importing
that one image and optionally setting it as the current article's featured
image is bounded, immediate, and compensatable.

The product should support a useful installation consisting only of Workflow
Toolbox and Cloud Addon for these single-article tools.

## Decision

Add a `strong_local_confirmation` write lane for single-article image adoption.

Toolbox may create one attachment and optionally set it as the featured image
when the complete eligibility contract in
`docs/single-article-editor-tools-development-standard-v1.md` is satisfied.
The editor must see the exact candidate and final fields and explicitly confirm
the action. Native WordPress capabilities remain the authorization source.

The local contract accepts only:

- `import_only`;
- `set_featured_existing`;
- `import_and_set_featured`.

The REST result is `single_article_image_adoption_result.v1`. It is not a Core
proposal, approval, or audit record.

Cloud remains runtime and artifact delivery only. Cloud and Cloud Addon do not
receive WordPress write authority. Toolbox performs the local WordPress write
under the logged-in editor's capabilities.

ADR-003 remains historical evidence for the original existing-attachment
proof, but its statement that no media import may use a local confirmation lane
is superseded by this ADR for the exact single-image scope above. ADR-006
continues to govern native editor commits and all batch/background exclusions.

## Alternatives Considered

### Require Core for every imported image

Rejected for this bounded editor-present shape. It duplicates the editor's
explicit review and prevents the intended two-plugin product from completing
ordinary single-article media work.

### Treat media import as native editor commit

Rejected. Attachment creation is immediate durable WordPress state and does
not wait for the article's normal Save or Update transaction.

### Let Cloud import or assign media

Rejected. That would make Cloud a WordPress write owner and violate the local
control-plane boundary.

### Allow arbitrary editor-side direct writes

Rejected. UI location alone is not authorization. Batch, background, external,
destructive, incomplete-preview, and multi-object writes remain outside this
decision.

## Consequences

- Workflow Toolbox plus Cloud Addon can complete external-image and AI-image
  adoption for one current article.
- Editors use native `edit_post` and `upload_files` capabilities; a new role or
  approval store is unnecessary.
- The implementation must own safe remote download, MIME verification, file
  limits, Cloud artifact integrity/ACK verification, and compensation logic.
- Paragraph and inline insertion remain editor-state changes and are persisted
  only by normal WordPress Save/Update.
- Batch media optimization, external Agent writes, background adoption, media
  replacement, and publication remain governed paths.
- Existing Cloud Addon signed-pull and delivery-ACK interfaces are reused for
  artifact-only AI generation results; Cloud and Addon source changes are not
  required. Ordinary external candidates continue to use bounded HTTPS URLs.
