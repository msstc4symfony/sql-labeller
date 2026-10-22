<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Test\Integration;

use Closure;
use Composer\InstalledVersions;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Msstc4Symfony\SqlLabeller\Domain\Enum\StatementType;
use Msstc4Symfony\SqlLabeller\Domain\Helper\SqlLabeller;
use Msstc4Symfony\SqlLabeller\Test\Fixtures\FixedPlatformMiddleware;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The SQL DBAL's QueryBuilder writes for each platform, which is what metrics and profiling see.
 */
#[CoversNothing]
final class DbalQueryBuilderTest extends TestCase
{
    private const PLATFORMS = [
        'MySQL' => MySQLPlatform::class,
        'PostgreSQL' => PostgreSQLPlatform::class,
        'SQLite' => SQLitePlatform::class,
        'SQL Server' => SQLServerPlatform::class,
        'Oracle' => OraclePlatform::class,
    ];

    /**
     * @return iterable<
     *     string,
     *     array{class-string<AbstractPlatform>, Closure(QueryBuilder): QueryBuilder, StatementType}
     * >
     */
    public static function statements(): iterable
    {
        $statements = [
            'select with a join and a limit' => [
                static fn (QueryBuilder $qb): QueryBuilder => $qb->select('o.id')
                    ->from('orders', 'o')
                    ->innerJoin('o', 'customers', 'c', 'c.id = o.customer_id')
                    ->where('o.id IN (:ids)')
                    ->setFirstResult(20)
                    ->setMaxResults(10),
                StatementType::SELECT,
            ],
            'insert' => [
                static fn (QueryBuilder $qb): QueryBuilder => $qb->insert('orders')->values(['id' => '?']),
                StatementType::INSERT,
            ],
            'update' => [
                static fn (QueryBuilder $qb): QueryBuilder => $qb->update('orders')
                    ->set('status', '?')
                    ->where('id = ?'),
                StatementType::UPDATE,
            ],
            'delete' => [
                static fn (QueryBuilder $qb): QueryBuilder => $qb->delete('orders')->where('id = ?'),
                StatementType::DELETE,
            ],
        ];
        foreach (self::PLATFORMS as $platformName => $platform) {
            foreach ($statements as $statementName => [$build, $type]) {
                yield $platformName . ' ' . $statementName => [$platform, $build, $type];
            }
        }
    }

    /**
     * @param class-string<AbstractPlatform> $platform
     * @param Closure(QueryBuilder): QueryBuilder $build
     */
    #[DataProvider('statements')]
    public function testLabelsTheStatementsOfTheQueryBuilder(
        string $platform,
        Closure $build,
        StatementType $type,
    ): void {
        $sql = $build($this->connection($platform)->createQueryBuilder())->getSQL();

        $labels = (new SqlLabeller())->label($sql);

        self::assertSame([$type, 'orders'], [$labels->type, $labels->table], $sql);
    }

    /**
     * @param class-string<AbstractPlatform> $platform
     */
    #[DataProvider('unions')]
    public function testLabelsAUnionOfTheQueryBuilderByItsFirstPart(string $platform, StatementType $type): void
    {
        $qb = $this->connection($platform)->createQueryBuilder();
        if (version_compare(InstalledVersions::getVersion('doctrine/dbal') ?? '0', '4.1', '<')) {
            self::markTestSkipped('QueryBuilder::union() needs doctrine/dbal 4.1');
        }

        $sql = $qb->union('SELECT id FROM orders')->addUnion('SELECT id FROM archived_orders')->getSQL();

        $labels = (new SqlLabeller())->label($sql);

        self::assertSame([$type, 'orders'], [$labels->type, $labels->table], $sql);
    }

    /**
     * @return iterable<string, array{class-string<AbstractPlatform>, StatementType}>
     */
    public static function unions(): iterable
    {
        foreach (self::PLATFORMS as $name => $platform) {
            // SQLite cannot parenthesise the parts of a UNION, so the statement starts with SELECT there.
            $type = $platform === SQLitePlatform::class ? StatementType::SELECT : StatementType::OTHER;

            yield $name => [$platform, $type];
        }
    }

    /**
     * @param class-string<AbstractPlatform> $platform
     */
    private function connection(string $platform): Connection
    {
        if (!class_exists(DriverManager::class)) {
            self::markTestSkipped('doctrine/dbal is not installed');
        }

        $configuration = (new Configuration())->setMiddlewares([new FixedPlatformMiddleware(new $platform())]);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration);
        self::assertInstanceOf($platform, $connection->getDatabasePlatform());

        return $connection;
    }
}
