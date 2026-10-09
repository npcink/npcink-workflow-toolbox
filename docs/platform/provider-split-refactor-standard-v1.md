# Provider Split Refactor Standard V1

Status: active for future god-class splits in the Npcink WordPress plugins.

This standard records the method and lessons from the 2026-09-30 split of
`includes/Provider_Client.php` (10,037 lines, 234 methods) into ten
per-cluster service classes behind a facade, with unchanged public
signatures and zero behavior change (PR #156).

## Editor Split Sessions Addendum - 2026-10-08

Four sessions extracted seven clusters from `Rest_Editor_Content_Support`
(5398 -> 4136 lines) under this standard. Session-specific lessons that
generalize:

- **Load order is a hard dependency.** With no autoloader, an abstract
  base class file must be required BEFORE any child whose `extends`
  resolves at require time (the flow-cache base一度 loaded after the
  service - fatal on every plugin load until the review round caught
  it). Base first, static sub-services next, the facade service last.
- **Wire every list, not just the obvious ones.** Beyond the aggregation
  helper, this repo keeps a second source list in tests (the
  route-registration foreach), a runtime require block in the security
  smoke, and four behavior-test loaders. A moved class missing from any
  of them either fails loudly (good) or escapes the no-route-registration
  constraint (bad - the review round caught exactly this once).
- **phpstan-baseline.neon entries are path-pinned.** When methods move,
  re-path their baseline entries by block-walking the neon (message +
  path travel together); one entry per moved finding class, and verify
  against the actual analyser output because some findings in the same
  family belong to methods that stayed.
- **Dependency scans must be block-precise.** Line-range greps
  overestimated two writing-pack methods' cached dependencies; the
  per-method block extraction regex is the authority for what is pure
  enough to move.
- **eval-lab offline quality gate as the accelerated pre-publish
  evidence:** one command, no provider keys, run before every split
  publish (npcink-eval-lab project-review/run-quality-gate.php; its one
  recurring sk-marker flag is the redaction test's own fixtures).

## When To Use

Apply this standard before splitting any class whose methods are pinned by
the `tests/run.php` static contracts, which match exact source strings with
`strpos`, `substr_count`, and cross-method `preg_match` spans. A split that
ignores those needles will either break the contracts or tempt a session to
weaken them.

## Method

### 1. Make assertion sources portable before moving anything

The portability step itself must ship with a completion-marker guard on
the harness (see Static Analysis Standard v1): editing a test file's
header region is exactly when a split opener turns the whole suite into
vacuously-green plain text.

The first commit replaces every
`file_get_contents( 'includes/<Class>.php' )` assertion source with a
deterministic aggregation of the whole directory (sorted `glob`), keeping the
variable name. Needles then stop depending on which file a method lives in.

Before committing, replay every **negative** needle against the aggregated
text; aggregation widens the search surface and a forbidden string that only
appears in a sibling file will now fail.

### 2. Inventory needle sensitivity, not just needle text

Scan all needles for two classes that survive aggregation:

- **Receiver-pinned needles** contain `$this->helper(` or
  `'key' => $this->helper(`. They survive a move only while caller and
  callee stay in the same class, or while the callee is inherited.
- **Declaration needles** contain `private function helper` or a visibility
  keyword. Moving the method to a shared base flips visibility and breaks
  the needle; the honest fix is to update the visibility word and say so in
  the commit (this standard accepts that as non-semantic when the asserted
  behavior is unchanged).

Also inventory **span regexes** (`preg_match( '/function a\(.*?function b/s' )`).
Their two endpoint methods must stay in one file, in the original order, or
the span silently matches across concatenated files (or matches nothing and
the assertion passes vacuously). Pin the endpoints to the facade when in
doubt.

### 3. Choose inheritance for shared helpers, composition for clusters

When receiver-pinned needles reference a helper from more than one future
cluster, the helper must live in an abstract shared base that the facade and
every service extend. Composition (`$this->support->helper(`) rewrites the
pinned call sites and breaks the contracts. The trade-off is explicit: the
base is internal plumbing, never an extension point.

**PHP visibility rules that bite during the move:**

- `private` methods are invisible to child classes; shared base methods must
  be `protected`.
- `private` **class constants are also invisible to child classes**. Any
  constant referenced via `self::` from more than one class must move to the
  base as `protected const`, or every runtime reference fatals with
  `Undefined constant`.

### 4. Wire cross-cluster calls through the facade

Services hold one `Provider_Client $client` reference and call other
clusters only through facade methods. The facade keeps every public
signature as a one-line delegate and adds small public delegating bridges
for the internal helpers other services need. Services never reference each
other directly; the facade stays the single mediator and no method is ever
duplicated.

### 5. Commit one cluster at a time, lowest risk first

Each cluster extraction is one self-contained, revertible commit:
extraction file, facade delegates, bootstrap `require_once`, test-harness
updates, then the full gate. Order clusters so the ones with the fewest
cross-cluster edges move first; planning/method-family clusters move last.

### 6. Test harnesses that require class files directly need the full closure

Any test that instantiates the facade outside WordPress must load the
support base, every service, and the facade. Keep one shared loader file
(for example `tests/load-provider-client.php`) instead of per-test require
lists, so future services do not recreate the class-not-found CI failure.

## Verification Discipline (the hard lessons)

1. **Never pipe a gate through `grep`.** Running
   `composer test:all | grep -E "FAIL|passed"` makes the pipeline exit code
   the exit code of `grep`, which is zero whenever any line matches. During
   this refactor that masked every script failure after `tests/run.php`:
   more than thirty suite stages silently never executed for six cluster
   commits. Always run the gate to a file or terminal and check the
   composer exit code itself.
2. **A green static-contract runner is not a green suite.** `run.php` passed
   (3,617 assertions) while behavior tests that instantiate the real client
   fataled. The chain order means an early script failure hides every later
   stage.
3. **Run the cross-class visibility audit after every cluster commit, in
   both directions:** every `$this->service->method(` must target a public
   method on the owning service, and every `$this->client->method(` must
   target a public facade method. Both latent fatals found in this refactor
   (a private helper called cross-class, three missing facade bridges) sat
   on runtime paths the local suite never executes; CI behavior tests and
   this audit are the only nets that catch them.
4. **Reflection-based tests follow the method.** When a reflected method
   moves, the test's `ReflectionClass` **and** its instance source must both
   point at the new owner; updating only one fails at `invoke` with
   "not an instance of the class this method was declared in".
5. **Concurrent sessions in one worktree entangle commits.** Before a long
   multi-commit refactor, check `git status` freshness and `git log` HEAD
   against the session start snapshot. If another session is committing,
   create a dedicated branch in a separate `git worktree` and cherry-pick
   the first commit over; stage per-file (or per-hunk with
   `git apply --cached`) so foreign uncommitted edits stay untouched.
6. **Never run `phpcbf` on `tests/run.php`.** The standards gate excludes
   the contract runner for a reason: string-mutating sniffs (the WordPress
   capitalization rule) rewrite needle CONTENT, not just formatting. On
   2026-10-07 a phpcbf pass flipped `'wordpress'` to `'WordPress'` inside a
   passing needle while the source string stayed lowercase, so the suite
   failed on a needle the source never changed. Fix needles by hand against
   the real source text.
7. **Deleting handlers orphans helper families.** When intent retirement or
   a cluster split removes call sites, the private helpers behind them do
   not fail any gate — they just stop being reachable. Run a private-method
   reachability sweep from the public entrypoints and delete every
   unreachable family in the same change. The 2026-10-07 intent retirement
   found 24 additional orphaned methods this way after the visible
   branches were already gone; fixing them one suite failure at a time
   cost roughly twenty contract runs.

## Review Gate

Follow the advisory AI review standard. Findings that predate the refactor
and whose fix would change runtime behavior are recorded in the PR body and
deferred to a dedicated change; a zero-behavior-change refactor must not
absorb them silently.
