# Changelog

All notable changes to this library are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), versions follow
[Semantic Versioning](https://semver.org/); dates are UTC.

## [1.0.0] - 2026-10-22

### Added

- `Domain\Helper\SqlLabeller::label()` (interface `SqlLabelling`) returning `Domain\ValueObject\SqlLabels` with a
  `Domain\Enum\StatementType` and the first table of an SQL string — moved from metrics-bundle 2.0's internal
  `QueryLabeller` so that metrics-bundle and profiling-bundle share one parser.

[1.0.0]: https://github.com/msstc4symfony/sql-labeller/releases/tag/v1.0.0
