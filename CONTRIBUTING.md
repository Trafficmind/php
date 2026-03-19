# Contributing to Trafficmind PHP SDK

Thank you for your interest in contributing! This document covers everything you need to get started.

## Table of Contents

- [Development Setup](#development-setup)
- [Running Tests](#running-tests)
- [Code Style](#code-style)
- [Commit Style](#commit-style)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Security Vulnerabilities](#security-vulnerabilities)

---

## Development Setup

**Requirements:** PHP 8.1+, Composer, `ext-json`, `ext-curl`

```bash
git clone https://github.com/trafficmind/php.git
cd php
composer install
```

---

## Running Tests

```bash
# Unit tests (no network calls)
composer test

# Static analysis (PHPStan level 8)
composer analyse

# Code style check
composer cs-check

# Auto-fix code style
composer cs-fix

# All checks at once
composer check

# Tests with coverage report (requires Xdebug or PCOV)
composer coverage-check
```

All checks must pass before submitting a PR. The CI pipeline runs the full suite
on PHP 8.1, 8.2, 8.3, and 8.4.

---

## Code Style

- Formatted with **PHP CS Fixer** — run `composer cs-fix` before committing.
- All files must have `declare(strict_types=1)`.
- New classes should use `final` unless inheritance is explicitly intended.
- Use `readonly` for properties that are not modified after construction.
- Keep PHPStan at level 8 — do not add `@phpstan-ignore` without a clear comment explaining why.

---

## Commit Style

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short summary>
```

**Types:** `feat`, `fix`, `docs`, `refactor`, `test`, `chore`, `ci`

**Examples:**

```
feat(retry): add jitter to exponential backoff
fix(client): correct Retry-After header parsing for float values
docs(readme): add OpenTelemetry integration example
test(dns): add edge case for empty paginate response
```

Commits power the automated changelog via `release-please` — please use the correct type.

---

## Pull Request Process

1. **Fork** the repository and create a branch from `main`.
2. **Write tests** for any new behaviour — coverage must stay at or above 80%.
3. **Run all checks** locally: `composer check && make coverage-check`.
4. **Open a PR** against `main` with a clear description of what changed and why.
5. Reference any related issues with `Closes #<issue>` in the PR body.
6. A maintainer will review within a few business days.

**Breaking changes** must be discussed in an issue before implementation.
All breaking changes require a major version bump and a migration note in the PR.

---

## Reporting Bugs

Use the [Bug Report](https://github.com/trafficmind/php/issues/new?template=bug_report.yml) issue template.

Please include:
- PHP version and OS
- SDK version (`composer show trafficmind/php`)
- Minimal reproducible code snippet
- Expected vs. actual behaviour

---

## Security Vulnerabilities

**Do not open a public issue for security vulnerabilities.**
Please follow the process described in [SECURITY.md](SECURITY.md).