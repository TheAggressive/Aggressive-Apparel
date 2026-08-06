# Repository rulesets

`release-branches.json` is the reviewable source of truth for the `master`
branch. GitHub does not synchronize ruleset files automatically, so an
administrator must apply it and periodically check for drift.

## Apply or update

Resolve whether this is a create or update:

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

Before the first activation, confirm that every named status check has completed
successfully in the repository. To stage a first application without enforcing
it, create the ruleset as disabled:

```bash
jq '.enforcement = "disabled"' .github/rulesets/release-branches.json | \
  gh api --method "${RULESET_METHOD}" "${RULESET_ENDPOINT}" --input -
```

After staging a new ruleset, run the resolution block again so
`RULESET_ENDPOINT` includes its ID. Apply the committed active policy after the
checks exist:

```bash
gh api --method "${RULESET_METHOD}" "${RULESET_ENDPOINT}" \
  --input .github/rulesets/release-branches.json
```

This repository currently has one maintainer. Requiring an approval would make
the repository unmergeable without adding a bypass, so the policy deliberately
requires zero approvals. The pull request, current checks, resolved threads,
and merge restrictions remain hard gates; there is no administrative bypass.

## Enforced intent

- Pull requests and squash merges only.
- Zero required approvals while there is only one maintainer.
- All review conversations resolved.
- `CI Summary`, CodeQL, Actionlint, and Zizmor required on the current merge
  result.
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

1. Restrict deployment branches to `master`.
2. Do not configure a required reviewer while only one maintainer exists.

When a second active maintainer is added, raise
`required_approving_review_count` to one, enable code-owner and last-push
approval, add that maintainer as a production reviewer, and enable “Prevent
self-review.” Until then, the repository intentionally does not claim
separation of duties.

## Dependabot auto-merge

Keep **Settings → General → Pull Requests → Allow auto-merge** enabled. The
Dependabot workflow registers a squash auto-merge only after every check is
green. If another update reaches `master` first, the workflow updates the stale
Dependabot branch and waits for a fresh pipeline before registering its merge.
The branch ruleset independently enforces the current required checks, PR-only
history, and squash-only merge policy.
