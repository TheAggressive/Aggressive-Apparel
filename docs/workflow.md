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

The commit message decides whether the protected merge produces a release. A
pull request plus current CI, CodeQL, Actionlint, and Zizmor results gate the
merge.

## The one thing to know: your commit prefix

| Prefix                                  | What happens                          |
| --------------------------------------- | ------------------------------------- |
| `fix:`                                  | Patch release — 1.181.**1** → 1.181.2 |
| `feat:`                                 | Minor release — 1.**181** → 1.182.0   |
| `chore:` `ci:` `docs:` `test:`          | **No release.** Just checks.          |
| `feat!:` or a `BREAKING CHANGE:` footer | Major release — **2**.0.0             |

A release is prepared automatically after merge. The protected `production`
environment permits deployment only from `master`, so publication follows a
protected merge and the complete release verification path.

## What runs, and when

**On the pull request:** lint, types, tests, CodeQL, Actionlint, and Zizmor.

**After a release-worthy merge:** build the normalized final ZIP, install it in
clean WordPress, attest it, verify the remote draft, and publish it.

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

- **All checks green** → auto-merge completes under the branch rules. Inspect it
  manually whenever the change needs judgment beyond the automated gates.
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

Security updates still arrive automatically regardless.

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
