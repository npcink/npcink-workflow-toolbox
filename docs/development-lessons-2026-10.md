# Development Lessons And Closeout - 2026-10

Status: closeout record for the 2026-10 systematic review, structural
refactoring, product delivery, and 0.4.0 release arc. Written as a
session-end summary of what worked, what was learned, and what remains.

## Arc Summary

The 2026-10 arc spans a systematic review (2026-10-03), nine Rest_Controller
cluster extractions (#185–#196), an editor JavaScript part-file split with
JED translation policy (#178, #179, #198), PHPStan/PHPCS enrollment and
promotion to required gates (#184, #200), live-site verification on a
standalone five-plugin WordPress install (#199), two new review-set product
features (#201, #202), and the 0.4.0 WordPress.org release (SVN revision
3730454).

## What Worked

### 1. The Split Standard (Provider Split Refactor Standard v1)

Every cluster extraction followed the same recipe, and it held up across
nine clusters and two languages:

1. **Make assertion sources portable first** (aggregate file lists, not
   single-file reads). This is the single most important step — without
   it, needle-pinned reformatting and extraction are impossible.
2. **Inventory dependencies** before cutting: which private helpers does
   the cluster use, which are shared, which are self-contained.
3. **Extract verbatim** behind one-line facade delegates. The facade keeps
   registration, permissions, and the scope map; the service gets the
   handler bodies.
4. **Pin the new structure** with static contracts: bootstrap-only load,
   delegate presence, no-route-registration invariant.
5. **One cluster per commit** (or per PR), always revertible.

The standard was written for the PHP Provider_Client split and applied
without modification to the Rest_Controller and JavaScript splits. It
generalizes.

### 2. Static Analysis As A Real Safety Net

PHPStan's advisory gate caught **four real runtime defects** across the
split series that neither the static contracts nor the behavior tests
covered (missing `use WP_Error` imports, undefined method calls on
extracted services, a shared helper left behind in the facade). The
advisory→required promotion path from the Static Analysis Standard worked
exactly as designed: run advisory through the refactoring series, fix the
real defects it surfaces, baseline the defensive noise, then promote.

The undefined-call audit gate (`test:editor-js-undefined`) caught the
exact class of bug the advisory review had found (calling a function that
moved to a part file without joining the namespace). Building it was
cheaper than a single production incident.

### 3. Just-In-Time Refactoring Over Speculative Refactoring

The decision to defer remaining JS clusters (image candidates, preflight,
progressive, draft flows) and the admin files until product work touches
them was the right call. The taxonomy/tag and internal-link review sets
were built in two sessions directly on top of the split architecture,
proving the structure delivers its promised value without needing to be
"finished" first.

### 4. The Review-Set Pattern

Five review sets shipped sharing one architecture: bounded sampling,
Cloud suggestion, suggestion-only admin panel, fail-closed without
Cloud, pii no-store data classification. After the second review set,
the pattern was a template; the fifth took one session end-to-end
(service + intent routing + admin panel + behavior test + static
contracts + translations).

## What Was Learned

### Needle Management

The hardest part of the phpcbf reformatting was not the 3,962 code
changes — it was the ~300 static-contract needle realignments. The
approach that worked: (1) build a smart bulk fixer that maps needle
variables to their source files, (2) use whitespace-collapsed regex
matching to find the new alignment, (3) verify each fix with `php -l`
before proceeding. The lesson: when you have string-pinned contracts,
formatting changes are a **contract migration project**, not a mechanical
cleanup.

### PHP Interpolation Safety In Contract Needles

When auto-fixing needles that contain PHP variables, three escaping
rules matter: `\$` in double-quoted strings is literal `$` (no
interpolation), `{$var}` interpolates, and `{\$var}` is literal
`{$var}` (no interpolation). Getting these wrong produces silent test
failures that look like missing strings. The fix: **never auto-escape
dollars** in needles that use `{$var}` interpolation; only escape in
needles that reference `$this->` or `$variable` as literal text.

### SVN vs Git Mental Model

WordPress.org uses SVN while this project uses Git. The release flow:
build a clean package with `rsync --exclude-from=.distignore`, checkout
or update the SVN working copy, replace trunk/, create tags/X.Y.Z/,
`svn add --force` new files, `svn ci`. The phar-based wp-cli and the
Plugin Check plugin need to be installed on a real WordPress site to
run the release verification — a standalone test site serves both this
and browser smoke testing.

## What Remains (Known Issues)

14 open items remain in `docs/known-issues.md`, categorized:

- **Product decisions** (not engineering debt): 16 editor intents
  convergence, onboarding tour, terminology standard.
- **Cross-repo** (other repositories own the resolution): Cloud Addon
  quota follow-ups, Operator Terminology Standard acceptance.
- **Translation** (standing policy, not debt): Chinese-source msgid
  migration file-by-file.
- **Structure (deferred by decision)**: remaining JS clusters, admin
  files, editor PHP service sub-division — all waiting for just-in-time
  triggers.
- **Verification**: the site-knowledge review UI smoke (pre-existing
  red, not in default gate); a Cloud-connected live pass for the
  internal-link batch no-HTTP-errors gate.
- **Ratchet**: PHPStan baseline (136 findings) shrinks as clusters
  split and cleanups land.

## Metrics

| Metric | Start (2026-10-03) | End (2026-10-06) |
|--------|--------------------:|-----------------:|
| Rest_Controller.php | 8,517 lines | 520 lines |
| Provider service classes | 15 | 24 |
| Editor JS parts | 1 (monolith) | 4 (main + 3 parts) |
| Static contracts | ~3,700 | 4,022 |
| CI gate stages | 43 | 45 |
| PHPStan/PHPCS | not enrolled | required |
| Review sets | 3 | 5 |
| WordPress.org version | 0.3.0 | 0.4.0 |
| PRs in this arc | — | ~20 |
