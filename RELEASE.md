# Release Process

This package follows Semantic Versioning and keeps pending release notes under
`## [Unreleased]` in `CHANGELOG.md`.

## Commands

```bash
# 0.0.0 -> 0.0.1
composer release:patch

# 0.0.0 -> 0.1.0
composer release:minor

# 0.0.0 -> 1.0.0
composer release:major
```

Preview a release without changing files or Git state:

```bash
composer release:patch -- --dry-run
```

## Workflow

1. Add user-facing changes to the `Unreleased` changelog section.
2. Run the package tests supported by the project.
3. Commit all preparation work so the working tree is clean.
4. Run the appropriate `composer release:*` command.
5. Review the generated commit and annotated tag.
6. Push explicitly with `git push origin HEAD && git push origin vX.Y.Z`, or
   pass `--push` to the release command.

Use `--yes` for non-interactive local release creation. A release command
updates `composer.json` and `CHANGELOG.md`, creates a `chore: release vX.Y.Z`
commit, and creates the matching annotated tag. It never pushes unless
`--push` is present.

## Automated Pull Request Releases

`.github/workflows/release-on-pr-merge.yml` releases only merged pull requests
targeting `main` or `master`. It runs tests and `composer audit`, then defaults
to a patch bump. Apply exactly one `release:minor` or `release:major` label to
select a different bump.

The workflow updates the manifest and changelog, creates and pushes an
annotated tag, and creates the GitHub Release. If the PR already contains a
version prepared with `composer release:*`, it validates and uses that version
without bumping twice.

Repository settings must grant GitHub Actions write access to contents and
allow its release commit on the protected target branch.
