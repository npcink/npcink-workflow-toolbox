# Static Analysis Standard v1

Status: active.

Purpose: define the shared PHPStan + PHPCS quality gates for Npcink
WordPress plugins, so every repository gets the same deterministic,
WordPress-aware static analysis before the advisory AI review runs. The
reference implementation shipped in `npcink-ai-client-adapter` PR #56
(advisory) and PR #62 (promoted to required); this standard records the
setup, the promotion rule, and the operational lessons.

It complements the AI Code Review Standard v1: static analysis is
deterministic and enforced in CI; the LLM review stays advisory.

## Standard Setup

Per repository (all dev-only, never shipped — `.distignore` excludes
`vendor/`, the configs, and `phpstan/`):

1. **Composer dev dependencies**
   - `phpstan/phpstan`
   - `php-stubs/wordpress-stubs` (version aligned with the repo's
     `Tested up to`)
   - `squizlabs/php_codesniffer`
   - `wp-coding-standards/wpcs`
   - `phpcompatibility/phpcompatibility-wp`
   - `dealerdirect/phpcodesniffer-composer-installer` (allow it in
     `config.allow-plugins`)
2. **`composer.json`**
   - A PSR-4 `autoload` section mapping the plugin namespace to its
     `includes/` tree for tooling only; the plugin runtime keeps its own
     autoloader and never includes `vendor/autoload.php`.
   - Scripts: `analyse:php` (`phpstan analyse --memory-limit=1G`) and
     `lint:standards` (`phpcs -d memory_limit=2G`).
3. **`phpstan.neon`**
   - `level: 5` to start; ratchet upward, never downward.
   - WordPress stubs via `parameters.bootstrapFiles`.
   - `ignoreErrors` entries must be narrow, commented with the reason,
     and counted per file path. An unmatched ignore entry is itself an
     error; prune entries when code moves.
4. **`phpcs.xml`**
   - `WordPress-Core` plus `<rule ref="PHPCompatibilityWP"/>` and
     `<config name="testVersion" value="<floor>-"/>` matching the plugin's
     `Requires PHP`. Without PHPCompatibilityWP the testVersion line is
     inert — the floor is not actually enforced.
   - Scope `<file>` to first-party runtime PHP; tests and tooling stay out
     until they can pass the same bar.
   - Document every `<exclude>` in a comment (for example, PSR class-file
     naming behind the autoloader).
5. **CI**
   - A dedicated job on a newer PHP than the floor (the floor stays
     covered by the main contracts job). It runs both gates plus the
     contracts suite, giving a forward-compatibility leg.
   - PHPStan's `--error-format=github` renders findings as PR
     annotations; PHPCS findings land in the job log.

## Promotion Rule

Gates ship **advisory** (`continue-on-error`) in the same PR that
introduces them. Promote to required by deleting the flags once every PR
since introduction has run both gates clean on master. Version pins in
`composer.lock` make later tooling bumps deliberate; a bump that surfaces
new findings is the ratchet working, not a regression of the rule.

## First-Run Experience

Expect a small real-defect haul plus mechanical noise; fix the real ones
in code and keep the code fixes in the introducing PR:

- Real defects found in the reference run: a stale `@param`, a
  pure-function assumption on a side-effecting DB insert (fix with
  `@phpstan-impure` plus a one-line rationale, not a suppression), loose
  typing on `filter_input` input constants, non-Yoda comparisons, a short
  ternary, a reserved-word parameter name.
- Mechanical noise goes through `phpcbf`. On string-pinned contract
  suites this reflows alignment that needles encode verbatim; either
  replay the needles against the reformatted source or apply the
  Provider Split Refactor Standard's portable-assertion-source step first.

## Operational Lessons

- **The generated stubs have gaps.** `ARRAY_A` and guarded bootstrap
  constants are not exported by `php-stubs/wordpress-stubs`; a small
  `phpstan/stubs.php` bootstrap with `defined()` guards closes them
  without touching the plugin.
- **PHPStan remembers pure return values.** An `INSERT IGNORE` retry path
  reads as unreachable unless the insert helper is marked `@phpstan-impure`;
  the annotation documents real semantics instead of hiding a finding.
- **Ignore entries are per-file.** `count` requires a single `path`; when
  a defensive guard moves to a new service during a split, split the
  ignore entry with it or the unmatched entry fails the gate.
- **Gate early findings in the same PR.** PHPStan's unused-method check is
  the cheapest dead-delegator detector during facade splits.

## Enrollment

| Repository | Status |
| --- | --- |
| `npcink-ai-client-adapter` | required since 2026-10-04 (PR #56, #62) |
| `npcink-workflow-toolbox` | advisory since 2026-10-04 (this file's reference setup; `analyse:php` uses `--memory-limit=4G` because the analysed tree is ~50k lines and 1G crashes the worker) |
| `npcink-governance-core` | pending |
| `npcink-abilities-toolkit` | pending |
| `npcink-cloud-addon` | pending |

Sibling repositories adopt this standard by following the reference setup
above; record the enrollment PRs in this table when they land.
