# msstc4symfony/sql-labeller

![Build Status](https://github.com/msstc4symfony/sql-labeller/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

The statement type and the table of an SQL string, cheap enough to run on every query: the labels
[metrics-bundle](https://github.com/msstc4symfony/metrics-bundle) puts on its Doctrine metrics and the names
[profiling-bundle](https://github.com/msstc4symfony/profiling-bundle) gives its SQL spans. A plain PHP library: no
Symfony, no Doctrine.

## Compatibility

| Library | PHP  |
|---------|------|
| 1.0     | 8.2+ |

## Installation

The package is not on Packagist yet, so register its GitHub repository first:

```sh
composer config repositories.msstc4symfony-sql-labeller vcs https://github.com/msstc4symfony/sql-labeller
composer require msstc4symfony/sql-labeller
```

## Usage

```php
use Msstc4Symfony\SqlLabeller\Domain\Helper\SqlLabeller;

$labels = (new SqlLabeller())->label('SELECT o.id FROM shop.orders o JOIN customers c ON c.id = o.customer_id');
$labels->type;  // StatementType::SELECT
$labels->table; // 'orders'
```

- `type` is `select`, `insert`, `update` or `delete` by the statement's first keyword; anything else is `other` — a
  statement starting with `WITH`, a parenthesised `UNION`, a leading comment — even when its table is found.
- `table` is the first table the statement reads or writes, unquoted (`"…"`, `` `…` ``, `[…]`) and without its
  schema or catalog; `null` when none is found. Comments and string literals are skipped, and only the statement's own
  `FROM` counts: a subquery's or a function argument's (`EXTRACT(EPOCH FROM …)`) does not. A derived table, a
  parenthesised statement (a `UNION` built by DBAL's QueryBuilder) and a common table expression are followed to the
  table inside.
- Only the first 16 KiB are parsed: a `FROM` beyond them is not found. Results are cached per SQL (256 statements,
  least recently used out), so keep one labeller per connection or process.

Known limits:

- a table function in `FROM` is labelled with the function's name;
- a table named `only` or `lateral` is taken for the keyword: the next word (an alias or a keyword) is labelled;
- a string literal ending in a backslash (standard_conforming_strings, MySQL's NO_BACKSLASH_ESCAPES), dollar-quoted
  strings and MySQL `#` comments are not recognised, so a `FROM` inside them may be taken;
- MySQL's multi-table `DELETE t FROM t JOIN …` has no table.

## Public API

`Domain\Enum\StatementType`, `Domain\ValueObject\SqlLabels`, `Domain\Helper\SqlLabelling` (the interface, for test
doubles) and `Domain\Helper\SqlLabeller` under `Msstc4Symfony\SqlLabeller\`.

## Local development

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration (DBAL 4 QueryBuilder SQL of five platforms)
```

## License

MIT — see [LICENSE](LICENSE).
