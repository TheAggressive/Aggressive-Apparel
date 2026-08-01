# Repository rulesets

Branch protection lives in GitHub's database, not in this repository, so every
gate the CI pipeline builds is only as strong as a setting somebody can change
without review or audit trail. These files are the committed, reviewable
intent — apply and verify them with the commands below.

They are **not** applied automatically. GitHub has no mechanism to sync rulesets
from a repository, and a workflow that tried would need an admin-scoped token
with permission to rewrite its own protections, which is a worse trade than
running a command deliberately.

## Apply

```bash
# Create (first time)
gh api --method POST /repos/TheAggressive/Aggressive-Apparel/rulesets \
  --input .github/rulesets/release-branches.json

# Update (subsequent changes) — find the id with the list command below
gh api --method PUT /repos/TheAggressive/Aggressive-Apparel/rulesets/<id> \
  --input .github/rulesets/release-branches.json
```

## Verify (drift check)

```bash
gh api /repos/TheAggressive/Aggressive-Apparel/rulesets --jq '.[] | "\(.id) \(.name) \(.enforcement)"'

# Full comparison against the committed intent
gh api /repos/TheAggressive/Aggressive-Apparel/rulesets/<id> \
  --jq '{name, enforcement, conditions, rules}'
```

Run the verify command whenever a release behaves unexpectedly, and after any
change to repository settings.

## What `release-branches.json` enforces

| Rule               | Effect                                                                            |
| ------------------ | --------------------------------------------------------------------------------- |
| `deletion`         | `main`/`master` cannot be deleted                                                 |
| `non_fast_forward` | No force-pushes — protects the tag history semantic-release derives versions from |

Both are safe to apply immediately: neither interferes with the release job.

## Rules deliberately NOT in the committed ruleset

Everything below would block the automated release. semantic-release pushes the
`chore(release): x.y.z [skip ci]` commit **directly** to the release branch as
`github-actions[bot]`, using `GITHUB_TOKEN`. That identity is not covered by the
repository-admin bypass above.

**`required_status_checks`.** GitHub enforces this on direct pushes, not only on
merges — and the release commit is a brand new commit with no checks against it,
because `[skip ci]` deliberately prevents a run. Adding this rule stops every
release at its final step, after the tag and GitHub Release already exist, which
is the hardest state to recover from (see the runbook's `verify-assets.sh`
section).

**`pull_request`.** Same problem: a direct push cannot satisfy a pull-request
requirement.

**`required_approving_review_count`.** Unsatisfiable with one maintainer — you
cannot approve your own pull request.

### Adding them safely

1. Add the GitHub Actions app as a `bypass_actor`:

   ```bash
   # Resolve the installation's app id for this repository
   gh api /repos/TheAggressive/Aggressive-Apparel/installation --jq '.app_id'
   ```

   then add `{"actor_id": <app_id>, "actor_type": "Integration", "bypass_mode": "always"}`.

2. Apply with `"enforcement": "evaluate"` — this reports what _would_ have been
   blocked without blocking it.
3. Cut a real release and confirm the ruleset's insights show no violations.
4. Only then switch to `"enforcement": "active"`.

Do not skip step 3. A ruleset that looks correct and blocks the release push
fails at the one point in the pipeline that cannot be retried.

## CODEOWNERS

`.github/CODEOWNERS` is committed and takes effect for review _suggestions_
immediately. It only becomes enforcing when a `pull_request` rule with
`require_code_owner_review: true` is added — see the caveats above.

## `strict_required_status_checks_policy`

Left `false` on purpose. When true, a branch must be up to date with the base
before merging, which on an active repository means constant rebasing for a gate
that already runs the full suite on the merge result.
