# Repository rulesets

`release-branches.json` is the reviewable source of truth for the `master`
branch. GitHub does not synchronize ruleset files automatically, so an
administrator must apply it and periodically check for drift.

## Apply or update

Resolve whether this is a create or update before staging the rules:

```bash
RULESET_ID="$(gh api /repos/TheAggressive/Aggressive-Apparel/rulesets \
  --jq '.[] | select(.name == "release-branches") | .id')"
RULESET_METHOD=POST
RULESET_ENDPOINT=/repos/TheAggressive/Aggressive-Apparel/rulesets
if [[ -n "${RULESET_ID}" ]]; then
  RULESET_METHOD=PUT
  RULESET_ENDPOINT="${RULESET_ENDPOINT}/${RULESET_ID}"
fi
```

On GitHub plans that support ruleset evaluation, stage the rules with:

```bash
jq '.enforcement = "evaluate"' .github/rulesets/release-branches.json | \
  gh api --method "${RULESET_METHOD}" "${RULESET_ENDPOINT}" --input -
```

GitHub currently reserves `evaluate` for Enterprise plans. On other plans, use
`disabled` for the first application, merge the workflows that provide every
required status check, and then update the ruleset with the committed active
payload:

```bash
jq '.enforcement = "disabled"' .github/rulesets/release-branches.json | \
  gh api --method "${RULESET_METHOD}" "${RULESET_ENDPOINT}" --input -

RULESET_ID="$(gh api /repos/TheAggressive/Aggressive-Apparel/rulesets \
  --jq '.[] | select(.name == "release-branches") | .id')"
gh api --method PUT \
  "/repos/TheAggressive/Aggressive-Apparel/rulesets/${RULESET_ID}" \
  --input .github/rulesets/release-branches.json
```

Do not activate the committed approval requirements while the repository has
only one eligible reviewer: GitHub does not allow an author to approve their own
pull request, and this ruleset intentionally has no administrative bypass.

## Enforced intent

- Pull requests and squash merges only.
- One independent code-owner approval, including approval of the last push.
- All review conversations resolved.
- `CI Summary` and CodeQL required on the current merge result.
- Signed, linear history.
- No force-pushes, deletion, or standing bypass actor.

The release process no longer commits generated versions back to `master`; it
tags the reviewed commit and stamps only the distributable artifact. Therefore
the publishing bot needs no branch-protection bypass.

## Drift check

```bash
gh api "/repos/TheAggressive/Aggressive-Apparel/rulesets/${RULESET_ID}" \
  --jq '{name, enforcement, conditions, bypass_actors, rules}'
```

Run this after repository-setting changes and as a scheduled administrative
control. `.github/workflows/ruleset-drift.yml` performs the same comparison each
Monday. If the default Actions token cannot read rulesets, configure a
`RULESET_AUDIT_TOKEN` secret with read-only repository-administration access. The
credential that applies changes must remain separate and deliberately approved.

## Production environment

Repository rulesets do not configure the `production` Actions environment.
Configure it separately under **Settings → Environments → production**:

1. Add at least one reviewer other than the author of the release change.
2. Enable “Prevent self-review.”
3. Restrict deployment branches to `master`.

With only one maintainer, the independent-review requirements cannot be met.
Temporarily weakening them is a governance decision, not a workflow change; the
repository should not claim separation of duties until a second reviewer exists.

## Dependabot auto-merge

Keep **Settings → General → Pull Requests → Allow auto-merge** enabled. The
Dependabot workflow registers a squash auto-merge only after every check is
green; the branch ruleset still withholds the merge until an independent
code-owner approves it.
