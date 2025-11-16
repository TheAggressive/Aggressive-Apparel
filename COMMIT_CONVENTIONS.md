# Commit Message Conventions

This project uses [Conventional Commits](https://conventionalcommits.org/) for automated version management and release generation.

## Commit Types

- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc)
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `perf`: A code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `build`: Changes that affect the build system or external dependencies
- `ci`: Changes to our CI configuration files and scripts
- `chore`: Other changes that don't modify src or test files
- `revert`: Reverts a previous commit

## Format

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

## Examples

### Feature Commit
```
feat: add dark mode toggle block

- Add new dark mode toggle block
- Include server-side rendering
- Add editor controls
```

### Bug Fix
```
fix: resolve WebP conversion memory leak

Fix memory leak in WebP converter when processing large images
by properly destroying GD resources after conversion.
```

### Breaking Change
```
feat!: redesign block API

BREAKING CHANGE: The block registration API has been completely redesigned.
Update your block definitions to use the new format.
```

### Scope
```
feat(blocks): add interactive dark mode toggle

Add interactive functionality to the dark mode toggle block
using WordPress Interactivity API.
```

## Why Conventional Commits?

- **Automated Versioning**: semantic-release automatically determines version bumps
- **Clear Changelog**: Generated changelogs are more readable
- **Better Communication**: Commit messages clearly indicate the intent and impact
- **Automated Releases**: GitHub Actions automatically creates releases

## Version Bumping

- `fix:` → Patch release (1.0.0 → 1.0.1)
- `feat:` → Minor release (1.0.0 → 1.1.0)
- `BREAKING CHANGE:` → Major release (1.0.0 → 2.0.0)

## Tools

- **Husky**: Git hooks for pre-commit linting and commit message validation
- **commitlint**: Validates commit messages follow conventional format
- **semantic-release**: Automates versioning and release creation

## Validation

All commits are validated using commitlint. Invalid commit messages will be rejected:

```bash
❌ Wrong: "fixed bug"
✅ Right: "fix: resolve memory leak in WebP converter"
```

## Getting Started

1. Use `pnpm run commit` instead of `git commit` (if you have commitizen set up)
2. Or manually write commits following the format above
3. Pre-commit hooks will run linting automatically
4. Commit-msg hooks will validate your commit message format
