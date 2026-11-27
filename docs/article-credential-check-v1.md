# Article Credential Check v1

Toolbox provides two local, deterministic checks for likely credentials in
WordPress article content:

- **Current article:** Check credentials under Article check scans the edited
  title, excerpt, serialized body and loaded block attributes. Its report
  shows only rule types and locations. Each reviewed plain-text value in a
  supported, editable block can be replaced with a fixed placeholder. Other
  matches, including key files, connection strings, encoded text and link
  attributes, require manual edits. The author reviews, undoes or saves with
  WordPress. The native first-publish confirmation panel warns without blocking.
- **Site scan:** Toolbox Start contains the administrator-only scan of saved
  article titles, excerpts and bodies, collapsed by default. There is no separate Articles menu item; a
  legacy bookmark redirects administrators to the expanded Toolbox section.
  The scan reads bounded batches of published, scheduled, draft, pending and
  private posts, reports only the post ID, edit link, field and finding categories, and
  never sends content to Cloud. A stopped scan is explicitly partial and can be
  resumed. Oversize or inaccessible posts are counted as skipped.

The shared rules intentionally cover a small set of recognizable key, token,
PEM private-key and password-bearing connection-string shapes. Those rules
pause covered Cloud requests. Generic password, key or token assignments have
less evidence; they are marked advisory, never automatically replaced and do
not prevent a Cloud request. No broad randomness scan or live validation is
performed. A clean result
means only that these local rules found nothing; it is not a general privacy or
security certification. The implementation does not inspect media pixels,
comments, revisions, custom post types, user data or external services.

If a credential has already been exposed, revocation or rotation at the
issuing service comes before replacing the article text. Replacing the visible
value does not invalidate an already-issued credential.

Article check and body FAQ pause on a known credential before browser submission
and at the corresponding WordPress REST dispatch boundary. WordPress also checks
the final projected FAQ body or the derived article search queries and validated
review block text, before the first outbound call. This catches matches made
contiguous by the text conversion. The error contains no
matched value. **Review and fix** lists locations and types from the current
request fields (body only for FAQ), without repeating the credential. The author
can replace individual unambiguous values or return to the editor to make manual
changes. The browser rechecks the current request fields and offers an explicit
continue action only when its rules no longer match. Retrying rebuilds the request
from the latest unsaved article and runs both guards again; fixing a finding does
not send a request, save, or publish. A server-only hit needs a source edit before
the browser offers another attempt. This is not a catch-all guard on other editors, other intents,
third-party plugins or Site Knowledge delivery. The addon is unchanged.

The site scan checks the three saved fields independently, including their
plain-text projection, at most ten posts per request and 512 KiB combined per
post. A post ID ceiling limits a run to existing records; it is not
a consistent snapshot of content while edits are in progress. Progress and
findings exist only in the open browser tab. No scan runs on its own, no results
are persisted, and the scan never edits posts. For rollback, remove only this
feature's change; there are no scan options, jobs or tables to clean up.
