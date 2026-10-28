# Local Site Knowledge automatic delivery — 2026-10-28

Scope: restore the existing Addon knowledge scan/flush through WordPress's
normal HTTP-triggered Cron on `magick-ai.local`. This is local development
configuration and acceptance evidence, not a distributed plugin scheduler,
production deployment, ranking improvement or global cleanup authorization.

## Diagnosis and bounded recovery

The previous editor-validation MU plugin defined `DISABLE_WP_CRON=true` for
every request. It remained installed after editor acceptance. The knowledge
reconciliation event was overdue even though source delivery and the Cloud
worker worked when invoked directly. The reconciliation cursor was already
`2026-10-16 12:17:16 / 282641`; a fresh query found no currently eligible
public sources beyond it. It was not reset or invented from the delivery clock.

The blanket guard was backed up and retired. A local MU plugin now uses core's
`pre_get_ready_cron_jobs` filter to admit only these existing hooks:

- `npcink_cloud_addon_reconcile_site_knowledge_changes`
- `npcink_cloud_addon_flush_site_knowledge_changes`

It filters due timestamps, respects an earlier array prefilter, fails closed
for another site URL and leaves the complete stored Cron option untouched.
It adds no timer, queue, option, daemon or execution controller. Normal page
requests use core's ordinary Cron spawn and lock. `DISABLE_WP_CRON` is no
longer defined by the retired file. Other explicit configuration defining it
would still need to be respected; this site's wp-config has no such setting.

Installed local path:
`wp-content/mu-plugins/000-npcink-local-knowledge-cron-scope.php`.
Retired path:
`wp-content/mu-plugins/000-npcink-local-validation-cron-guard.php`.
The exact scope source, old guard and SHA-256 switch receipt are retained in
Toolbox's primary ignored local evidence directory
`tests/artifacts/knowledge-cron-2026-10-28`. This file is intentionally not
shipped in Toolbox or Addon release packages.

This is a replacement of global pause with a bounded knowledge-only scope,
**not a restoration of all WordPress scheduled jobs**. Sixteen other scheduled
hook buckets stay stored and paused, including media backup, trash/auto-draft,
governance history and updater/privacy cleanup. Their deferred cleanup and
monitoring behavior remains a separate local environment concern. Simply
removing the scoped file would release those jobs on the next page visit and
must not be treated as harmless recommendation testing.

## Verification

- Isolated PHP scope checks cover due allowlist filtering, future timestamps,
  an existing empty prefilter, an unrelated prefilter and wrong-site rejection.
  The check source `knowledge-cron-scope-test.php` and exact scope source reside
  in the ignored evidence directory above and are hashed in its manifest. They
  are local acceptance evidence, not repository-distributed regression tests.
- A normal HTTPS home request returned 200 and automatically ran overdue
  reconciliation. `last_reconciled_at` became `2026-10-28T06:25:47+00:00`,
  `reconcile_overdue=false`, `wp_cron_disabled=false`, and the next hourly
  event was scheduled. No candidates were available beyond the preserved cursor.
- For the delivery check, the existing save handler was replayed for an existing
  public post 21764 and page 282641. It did not call a WordPress content save.
  The normal 180-second debounce was retained. After that deadline an ordinary
  HTTPS page request returned 200 and triggered the due core Cron, not direct invocation
  of the Addon flush callback or a named `wp cron event run` command.
- Run `run_a131863a8394429b80a26927928b971d` succeeded on clean Cloud
  `d32e95cec73167ba69ee1470616e5380e218a23f`: two documents, two chunks,
  zero failed/skipped/quota-skipped sources and zero deletions. The delivery
  buffer returned to zero / idle without an error.
- Public post/page coverage remains 1,999 documents / 2,217 chunks. The 2,340-row
  WordPress source fingerprint is unchanged. Excluding the two test sources,
  2,130 document rows and 2,362 chunk rows retain their exact row digests; all
  16 other Cron hook buckets retain their timestamps, payloads and recurrences.
- Exact runtime identity, source/corpus preservation and other
  Cron bucket comparison are recorded in the accompanying
  [evidence receipt](recommendation-knowledge-cron-evidence-2026-10-28.json).

## Limits, rollback and next work

WordPress pseudo-Cron still needs site requests; with Local, Docker or the
machine stopped it cannot promise continuous execution. No permanent machine
scheduler is added. This acceptance verifies the already-existing producer,
180-second scheduling, HTTP spawn and runtime delivery. It does not claim a
newly edited article save or a nonempty full-corpus reconciliation sweep.

To roll back this local scope without releasing cleanup, restore the backed-up
blanket guard first, then remove the scoped MU file. This disables automatic
knowledge delivery again while keeping stored jobs. Do not delete Cron events,
reset the reconciliation cursor, rebuild indexes or change article content as
rollback. An in-flight Cloud run must be observed to terminal before any repair.

The global pause no longer blocks knowledge updates. Remaining work is the
Toolbox 80-versus-1,000 request/response status comparison, then a reproducible
Eval Lab relevance comparison on the complete corpus. User involvement remains
actual use and final feedback, not per-article annotation.
