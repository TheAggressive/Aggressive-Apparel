# Day-to-day workflow

The short version. For release mechanics and recovery, see
[`release-runbook.md`](release-runbook.md).

## The whole loop

```bash
# 1. Make the change on a branch, then:
git add -A
git commit -m "fix: cart badge shows the wrong count"
git push
gh pr create --fill
```

The pull-request title becomes the squash commit title, so it must use the same
Conventional Commit form as the example. A pull request plus current PR Policy,
CI, CodeQL, Actionlint, and Zizmor results gate the merge.

## The one thing to know: your PR title prefix

| Prefix                                  | What happens                          |
| --------------------------------------- | ------------------------------------- |
| `fix:`                                  | Patch release — 1.181.**1** → 1.181.2 |
| `feat:`                                 | Minor release — 1.**181** → 1.182.0   |
| `chore:` `ci:` `docs:` `test:`          | **No release.** Just checks.          |
| `feat!:` or a `BREAKING CHANGE:` footer | Major release — **2**.0.0             |

Merging does not publish. Release deliberately with
`gh workflow run "CI/CD Pipeline" --ref master -f publish=true`; the protected
`production` environment then permits deployment only from `master`.

## Pull-request automation

Every PR receives one type label, relevant area labels, and exactly one risk
label. `risk:high` and `needs-attention` mean automation has stopped for you.
Ordinary owner PRs never opt themselves into merging.

For one of your own low/medium-risk PRs, add `automerge` and leave it alone:

```bash
gh pr edit <number> --add-label automerge
```

The policy re-verifies the owner and changed paths from GitHub, updates a branch
that is behind `master`, waits for fresh required checks, and then registers
native squash auto-merge. Removing `automerge`, adding a high-risk file, a
conflict, an invalid title, or a failed check cancels that intent and adds
`needs-attention` where action is required. Workflow/ruleset/release/CI-contract,
runtime-contract, updater, and security-sensitive endpoint changes are always
high-risk.

## What runs, and when

**On the pull request:** lint, types, tests, CodeQL, Actionlint, and Zizmor.

**On a deliberate release dispatch after release-worthy merges:** build the
normalized final ZIP, install it in clean WordPress, attest it, verify the
remote draft, and publish it.

**Before a release, if you want exact parity:** `pnpm qa:ci` runs every required
GitHub lane in its isolated containers. Ordinary `pnpm qa` stays Docker-free.

## When something goes red

1. **Read which job failed** — the name tells you where to look.
2. **Is it a network error?** `registry-1.docker.io`, `context deadline
exceeded`, `TLS handshake timeout` → not your code. Re-run it:
   ```bash
   gh run rerun <run-id> --failed
   ```
3. **Otherwise reproduce it locally** — each CI job maps to one command:

   | Failing job             | Run locally        |
   | ----------------------- | ------------------ |
   | 🔍 Lint & Test Frontend | `pnpm ci:frontend` |
   | 🔨 Build                | `pnpm ci:build`    |
   | 🧪 PHP Quality & Tests  | `pnpm ci:php`      |
   | 🎭 Browser E2E          | `pnpm ci:e2e`      |
   | 📦 Package Theme        | `pnpm ci:package`  |
   | 🧳 Artifact Acceptance  | `pnpm ci:artifact` |
   | 🌐 i18n Check           | `pnpm ci:i18n`     |

   These are the _same commands_ GitHub runs — not approximations.
   `bin/ci/contracts.mjs` fails the build if they ever drift apart.

## Dependabot pull requests

A few arrive each Monday, grouped. They are dependency updates.

- **Patch/minor or grouped patch/minor, all checks green** → auto-merge completes
  under the branch rules. Verified GitHub Action SHA bumps use the same path.
- **`needs-attention` / major / unknown structure** → automation failed closed;
  inspect it manually.
- **`CONFLICTING`** → comment `@dependabot rebase` on the PR. Never fix a
  lockfile conflict by hand.
- **Checks failing** → the update genuinely breaks something. Close it; it will
  come back once the upstream problem is fixed, or once you make the change it
  needs.

Major upgrades no longer arrive automatically — they were too noisy. Do them
deliberately when you want to:

```bash
pnpm up typescript@latest
pnpm qa          # Docker-free local check
pnpm qa:ci       # full containerized release-parity check
git commit -m "chore(deps): upgrade typescript"
```

Security updates still open when the minimum secure version crosses a major.
Those cross-major security PRs are deliberately left for review; security
patch/minor PRs follow the normal hands-off path.

## If a release goes wrong

Stop it reaching any more sites immediately:

```bash
gh release edit v1.2.3 --prerelease
```

Sites that have not updated resolve the previous stable version; already-updated
sites stay on the bad version. Fix forward with a normal `fix:` commit. Full
detail is in [`release-runbook.md`](release-runbook.md).

## Commands worth remembering

| Command        | What it does                               |
| -------------- | ------------------------------------------ |
| `pnpm dev`     | Start the local site + watch for changes   |
| `pnpm qa:fast` | The pre-push checks, on demand (~3 min)    |
| `pnpm qa`      | Docker-free native + Studio checks         |
| `pnpm qa:ci`   | Everything GitHub runs (~15 min)           |
| `pnpm build`   | Compile assets                             |
| `gh run watch` | Follow the current CI run in your terminal |
| `gh pr list`   | See open pull requests                     |
