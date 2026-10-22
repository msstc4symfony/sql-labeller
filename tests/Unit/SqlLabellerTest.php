<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Test\Unit;

use InvalidArgumentException;
use Msstc4Symfony\SqlLabeller\Domain\Enum\StatementType;
use Msstc4Symfony\SqlLabeller\Domain\Helper\SqlLabeller;
use Msstc4Symfony\SqlLabeller\Domain\ValueObject\SqlLabels;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SqlLabeller::class)]
#[CoversClass(SqlLabels::class)]
final class SqlLabellerTest extends TestCase
{
    // Shape of PostgreSQLSchemaManager::selectTableColumns() (DBAL 4): a subquery in the select list,
    // a scalar subquery in a JOIN condition and "from" in a trailing comment.
    private const POSTGRES_COLUMNS_SQL = <<<'SQL'
        SELECT quote_ident(n.nspname) AS schema_name,
               quote_ident(c.relname) AS table_name,
               format_type(a.atttypid, a.atttypmod) AS complete_type,
               (SELECT pg_get_expr(adbin, adrelid)
                FROM pg_attrdef
                WHERE c.oid = pg_attrdef.adrelid AND pg_attrdef.adnum = a.attnum) AS "default"
        FROM pg_attribute a
                 JOIN pg_class c ON c.oid = a.attrelid
                 LEFT JOIN pg_depend dep
                           ON dep.objid = c.oid
                               AND dep.classid = (SELECT oid FROM pg_class WHERE relname = 'pg_class')
        WHERE c.relname = ?
          -- 'r' for regular tables - 'p' for partitioned tables
          AND c.relkind IN ('r', 'p')
          -- exclude partitions (tables that inherit from partitioned tables)
          AND dep.refobjid IS NULL
        SQL;

    /**
     * @return iterable<string, array{string, StatementType}>
     */
    public static function typeProvider(): iterable
    {
        yield 'SELECT uppercase' => ['SELECT * FROM users', StatementType::SELECT];
        yield 'select lowercase' => ['select * from users', StatementType::SELECT];
        yield 'INSERT' => ['INSERT INTO users (name) VALUES (1)', StatementType::INSERT];
        yield 'UPDATE' => ['UPDATE users SET name = ?', StatementType::UPDATE];
        yield 'DELETE' => ['DELETE FROM users WHERE id = 1', StatementType::DELETE];
        yield 'leading whitespace' => ["  \n  SELECT 1", StatementType::SELECT];
        yield 'unknown becomes OTHER' => ['CREATE TABLE foo (id INT)', StatementType::OTHER];
        yield 'WITH is OTHER' => ['WITH x AS (SELECT * FROM orders) SELECT * FROM x', StatementType::OTHER];
        yield 'parenthesised UNION is OTHER' => ['(SELECT a FROM t1) UNION (SELECT a FROM t2)', StatementType::OTHER];
        yield 'leading comment is OTHER' => ['/* app */ SELECT 1', StatementType::OTHER];
        yield 'empty becomes OTHER' => ['', StatementType::OTHER];
    }

    #[DataProvider('typeProvider')]
    public function testLabelsTheStatementType(string $sql, StatementType $expected): void
    {
        self::assertSame($expected, (new SqlLabeller())->label($sql)->type);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function tableNameProvider(): iterable
    {
        yield 'SELECT FROM' => ['SELECT * FROM users WHERE id = 1', 'users'];
        yield 'INSERT INTO' => ['INSERT INTO orders (id) VALUES (1)', 'orders'];
        yield 'DELETE FROM' => ['DELETE FROM products WHERE id = 1', 'products'];
        yield 'UPDATE' => ['UPDATE accounts SET balance = 0 WHERE id = 1', 'accounts'];
        yield 'underscores' => ['SELECT * FROM user_logs WHERE id = 1', 'user_logs'];
        yield 'SELECT without WHERE' => ['SELECT * FROM users', 'users'];
        yield 'INSERT without column list' => ['INSERT INTO orders VALUES (1)', 'orders'];
        yield 'UPDATE at end of line' => ["UPDATE accounts\nSET balance = 0", 'accounts'];
        yield 'schema-qualified SELECT' => ['SELECT * FROM public.users WHERE id = 1', 'users'];
        yield 'schema-qualified UPDATE' => ['UPDATE app.orders SET x = 1', 'orders'];
        yield 'no table' => ['CREATE TABLE foo (id INT)', null];
        yield 'subquery in WHERE labels the outer table' => [
            'SELECT * FROM users WHERE id IN (SELECT user_id FROM orders)',
            'users',
        ];
        yield 'JOIN labels the first FROM table' => [
            "SELECT u.id FROM users u\nJOIN orders o ON o.user_id = u.id\nWHERE o.id IN (SELECT id FROM refunds)",
            'users',
        ];
        yield 'FROM beyond the parsed prefix is not searched' => [
            'SELECT ' . str_repeat('a, ', 6_000) . 'b FROM users',
            null,
        ];
        yield 'long IN list after the table' => [
            'SELECT * FROM users WHERE id IN (' . implode(', ', range(1, 200_000)) . ')',
            'users',
        ];
        yield 'FROM inside a function call' => ['SELECT EXTRACT(EPOCH FROM created_at) FROM events', 'events'];
        yield 'scalar subquery in the select list labels the outer table' => [
            'SELECT (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS n FROM users u',
            'users',
        ];
        yield 'FROM in a line comment' => ["SELECT id -- copied from partitioned tables\nFROM users", 'users'];
        yield 'FROM in a block comment' => ['SELECT /* from audit */ id FROM users', 'users'];
        yield 'FROM in a string literal' => ["SELECT 'from audit' AS source, id FROM users", 'users'];
        yield 'escaped quote in a string literal' => ["SELECT 'it''s from audit' AS s FROM users", 'users'];
        yield 'derived table labels its inner table' => [
            'SELECT n FROM (SELECT COUNT(*) AS n FROM orders) t',
            'orders',
        ];
        yield 'double-quoted identifier' => ['SELECT * FROM "public"."user_logs" WHERE id = 1', 'user_logs'];
        yield 'backtick identifier' => ['SELECT * FROM `orders`', 'orders'];
        yield 'quoted INSERT target' => ['INSERT INTO "orders" (id) VALUES (1)', 'orders'];
        yield 'column whose name starts with "from"' => ['SELECT id, fromage FROM cheeses', 'cheeses'];
        yield 'column named "from" behind a qualifier' => ['SELECT t.from FROM transfers t', 'transfers'];
        yield 'FROM right after a closing parenthesis' => ['SELECT COUNT(*)FROM users', 'users'];
        yield 'Postgres column introspection by DBAL' => [self::POSTGRES_COLUMNS_SQL, 'pg_attribute'];
        yield 'parenthesised UNION built by DBAL QueryBuilder' => ['(SELECT a FROM t1) UNION (SELECT a FROM t2)', 't1'];
        yield 'parenthesised UNION with leading whitespace' => [
            "\n  ((SELECT a FROM t1) UNION ALL (SELECT a FROM t2))",
            't1',
        ];
        yield 'CTE reference labels the CTE body table' => [
            'WITH x AS (SELECT * FROM orders) SELECT * FROM x',
            'orders',
        ];
        yield 'CTE referencing an earlier CTE' => [
            'WITH a AS (SELECT * FROM orders), "b" (id) AS MATERIALIZED (SELECT id FROM a) SELECT * FROM b',
            'orders',
        ];
        yield 'recursive CTE does not loop' => [
            'WITH RECURSIVE n AS (SELECT 1 AS i UNION ALL SELECT i + 1 FROM n WHERE i < 5) SELECT i FROM n',
            'n',
        ];
        yield 'block comment glued to the table name' => ['SELECT * FROM/* audit */users', 'users'];
        yield 'CTE declared in another case than it is used' => [
            'WITH Recent AS (SELECT * FROM orders) SELECT * FROM recent',
            'orders',
        ];
        yield 'derived table after another parenthesised group' => [
            'SELECT COUNT(n) FROM (SELECT id AS n FROM orders) t',
            'orders',
        ];
        yield 'CTE name differing in case' => ['WITH recent AS (SELECT * FROM orders) SELECT * FROM Recent', 'orders'];
        yield 'table that is not a CTE' => [
            'WITH x AS (SELECT * FROM orders) SELECT * FROM users JOIN x USING (id)',
            'users',
        ];
        yield 'CTE before INSERT' => [
            'WITH x AS (SELECT * FROM orders) INSERT INTO archive SELECT * FROM x',
            'archive',
        ];
        yield 'FROM ONLY' => ['SELECT * FROM ONLY parent WHERE id = 1', 'parent'];
        yield 'UPDATE ONLY' => ['UPDATE ONLY parent SET x = 1', 'parent'];
        yield 'DELETE FROM ONLY' => ['DELETE FROM ONLY parent WHERE id = 1', 'parent'];
        yield 'FROM LATERAL' => ['SELECT * FROM LATERAL (SELECT * FROM orders) o', 'orders'];
        yield 'escaped double quote in an identifier' => ['SELECT * FROM "my""tbl"', 'my"tbl'];
        yield 'catalog-qualified table' => ['SELECT * FROM db.public.users', 'users'];
        yield 'blank bracketed identifier' => ['SELECT * FROM [ ]', null];
        yield 'bracketed identifiers' => ['SELECT * FROM [dbo].[users]', 'users'];
        yield 'escaped backtick in an identifier' => ['SELECT * FROM `my``tbl`', 'my`tbl'];
    }

    #[DataProvider('tableNameProvider')]
    public function testLabelsTheTable(string $sql, ?string $expected): void
    {
        self::assertSame($expected, (new SqlLabeller())->label($sql)->table);
    }

    public function testAnEmptyTableIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Reason: the type forbids it; the test pins the runtime check for callers without static analysis.
        new SqlLabels(StatementType::SELECT, ''); // @phpstan-ignore argument.type
    }

    public function testRepeatedStatementIsServedFromCache(): void
    {
        $labeller = new SqlLabeller();

        $labels = $labeller->label('SELECT * FROM users');

        self::assertSame(StatementType::SELECT, $labels->type);
        self::assertSame('users', $labels->table);
        self::assertSame($labels, $labeller->label('SELECT * FROM users'));
    }

    public function testStatementsSharingTheParsedPrefixShareLabels(): void
    {
        $labeller = new SqlLabeller();
        $prefix = 'SELECT * FROM users WHERE id IN (' . str_repeat('1, ', 6_000);

        self::assertSame($labeller->label($prefix . '2)'), $labeller->label($prefix . '3)'));
    }

    public function testEvictsTheLeastRecentlyUsedStatement(): void
    {
        $labeller = new SqlLabeller();
        $kept = $labeller->label('SELECT * FROM kept');
        $evicted = $labeller->label('SELECT * FROM evicted');
        for ($i = 0; $i < 254; $i++) {
            $labeller->label('SELECT * FROM t' . $i);
        }

        self::assertSame($kept, $labeller->label('SELECT * FROM kept'));
        $labeller->label('SELECT * FROM overflow');

        self::assertSame($kept, $labeller->label('SELECT * FROM kept'));
        $relabelled = $labeller->label('SELECT * FROM evicted');
        self::assertNotSame($evicted, $relabelled);
        self::assertSame('evicted', $relabelled->table);
    }
}
