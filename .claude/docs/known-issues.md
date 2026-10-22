# Известные ловушки

- **Значения `StatementType` — метки Prometheus** в metrics-bundle (`doctrine_query_*{type}`) и часть имён span'ов
  profiling: переименование case'ов или смена правила типа — BC break для дашбордов, только в мажоре.
- **Интеграционный тест строит SQL DBAL QueryBuilder'ом** для MySQL, PostgreSQL, SQLite, SQL Server и Oracle.
  Платформа подставляется driver middleware (`tests/Fixtures/FixedPlatformMiddleware`): параметр соединения `platform`
  DBAL 4 молча игнорирует (ревью 2026-10-22 нашло, что тест проверял только SQLite), тест проверяет тип платформы.
  Dev-зависимость — DBAL `^4.0`: в 3.x класс называется `SqlitePlatform`. `union()` — с 4.1, ниже тест пропускается.
  Без doctrine/dbal (job «minimal») тесты пропускаются.
- **`roave/security-advisories` конфликтует с локальным PHP 8.4.17** — локально `composer-local.json`.
- **Эквивалентные мутанты (3, 2026-10-22 UTC)** в `findTable()`: `return null` без совпадения `STATEMENT` (дальше
  `$match` пуст, цикл тоже вернёт null), `isset($match[1])` → `$match[0]` (следом всё равно `$match[1] !== ''`) и
  `array_slice($match, 2)` → `1` (группа 1 пуста и пропускается `continue`). Covered MSI 96 %, пороги CI 90.
- **Отличие от исходника metrics 2.0**: `$groups[$body]` без `(int)` (числовой ключ PHP приводит сам; каст давал
  эквивалентный мутант); пустое имя таблицы `SqlLabels` отвергает, `SqlLabeller` отдаёт вместо него null.
- **`.gitignore` с блоком framework-bundle** — шаблон стандарта (ExactFileRule), для библиотеки он лишний, но не мешает.
- **Кэш внутри `SqlLabeller`** (разбор + LRU в одном классе) оставлен как в metrics; вынос в декоратор над
  `SqlLabelling` — возможная 1.x-доработка без смены API.
- **PHPStan без DBAL**: `make check` рассчитан на CI-профиль (`composer-ci.json` с doctrine/dbal); `phpstan-ci.neon` —
  шаблон стандарта, его комментарий про class.notFound в baseline общий для семейства, baseline здесь пуст.
- **`[ ]`** (пробелы в скобках) → после `trim` пустое имя → null.
- **`composer-ci.lock` в git**, как у остальных пакетов семейства: job `setup` CI ставит зависимости по нему. Локально
  `roave/security-advisories` конфликтует с PHP 8.4.17 — lock обновлять с `--ignore-platform-req=php`, работать через
  `composer-local.json`.
