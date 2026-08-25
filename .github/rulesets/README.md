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
successfully in the repository. In particular, merge `pr-policy.yml` and let its
`PR Policy` check complete before adding that context to the live active
ruleset. To stage a first application without enforcing it, create the ruleset
as disabled:

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
- `CI Summary`, `PR Policy`, Actionlint, and Zizmor required on the current merge
  result and bound to the GitHub Actions App that produces them.
- Native CodeQL merge protection requires a CodeQL result and blocks every
  alert severity. This replaces the fragile workflow-job-name status context;
  the advanced CodeQL workflow remains unchanged and required in substance.
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
Monday. The default Actions token omits ruleset bypass actors, so the workflow
mints a repository-scoped token from the existing `AA_CI` GitHub App with only
read-only Administration access. The App private key does not expire
automatically, while each installation token is short-lived and generated on
demand. The App must keep **Repository permissions → Administration: Read-only**;
the credential that applies ruleset changes remains separate and deliberately
approved.

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

## PR policy and auto-merge

Keep **Settings → General → Pull Requests → Allow auto-merge** enabled. The
repository must also use **PR title** for squash commit titles and delete head
branches after merge. `pr-policy.yml` registers squash auto-merge only after
every check is green. It does this automatically for verified Dependabot
patch/minor updates and the exact machine version-sync PR; owner PRs require the
explicit `automerge` label. If another PR reaches `master` first, the workflow
updates an eligible stale branch and waits for fresh checks. High-risk, major,
conflicting, failed, or uncertain PRs get `needs-attention` and remain open.

Dependabot security updates must remain enabled in **Settings → Advanced
Security**. Scheduled version PRs are limited to patch/minor with `allow`, which
does not suppress a security update that needs a major version. Do not replace
that separation with a broad `ignore` rule.
