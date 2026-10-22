# Архитектура

- PHPStan level 10 (шаблон позволяет 9/10/max).
- Пакет вида `library` (bundle-standard 3.3): нет класса бандла и слоёв DependencyInjection/BundleRoot; deptrac — шаблон
  `deptrac-library.yaml`.
- `Domain/Enum/StatementType`, `Domain/ValueObject/SqlLabels` — слой Domain; `Domain/Helper/SqlLabelling`,
  `Domain/Helper/SqlLabeller` — DomainService (чистая функция с кэшем).
- Разбор (перенесён из metrics-bundle 2.0 `QueryLabeller`, 2026-10-22 UTC): обрезка до 16 KiB; комментарии и строки →
  пробел/`''` (`NOISE`); скобочные группы → `(#n)` (`PARENTHESISED`, рекурсивный regex), поиск `STATEMENT` только на
  верхнем уровне; рекурсия в derived table, в выражение, начинающееся с `(#0)` (UNION от QueryBuilder), и в тело CTE
  (имя рекурсивного CTE не разворачивается повторно); снятие кавычек и schema/catalog-префиксов.
- Кэш: 256 записей по `xxh128`, LRU (повторное попадание переносит запись в конец).
- Тип — только по первому ключевому слову: `WITH … SELECT` = `other` (как в metrics 2.0, менять — значит менять
  метки метрик).
