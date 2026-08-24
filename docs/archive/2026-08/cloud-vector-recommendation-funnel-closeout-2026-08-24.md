# Cloud Vector Recommendation Funnel Closeout - 2026-08-24

Status: time-bounded local and M4 candidate evidence.

This record closes the internal development slice for Cloud-backed related
articles, safe internal-link anchors, batch editor Apply, metadata-only
recommendation observation, and one real WordPress native-save acceptance. It
is not merge, M4 acceptance, production validation, user-value proof, or SEO
quality authority.

## 1. Product Outcome

The current WordPress-first loop is:

```text
WordPress editor context
  -> Addon transport
  -> Cloud Site Knowledge vector evidence
  -> Toolbox review and exact source-match safety
  -> editor Apply
  -> explicit WordPress native save
  -> metadata-only Cloud quality rollup
```

Cloud remains the semantic retrieval and read-only quality-detail owner.
Toolbox owns review controls and fail-closed editor application. WordPress owns
the final write. No component gained automatic save, publish, cross-post patch,
prompt mutation, or a second recommendation registry.

## 2. Functional Commits

- Cloud `54908d5b`: bounded internal-link anchor evidence.
- Cloud `51ef6d5d`: recommendation-session funnel rollups and impression-safe
  quality rates.
- Toolbox `5453e81`: bounded current-article multi-link editor transaction.
- Toolbox `e19357a`: recommendation impression/action/native-save correlation
  and real-save browser acceptance.

The candidate branches were not merged or promoted during this closeout.

## 3. Problems Closed

### Generic or invented anchors

Cloud anchor evidence is now preserved through the consumer path. Toolbox
rejects generic anchors and permits Apply only for a safe exact phrase in the
visible editor. Missing evidence stays copy/open-only; the target title is not
used as an automatic final anchor fallback.

### Hidden or premature WordPress writes

Selecting suggestions does not change editor content. Apply changes only the
visible Gutenberg state. The browser gate reads the database after Apply and
proves it is unchanged. Persistence occurs only after explicit WordPress
native save.

### Behavior events without a denominator

Related-article and internal-link result sets emit one metadata-only
impression session. Open, copy, ignore, Apply, save, edit, and undo correlate
to that session. Impression-only events are excluded from generic acceptance,
quality trend, scenario, and label-rate denominators.

### Apply and save using different session types

The first implementation recorded Apply against `recommendation_session` but
prepared save follow-up as `internal_link_apply_session`. The browser lifecycle
review exposed that Cloud would miss confirmed saves. Both now use the original
recommendation session; the private pending key remains local only.

## 4. Verification Evidence

| Evidence | Result |
| --- | --- |
| Cloud Agent Feedback and Site Knowledge API | 58 tests passed |
| Cloud Ruff and mypy | passed |
| Toolbox static contracts | 3531 passed |
| Toolbox translation contracts | 128 passed |
| Toolbox editor JS behavior | passed |
| Toolbox real REST artifact smoke | passed, sampled post unchanged |
| M4 focused Agent Feedback test | 14 passed on candidate source |
| Provider calls manufactured for performance | none |

Earlier repeatability sampling covered three articles, five rounds, and both
recommendation intents: 30 requests returned explicit retrieval and source
status while article snapshots remained unchanged. That proves local runtime
stability and no-write behavior, not recommendation quality.

## 5. Native-Save Browser Receipt

The acceptance copied published post `286721` into disposable draft `287478`.
The source post was read-only and the draft was deleted after the run.

- viewport: `1440x1000`;
- related articles: HTTP `200`, `cloud_vector_evidence`, `cloud_vector`, 5
  candidates;
- internal links: HTTP `200`, `cloud_vector_evidence`, `cloud_vector`, 8
  candidates;
- safe exact anchor: `WordPress 7.0`;
- applicable/applied: `1/1`;
- database after Apply: unchanged;
- Toolbox direct WordPress write: false;
- database after explicit native save: changed;
- save event: `internal_link_saved_unchanged` on the original recommendation
  session;
- Toolbox/Addon network errors: 0;
- known Gutenberg block-validation console errors: 9.

The console errors predated this acceptance and did not block the transaction,
but they remain follow-up debt rather than a green-console claim.

## 6. Development Lessons

1. Trace Cloud evidence through every projection before inventing a local
   algorithm. Most failures in this slice were contract or consumer-path
   problems, not vector-search failures.
2. Reject unsafe evidence instead of manufacturing a convenient anchor. A
   review-only candidate is a valid result.
3. Test state ownership at the database boundary. UI changes alone cannot
   prove that no backend write occurred.
4. Count exposure before interpreting adoption. Click totals without
   impressions are not actionable quality evidence.
5. Count successful save separately from Apply. Apply measures editing intent;
   native save measures persisted adoption.
6. Keep telemetry metadata-only. Random session IDs, bounded count buckets,
   status, action, and outcome are enough for the first funnel.
7. Use the browser smoke to catch lifecycle integration defects. It found both
   a misplaced helper function during development and the session-type gap
   that unit-level helpers alone did not prove end to end.
8. Separate evidence states. Local green, M4 candidate, merge, accepted M4,
   production, and real-user quality are different claims.

## 7. Next-Stage Stop Point

Feature development should pause here. The next useful work is natural,
consented internal-user observation, not another dashboard or ranking system.

- Start with the developer's own 3 to 10 articles to verify event integrity.
- Allow real internal users to contribute naturally; do not require a fixed
  number of articles per person.
- Review aggregate funnels only after at least 20 impression sessions per
  recommendation kind.
- Periodically turn a small, diverse sample into human-reviewed gold cases.
- Freeze the first 30-article gold set only when naturally accumulated.
- Do not tune ranking, prompts, or routing from one site or from Cloud
  candidates treated as truth.

Reopen product implementation only for a repeated real-user defect, a clear
funnel drop with adequate samples, or the separate merge/release lane.

## 8. Evidence State

- local verified: yes;
- M4 candidate validated: yes;
- merged into `master`: no;
- accepted on M4 from clean `master`: no;
- production validated: no;
- real-user recommendation quality validated: no.

`M4_OBSERVATION_RECEIPT date=2026-08-24; route=tailscale_private_relay; sync=not measured; focused=10.74s; promotion=not occurred; operations=sync:2,deploy:0; stable_502=not applicable; m4_only=not occurred; coordination=not occurred`
