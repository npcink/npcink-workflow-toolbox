# Version and Evidence Identities

The source checkout, an installed WordPress site and the public package are
independent identities. Record which one was verified before making claims
about behavior. The observations below are dated **2026-11-27**; recheck when
using them for a release or an installed-site acceptance decision.

| Identity | Observed or recorded state | What to verify next time |
| --- | --- | --- |
| GitHub source | `master` was at `bbca46e7dad39497643072b76a6a8cf212bcdcf8` when this documentation work started. | Resolve the exact remote ref and record the tested SHA. A merge is source evidence, not an install or release. |
| Local WordPress (`magick-ai.local`) | The [installation handoff](article-credential-check-closeout-2026-11-27.md#evidence-and-installation) records a package built from `d805811ade6a17dac75b407f71ed5800d1f3a3ca`. A read-only follow-up found a real plugin directory and matched the bootstrap, admin renderer, credential checker and readable editor JS to that revision. | Record the installed package hash or compare its runtime inventory, then inspect the actual logged-in page and served assets. The sample match is not a full tree or browser check. |
| Public package (WordPress.org) | The plugin-information API reported `npcink-workflow-toolbox` version `0.6.0`. The [WordPress.org submission record](wordpress-org-submission.md) records SVN revision `3739544`. | Confirm the public stable tag, package and SVN identity independently. The version in a Git checkout's plugin header does not identify its contents or prove publication. |

The 2026-11-26 maintenance decision retains WordPress.org `0.6.0` for this
pass. Neither the Local receipt nor the public listing identifies a separate
production site install. Local smoke, fixture-backed browser checks, M4/Cloud
acceptance, production deployment and ordinary operator use must remain
separate claims. Follow the [release gate](wordpress-org-release-gate.md) and
obtain separate approval for any site update or public package release.
