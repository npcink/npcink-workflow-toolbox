# Single-Article Editor Tools Development Standard v1

Status: active.

Date: 2026-08-11.

## Purpose

This standard turns the recent editor-tool discussions and the existing
Toolbox implementation history into one development rule for small,
single-article, visibly confirmed features.

The target product shape is a useful two-plugin installation:

```text
npcink-workflow-toolbox + npcink-cloud-addon
```

Toolbox owns the WordPress editor interaction and the bounded local write.
Cloud Addon owns signed transport to hosted runtime. Cloud may generate or
retrieve candidates, but it never imports media, changes a post, or becomes a
second WordPress control plane.

## Historical Lessons

### 1. Feature names do not justify new plugins

Image selection, paragraph review, paragraph splitting, and similar utilities
share one operator job: improve the article currently open in the editor.
They belong in the existing Content Support surface unless they acquire an
independent lifecycle, heavy dependency, data owner, channel, or commercial
boundary.

### 2. A durable write is not automatically a governance workflow

Earlier contracts treated nearly every media write as
`core_proposal_required`. That was safe but too coarse for one present editor,
one fully previewed image, one current article, and one reversible action.

The correct discriminator is the execution shape:

- who initiated it;
- whether the actor is present;
- whether the exact source and final effect are visible;
- whether the target is one current article and one image;
- whether failure can be compensated locally;
- whether execution is immediate rather than delegated, scheduled, or batch.

### 3. Native editor state and local media adoption are different lanes

Paragraph splitting, block insertion, excerpt changes, and similar visible
editor-state changes remain `native_editor_commit`: WordPress persists them
only when the editor later uses the normal Save, Update, or Publish action.

Media import and featured-image assignment are immediate WordPress writes.
They therefore require `strong_local_confirmation`, capability checks, exact
preview, and compensation, but not a second approval system when every bounded
condition in this standard is satisfied.

### 4. Cloud output is a candidate, not write authority

An external-source URL or a short-lived AI-generated Cloud artifact is
candidate evidence. The local editor chooses whether to adopt it. Cloud and Cloud Addon do not decide
the target post, create the attachment, set media fields, assign the featured
image, or publish the article.

### 5. Security is part of the product boundary

“No complex permission management” means using WordPress's existing roles and
capabilities instead of inventing a new approval store. It does not mean
skipping authorization, CSRF protection, remote-download validation, MIME
inspection, size limits, or rollback.

### 6. Separate product availability from candidate availability

Installing Toolbox and Cloud Addon is sufficient to expose the two-plugin
product path, but it does not manufacture Cloud candidates by itself. Candidate
availability has a separate runtime dependency:

- external image search requires a healthy Cloud connection and a configured
  image-source provider;
- AI image generation requires a healthy Cloud connection, an enabled hosted
  image-generation profile, and a configured upstream generation provider;
- existing Media Library selection, paragraph splitting, and other local
  editor tools do not require a live Provider call;
- adoption of an already returned candidate remains a local WordPress action.

Npcink AI Cloud already owns the hosted runtime execution and short-lived media
artifact contracts. Cloud Addon already owns request signing, artifact pull,
and delivery acknowledgement. Toolbox must consume those existing seams rather
than add Provider credentials or a second transport client. If Cloud cannot
produce a candidate because its provider/profile is unavailable, report that
as runtime configuration or availability—not as evidence that a third
WordPress plugin is required.

## Two-Plugin Capability Matrix

| Operator action | Toolbox only | Toolbox + connected Cloud Addon | Additional condition |
| --- | --- | --- | --- |
| Split/reorder/format the current article | Yes | Yes | Saved later by native WordPress Update/Publish |
| Select an existing Media Library image | Yes | Yes | Actor can edit the post and read the attachment |
| Import an already reviewed HTTPS image | Yes | Yes | Actor has `upload_files`; safe URL and image checks pass |
| Search external image sources | No hosted candidates | Yes | Cloud image-source provider is configured and healthy |
| Generate AI image candidates | No | Yes | Cloud image-generation profile/provider is configured and healthy |
| Import a reviewed Cloud AI artifact | No artifact source | Yes | Signed pull, integrity verification, and ACK all pass |
| Set the current article featured image | Yes | Yes | Exact action is locally confirmed |
| Batch/background/media replacement | No | No | Keep the governed Core/Abilities/Adapter path |

This matrix answers installation scope, not commercial entitlement. Cloud may
still reject a real request for missing credentials, disabled capability,
quota, entitlement, or provider health. Those are Cloud runtime outcomes and
must remain visible as such.

## Two Write Lanes

### Native editor commit

Use this lane when the final change remains visible and editable in the open
editor until the normal WordPress save transaction:

- split or merge one paragraph;
- insert a selected image block into the current article;
- apply reviewed title, excerpt, ALT, or block text to editor state;
- reorder or format current article blocks.

Toolbox must not add a hidden `save_post` executor for this lane.

### Strong local confirmation

Use this lane for one immediate, reversible local media adoption transaction:

- import one reviewed external image into the Media Library;
- import one reviewed AI-generated image delivered through Cloud Addon;
- set one reviewed existing or newly imported attachment as the featured image
  of the current article;
- update the new attachment's title, ALT, caption, description, attribution,
  and source metadata as part of the same adoption transaction.

The result artifact is `single_article_image_adoption_result.v1`.

## Eligibility Contract

Every strong-local-confirmation image adoption must satisfy all conditions:

1. Request source is the WordPress post editor.
2. The logged-in actor is present and clicks the final action.
3. The actor can edit the current post.
4. Import additionally requires `upload_files`.
5. Exactly one current post and one image candidate are targeted.
6. The preview shows the selected image, source, license-review status, final
   media fields, and whether featured-image assignment will occur.
7. The request contains an explicit confirmation value bound to the action.
8. Execution is immediate and synchronous; there is no queue, retry worker,
   callback apply, cron path, or external Adapter entry.
9. Remote downloads use WordPress safe HTTP validation, HTTPS, bounded
   redirects, bounded response size, and server-side image MIME verification.
10. A failed combined import-and-feature action deletes the newly created
    attachment and restores the previous featured image.

If any condition is absent, fail closed. Do not silently fall back to a broader
write path.

## Allowed Actions

The first contract allows three action values:

- `import_only`: create one attachment and return its editor-ready projection;
- `set_featured_existing`: set one existing image attachment as the current
  post featured image;
- `import_and_set_featured`: import one image and set the new attachment as the
  current post featured image in one compensated transaction.

For paragraph and inline-image entry points, `import_only` is followed by a
native editor-state insertion of a `core/image` block. The article content is
not persisted until the editor saves it.

## Remote Image Rules

- Accept `https` only.
- Use the reviewed candidate download URL, never an arbitrary URL supplied by
  a hidden background caller.
- Cap the response at 10 MiB and the decoded dimensions at 40 megapixels.
- Allow JPEG, PNG, WebP, GIF, and AVIF when the active WordPress installation
  recognizes the type.
- Verify the bytes server-side; URL extensions and response headers are not
  sufficient.
- Sanitize and uniquify the final filename through WordPress upload handling.
- Preserve stock attribution and `download_location` evidence when supplied.
- Never expose Cloud credentials or signed headers to browser-visible result
  data.
- AI generation uses `image_generation_result.v1` artifact references rather
  than provider URLs. Toolbox must use Cloud Addon's named signed-pull and
  delivery-ACK methods, verify artifact id, expiry, MIME, byte length,
  checksum, dimensions, and ACK evidence, and reject legacy provider URL or
  Base64 result bypasses.
- Preview and adoption are independent verified transfers. Preview bytes may
  exist only in request-scoped browser memory; they are not an attachment or a
  durable local cache. Final adoption pulls and verifies the artifact again.

## Permission Model

Use native WordPress capabilities:

- `edit_post` for the current article;
- `upload_files` for a new attachment;
- an existing attachment must be an image and readable by the current editor;
- REST cookie authentication and `X-WP-Nonce` protect the request.

Do not require `manage_options` merely because the action is implemented by
Toolbox. Editors and authors with the appropriate WordPress capabilities may
use the feature.

## Rollback And Evidence

No custom table or approval store is allowed. WordPress remains the durable
truth through the post, attachment, author, timestamps, and ordinary media
metadata.

The response includes a bounded receipt with actor, post, attachment, source,
previous featured attachment, action, and rollback status. When the operation
creates an attachment and a later step fails, the attachment must be deleted.
When featured-image assignment changes and completion fails, restore the prior
featured image or remove the new assignment.

## Hard Stops

This contract does not authorize:

- multiple images or multiple posts;
- background, scheduled, CLI, Cloud callback, Adapter, or Agent execution;
- automatic publishing;
- replacing an existing attachment file;
- overwriting existing attachment metadata outside the newly created
  attachment;
- taxonomy, settings, permissions, SEO fields, or unrelated post metadata;
- retries after the editor leaves the page;
- a Toolbox queue, run table, approval store, or audit control plane.

Those operations keep their existing Core/Abilities or separately reviewed
boundary.

## Verification Gate

Every implementation must include:

- static route and boundary-contract tests;
- classifier coverage for single-image import;
- WordPress behavior smoke for existing attachment featured assignment;
- WordPress behavior smoke for remote import and combined rollback;
- editor JavaScript coverage proving imported media is inserted only into
  visible editor state for paragraph/inline use;
- PHP lint and `composer test:all` before closeout.

## Local Test Lanes

Use the smallest lane that answers the current risk question.

### Source and contract closeout

```bash
composer test:all
```

This is the required repository gate and consumes no Provider credit.

### WordPress adoption behavior

```bash
composer smoke:single-article-image-adoption
composer smoke:ai-image-artifact-adoption
```

These smokes use deterministic local fixtures. They prove confirmation,
capabilities, HTTPS import handling, artifact integrity, independent preview
and adoption pulls, attachment creation, featured-image assignment,
provenance, compensation, and cleanup without a real Cloud or Provider call.

### Toolbox-to-Addon transport integration

```bash
composer smoke:ai-image-cloud-addon-transport
```

This loads the real Cloud Addon helper/client code but intercepts HTTP with
deterministic responses. It proves the request sequence
`runtime execute -> artifact download -> delivery ACK`, does not persist test
credentials, and consumes no Provider credit.

### Browser interaction

```bash
composer smoke:editor-image-recommendation-browser
```

This lane requires the repository's Playwright dependency and browser runtime.
A missing Playwright installation is an environment limitation, not a reason
to install new production dependencies or weaken the PHP/contract gates.

### Real Cloud candidate acceptance

Run a credit-consuming acceptance only when the operator intentionally wants to
verify the configured Cloud provider/profile. Record the Cloud configuration,
quota/entitlement state, returned artifact contract, and cleanup outcome. Never
turn this optional acceptance into the default local test or hide a Cloud
configuration failure behind mocked success.

## Reusable Development Heuristics

1. Classify by execution shape, not by a broad noun such as "media" or
   "write".
2. Keep visible editor-state mutations separate from immediate durable writes.
3. Reuse native WordPress capabilities before designing roles or approval
   tables.
4. Bind confirmation to the exact action and exact candidate.
5. Treat all external URLs, artifact metadata, headers, and bytes as untrusted.
6. Preview and final adoption must not share unverifiable cached bytes.
7. Compensate a partially completed combined action before returning failure.
8. Preserve source, attribution, license-review, and artifact provenance.
9. Keep Cloud as candidate/runtime owner and WordPress as final write owner.
10. Add a new plugin only when an independent lifecycle, data truth,
    dependency, channel, or commercial boundary genuinely appears.
