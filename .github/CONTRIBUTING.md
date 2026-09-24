# Contribution Guide

Thanks for considering a contribution to Laravel Streamer! Please read this before you open a pull request.

For bigger changes, open an issue first so we can agree on the approach.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit and push
4. Open a pull request that explains your changes

## Guidelines

- Format your code with `composer format` (Laravel Pint).
- Add tests for new behaviour and bug fixes.
- Update the docs in `docs/` when behaviour changes.
- Keep each commit meaningful. You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
- We follow [SemVer](https://semver.org/).

## Setup

Clone your fork, then install the dev dependencies:

```bash
composer install
```

## Checks

```bash
composer format
composer analyse
composer test
```
