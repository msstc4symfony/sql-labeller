# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Plain PHP library `msstc4symfony/sql-labeller` (namespace `Msstc4Symfony\SqlLabeller`, bundle-standard kind
`library`: no bundle class): `Domain\Helper\SqlLabeller::label(string $sql): SqlLabels` — the statement type and the
first table. Shared by metrics-bundle (Doctrine metric labels) and profiling-bundle (SQL span names). PHP >= 8.2.

## Common commands

- `COMPOSER=composer-ci.json composer install` (locally `composer-local.json`: the same without
  `roave/security-advisories`, which conflicts with PHP 8.4.17).
- `make check`, `make test`, `make infection`, `make fix`.
- `php ../bundle-standard/bin/verify-standard.php .` — standard compliance.

## Architecture in 60 seconds

- `Domain/Enum/StatementType`, `Domain/ValueObject/SqlLabels` (layer Domain); `Domain/Helper/SqlLabelling` +
  `SqlLabeller` (layer DomainService, regex parser with an LRU cache of 256 by `xxh128`).
- The parser came from metrics-bundle 2.0 `Infrastructure\Doctrine\DBAL\Metrics\QueryLabeller` with all its test cases;
  its documented limits are in README.

Details: `.claude/docs/`. Read `known-issues.md` before chasing a "weird" failure.
