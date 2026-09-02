# Site Media Recommendation Editor Lessons v1

Status: active.

This document records the WordPress editor-side lessons from the external
image-source recommendation work. Cloud owns provider search and candidates;
the Toolbox owns the operator-facing interaction and local WordPress handoff.

## Intended Interaction

- Opening the AI recommended featured-image tool does not search automatically.
- The editor chooses a TAB, then clicks the search button.
- The source grid reserves nine positions so the modal does not jump as
  results arrive.
- TABs remain clickable while a request is running.
- Keyword chips show localized `display_label` values and search with the
  separate `search_query` value.
- Searching, completion, or keyword generation never clears the current
  selection. Only an explicit user selection changes it.
- Import and featured-image writes continue through the existing local
  confirmation and WordPress-owned path.

## Common Failure Modes

| Symptom | Likely cause | Check |
| --- | --- | --- |
| Only two images appear | old `per_page` default or `fast_first` result shown as final | inspect request/result count and completion state |
| Modal flashes or grows | variable grid or skeleton count | verify the nine-slot grid is rendered before results |
| English keyword labels | locale dropped in one layer | inspect WordPress config, visual context, and Cloud result |
| `[object Object]` or HTML entities | object suggestion not normalized before render | verify `display_label` and entity decoding |
| Search changes while changing keywords | keyword action reused the image-search handler | keyword generation must only update chips; chip click performs search |
| Same irrelevant images | mechanical title suffix or stale cache | inspect `search_query`, cache key, and provider order |
| Selected image disappears | request lifecycle clears selection | preserve selection through result and background completion updates |

## Trial Method

Use five to ten different real articles. Do not score every result or build a
formal benchmark. Ask one question: can a useful image be found quickly in the
first five to nine candidates? Keep a screenshot only when the result is
obviously unrelated, duplicated, unsafe, or missing attribution.

## Scope Boundary

Do not add image recognition, vector indexing, a local media registry, or a
second WordPress write path to fix a keyword problem. First improve bounded
query transformation and provider merging. Consider one explicit AI keyword
request only after deterministic search has been tried and only when quota and
fallback behavior are visible to the user.
