# WordPress MCP Adapter Approval Hook - Upstream Proposal Draft

Status: draft for the platform operator to review and post. Do not post to
an upstream repository without explicit operator approval.

Target: the official WordPress MCP Adapter repository / WordPress core AI
team discussions channel.

Suggested title: "Proposal: an approval/consent hook for write-class tools"

## Draft Body

The MCP adapter exposes Abilities as MCP tools authenticated as a WordPress
user. That makes AI-assisted reads straightforward, but write-class tools
execute immediately under the credential. The current guidance is to avoid
exposing destructive abilities and to use limited users, which pushes the
problem onto capability scoping and operator discipline.

We run a WordPress site where AI clients operate under a governed loop:
every write-class action is proposed server-side, a human approves it in the
WordPress admin, a commit preflight runs, and only then does an allowlisted
executor run the ability. All of it is audited. We built this as a plugin
stack over the Abilities API, and it works well for REST-channel clients.

We would like MCP-exposed tools to participate in the same loop instead of
bypassing it, and we think a small upstream hook could enable that for
everyone:

1. Ability-level metadata such as `approval_required` alongside the existing
   permission callback, so a tool can declare "this write needs a human
   approval step before execution".
2. A filter or interface the host can implement to intercept a
   write-class tool call and return a pending/approved/rejected decision,
   for example `wp_mcp_tool_pre_execute( $tool, $input, $user )`.
3. A standard "deferred" response shape for MCP tool results so clients can
   poll or resume after the human decision, instead of every plugin
   inventing its own.

With those primitives, governance plugins can become filters over the
official adapter rather than parallel MCP surfaces, and site owners get one
consistent approval story regardless of which client connects.

We are happy to contribute a reference implementation of the approval
filter side (proposal store, admin approval UI, commit preflight, audit) if
there is interest in the approach.

## Posting Notes

- Post as a discussion first, not a PR; align with the WordPress AI team's
  preferred channel before drafting code.
- Include a short demo of the governed loop (Core proposal timeline) if
  asked.
- Do not link private site URLs; describe the stack generically.
