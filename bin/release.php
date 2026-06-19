#!/usr/bin/env php
<?php

declare(strict_types=1);

const RELEASE_TYPES = ['patch', 'minor', 'major'];

function main(array $arguments): int
{
    try {
        $options = parseArguments($arguments);
        $root = dirname(__DIR__);
        $composerPath = $root . '/composer.json';
        $changelogPath = $root . '/CHANGELOG.md';
        $composer = readComposer($composerPath);
        $currentVersion = currentVersion($composer, $root);
        $nextVersion = bumpVersion($currentVersion, $options['type']);

        output("Release: {$currentVersion} -> {$nextVersion} ({$options['type']})");

        $changelog = readChangelog($changelogPath);
        $updatedChangelog = prepareChangelog(
            $changelog,
            $currentVersion,
            $nextVersion,
            repositoryUrl($composer, $root)
        );

        if ($options['dry_run']) {
            output('Dry run complete. No files, commits, tags, or remotes were changed.');

            return 0;
        }

        assertCleanWorkingTree($root);
        assertTagDoesNotExist($root, $nextVersion);

        if (!$options['yes'] && !confirm("Create local release v{$nextVersion}?")) {
            output('Release cancelled.');

            return 0;
        }

        $composer['version'] = $nextVersion;
        writeJson($composerPath, $composer);
        writeFile($changelogPath, $updatedChangelog);

        runGit($root, ['add', 'composer.json', 'CHANGELOG.md']);
        runGit($root, ['commit', '-m', "chore: release v{$nextVersion}"]);
        runGit($root, ['tag', '-a', "v{$nextVersion}", '-m', "Release v{$nextVersion}"]);

        output("Created local release v{$nextVersion}.");

        if ($options['push']) {
            $branch = trim(runGit($root, ['branch', '--show-current']));

            if ($branch === '') {
                throw new RuntimeException('Cannot push a release from a detached HEAD.');
            }

            runGit($root, ['push', 'origin', $branch]);
            runGit($root, ['push', 'origin', "v{$nextVersion}"]);
            output("Pushed {$branch} and v{$nextVersion} to origin.");
        } else {
            output("Push when ready: git push origin HEAD && git push origin v{$nextVersion}");
        }

        return 0;
    } catch (Throwable $exception) {
        fwrite(STDERR, 'Release failed: ' . $exception->getMessage() . PHP_EOL);

        return 1;
    }
}

function parseArguments(array $arguments): array
{
    $type = $arguments[1] ?? null;

    if (!is_string($type) || !in_array($type, RELEASE_TYPES, true)) {
        throw new InvalidArgumentException(
            'Usage: php bin/release.php <patch|minor|major> [--dry-run] [--yes] [--push]'
        );
    }

    $flags = array_slice($arguments, 2);
    $allowedFlags = ['--dry-run', '--yes', '--push'];

    foreach ($flags as $flag) {
        if (!in_array($flag, $allowedFlags, true)) {
            throw new InvalidArgumentException("Unknown option: {$flag}");
        }
    }

    return [
        'type' => $type,
        'dry_run' => in_array('--dry-run', $flags, true),
        'yes' => in_array('--yes', $flags, true),
        'push' => in_array('--push', $flags, true),
    ];
}

function readComposer(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Missing composer manifest: {$path}");
    }

    $composer = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    if (!is_array($composer)) {
        throw new RuntimeException('composer.json must contain a JSON object.');
    }

    return $composer;
}

function currentVersion(array $composer, string $root): string
{
    $manifestVersion = $composer['version'] ?? null;
    $tags = trim(runGit($root, ['tag', '--list', 'v[0-9]*', '--sort=-version:refname']));
    $latestTag = $tags === '' ? null : ltrim((string) strtok($tags, PHP_EOL), 'v');

    if (is_string($manifestVersion) && $manifestVersion !== '') {
        assertSemanticVersion($manifestVersion);

        if ($latestTag !== null && $manifestVersion !== $latestTag) {
            throw new RuntimeException(
                "composer.json version {$manifestVersion} does not match latest tag v{$latestTag}."
            );
        }

        return $manifestVersion;
    }

    if ($latestTag === null) {
        return '0.0.0';
    }

    assertSemanticVersion($latestTag);

    return $latestTag;
}

function bumpVersion(string $version, string $type): string
{
    assertSemanticVersion($version);
    [$major, $minor, $patch] = array_map('intval', explode('.', $version));

    return match ($type) {
        'major' => ($major + 1) . '.0.0',
        'minor' => $major . '.' . ($minor + 1) . '.0',
        'patch' => $major . '.' . $minor . '.' . ($patch + 1),
        default => throw new InvalidArgumentException("Unsupported release type: {$type}"),
    };
}

function assertSemanticVersion(string $version): void
{
    if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/', $version) !== 1) {
        throw new RuntimeException("Version must use MAJOR.MINOR.PATCH format: {$version}");
    }
}

function readChangelog(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('CHANGELOG.md is required before creating a release.');
    }

    $content = file_get_contents($path);

    if (!is_string($content) || !str_contains($content, '## [Unreleased]')) {
        throw new RuntimeException('CHANGELOG.md must contain an ## [Unreleased] section.');
    }

    return $content;
}

function prepareChangelog(
    string $content,
    string $currentVersion,
    string $nextVersion,
    string $repositoryUrl
): string {
    $heading = "## [Unreleased]\n\n## [{$nextVersion}] - " . date('Y-m-d');
    $updated = preg_replace('/## \[Unreleased\]/', $heading, $content, 1, $count);

    if (!is_string($updated) || $count !== 1) {
        throw new RuntimeException('Unable to prepare the Unreleased changelog section.');
    }

    $unreleasedLink = "[Unreleased]: {$repositoryUrl}/compare/v{$nextVersion}...HEAD";

    if (preg_match('/^\[Unreleased\]:.*$/m', $updated) === 1) {
        $updated = (string) preg_replace('/^\[Unreleased\]:.*$/m', $unreleasedLink, $updated, 1);
    } else {
        $updated = rtrim($updated) . "\n\n{$unreleasedLink}\n";
    }

    $versionLink = version_compare($currentVersion, '0.0.0', '>')
        ? "[{$nextVersion}]: {$repositoryUrl}/compare/v{$currentVersion}...v{$nextVersion}"
        : "[{$nextVersion}]: {$repositoryUrl}/releases/tag/v{$nextVersion}";

    if (preg_match('/^\[' . preg_quote($nextVersion, '/') . '\]:/m', $updated) !== 1) {
        $updated = rtrim($updated) . "\n{$versionLink}\n";
    }

    return $updated;
}

function repositoryUrl(array $composer, string $root): string
{
    $sourceUrl = $composer['support']['source'] ?? null;

    if (is_string($sourceUrl) && $sourceUrl !== '') {
        return rtrim(preg_replace('/\.git$/', '', $sourceUrl) ?? $sourceUrl, '/');
    }

    $remote = trim(runGit($root, ['remote', 'get-url', 'origin']));
    $remote = preg_replace('/^git@github\.com:/', 'https://github.com/', $remote) ?? $remote;

    return rtrim(preg_replace('/\.git$/', '', $remote) ?? $remote, '/');
}

function assertCleanWorkingTree(string $root): void
{
    if (trim(runGit($root, ['status', '--porcelain'])) !== '') {
        throw new RuntimeException('Git working tree must be clean before creating a release.');
    }
}

function assertTagDoesNotExist(string $root, string $version): void
{
    $tag = trim(runGit($root, ['tag', '--list', "v{$version}"]));

    if ($tag !== '') {
        throw new RuntimeException("Tag v{$version} already exists.");
    }
}

function runGit(string $root, array $arguments): string
{
    $command = array_merge(['git', '-C', $root], $arguments);
    $escaped = implode(' ', array_map('escapeshellarg', $command));
    exec($escaped . ' 2>&1', $output, $status);

    if ($status !== 0) {
        throw new RuntimeException(implode(PHP_EOL, $output));
    }

    return implode(PHP_EOL, $output);
}

function writeJson(string $path, array $data): void
{
    writeFile($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
}

function writeFile(string $path, string $content): void
{
    if (file_put_contents($path, $content) === false) {
        throw new RuntimeException("Unable to write {$path}");
    }
}

function confirm(string $question): bool
{
    fwrite(STDOUT, "{$question} [y/N] ");
    $answer = fgets(STDIN);

    return is_string($answer) && strtolower(trim($answer)) === 'y';
}

function output(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

exit(main($argv));
