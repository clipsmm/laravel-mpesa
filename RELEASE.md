# Release Process

This package follows Semantic Versioning. The **latest git tag is the source of
truth** for the current version — `composer.json` intentionally omits the
`version` field (Composer derives it from VCS tags), so the manifest can never
drift out of sync with the published tags.

## Automated releases (on pull request merge)

`.github/workflows/release-on-pr-merge.yml` runs whenever a pull request is
**merged** into `main` or `master`. It:

1. checks out the merged target branch and runs `composer check` plus
   `composer audit`;
2. reads the latest `v*` tag to determine the current version;
3. creates and pushes an **annotated** tag on the merge commit;
4. publishes a GitHub Release with GitHub's auto-generated notes.

The bump defaults to **patch**. Apply exactly one label to the merged pull
request to widen it:

| Label | Result |
| --- | --- |
| _(none)_ or `release:patch` | `0.1.1` → `0.1.2` |
| `release:minor` | `0.1.1` → `0.2.0` |
| `release:major` | `0.1.1` → `1.0.0` |

The workflow only creates **tags and releases** — it never pushes a commit to
`main`/`master`. That keeps it compatible with the branch protection rules
(required review, signed commits, locked branch).

## Manual releases

Use these locally when you want to curate `CHANGELOG.md` yourself:

```bash
# 0.1.1 -> 0.1.2
composer release:patch

# 0.1.1 -> 0.2.0
composer release:minor

# 0.1.1 -> 1.0.0
composer release:major
```

Preview without changing files, commits, tags, or remotes:

```bash
composer release:patch -- --dry-run
```

A manual release:

- derives the current version from the latest `v*` tag,
- moves the `## [Unreleased]` changelog entries under a new version heading,
- commits the `CHANGELOG.md` change,
- creates an annotated `vX.Y.Z` tag.

It never pushes unless you pass `--push`. Use `--yes` for non-interactive use.

The manual command is meant for maintainers who can push directly to the target
branch. If the branch is protected, push the changelog commit through a pull
request and let the automated workflow create the tag and release instead.

## Notes

- The repository is configured to **automatically delete head branches** when a
  pull request is merged, so feature branches are cleaned up on their own.
- Automated release notes come from GitHub's generated notes (merged PRs since
  the previous tag). Curate `CHANGELOG.md` on a pull request if you want
  hand-written notes.
- Do **not** add a `version` field back to `composer.json`. A stale value there
  was what previously caused releases to fail with
  `composer.json version ... does not match latest tag ...`.
