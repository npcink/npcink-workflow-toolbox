# Editor Part JED Translation Policy v1

Status: active rule for splitting `assets/editor-content-support.js` into
part files.

This policy answers the question the editor content-support JS split was
waiting on: how do translated part files get their zh_CN catalog?

## Rules

1. **One handle, one catalog.** Every part file under
   `assets/editor-content-support/` that contains `__()` calls MUST have
   its own script handle and its own
   `wp_set_script_translations( <handle>, 'npcink-workflow-toolbox', languages )`
   registration. WordPress then prints the matching
   `languages/npcink-workflow-toolbox-zh_CN-<handle>.json` JED catalog
   immediately before that part's script tag, so even load-time `__()`
   calls inside the part (constant option tables) resolve.
2. **Parts without `__()` stay translation-free.** They must not gain a
   translations registration; the existing part-purity static contract
   enforces this (`text-utils.js`, `internal-links.js`).
3. **Exact per-handle coverage, checked mechanically.** The static gate
   extracts every literal `__( '...', 'npcink-workflow-toolbox' )` from a
   translated part and requires each msgid in that part's own JED, the
   same way the admin and editor-format handles are covered today. JED
   files stay hand-maintained with single-line arrays; the gate prints
   the exact missing list, so sync is mechanical.
4. **The main bundle catalog stays a superset during the split.** When a
   cluster moves out, its strings are copied into the part's new JED but
   are NOT pruned from the main bundle JED in the same change. Catalog
   pruning happens in one dedicated translation pass after the split
   completes, keeping per-cluster diffs reviewable and the po/pot/mo
   rebuild order (edit source -> po -> mo -> JED -> pot) unchanged.
5. **Msgids stay English** per the Translation Source Language Policy;
   new part strings never introduce new Chinese-source msgids.

## Verification Hooks

- `wp_set_script_translations` registrations: one per translated part,
  zero for pure parts, pinned by static contract on the enqueue source.
- Per-handle JED validity + spot labels + the mechanical literal
  coverage rule above are pinned in `tests/run.php`.
- Part load order stays governed by the explicit `PART_ORDER` list in
  `tests/editor-content-support-sources.mjs` and the enqueue dependency
  chain in `includes/Editor_Content_Support.php`.
