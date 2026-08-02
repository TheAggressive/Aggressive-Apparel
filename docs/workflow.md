# Day-to-day workflow

The short version. For release mechanics and recovery, see
[`release-runbook.md`](release-runbook.md).

## The whole loop

```bash
# 1. make your change, then:
git add -A
git commit -m "fix: cart badge shows the wrong count"
git push
```

That's it. The commit message decides whether a release happens. Pushing runs
checks automatically.

## The one thing to know: your commit prefix

| Prefix                                  | What happens                          |
| --------------------------------------- | ------------------------------------- |
| `fix:`                                  | Patch release — 1.181.**1** → 1.181.2 |
| `feat:`                                 | Minor release — 1.**181** → 1.182.0   |
| `chore:` `ci:` `docs:` `test:`          | **No release.** Just checks.          |
| `feat!:` or a `BREAKING CHANGE:` footer | Major release — **2**.0.0             |

A release publishes automatically and every site running the theme is offered
the update. There is no separate "deploy" step, so use `chore:` when you are
not ready for that.

## What runs, and when

**When you push** (~3 min, automatic): lint, types, JS tests, the build, and
all the PHP tests. Enough to catch what people actually break.

**In GitHub afterwards** (~15 min): the same checks plus browser tests, and —
only for `fix:`/`feat:` commits — building, verifying and publishing the ZIP.

**Before a release, if you want certainty:** `pnpm qa` runs everything GitHub
runs, on your machine, before you push.

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
   | 🌐 i18n Check           | `pnpm ci:i18n`     |

   These are the _same commands_ GitHub runs — not approximations.
   `bin/ci/contracts.mjs` fails the build if they ever drift apart.

## Dependabot pull requests

A few arrive each Monday, grouped. They are dependency updates.

- **All checks green** → merge it.
- **`CONFLICTING`** → comment `@dependabot rebase` on the PR. Never fix a
  lockfile conflict by hand.
- **Checks failing** → the update genuinely breaks something. Close it; it will
  come back once the upstream problem is fixed, or once you make the change it
  needs.

Major upgrades no longer arrive automatically — they were too noisy. Do them
deliberately when you want to:

```bash
pnpm up typescript@latest
pnpm qa          # full check
git commit -m "chore(deps): upgrade typescript"
```

Security updates still arrive automatically regardless.

## If a release goes wrong

Stop it reaching any more sites immediately:

```bash
gh release edit v1.2.3 --prerelease
```

Sites fall back to the previous version. Then fix forward with a normal `fix:`
commit. Full detail in [`release-runbook.md`](release-runbook.md).

## Commands worth remembering

| Command        | What it does                               |
| -------------- | ------------------------------------------ |
| `pnpm dev`     | Start the local site + watch for changes   |
| `pnpm qa:fast` | The pre-push checks, on demand (~3 min)    |
| `pnpm qa`      | Everything GitHub runs (~15 min)           |
| `pnpm build`   | Compile assets                             |
| `gh run watch` | Follow the current CI run in your terminal |
| `gh pr list`   | See open pull requests                     |
