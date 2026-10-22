<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Test\Fixtures;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\ServerVersionProvider;
use Override;

/**
 * Gives a connection the platform of the test: DBAL 4 ignores the "platform" connection parameter.
 */
final readonly class FixedPlatformMiddleware implements Middleware
{
    public function __construct(
        private AbstractPlatform $platform,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): Driver
    {
        $platform = $this->platform;

        return new class($driver, $platform) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly AbstractPlatform $platform)
            {
                parent::__construct($driver);
            }

            #[Override]
            public function getDatabasePlatform(ServerVersionProvider $versionProvider): AbstractPlatform
            {
                return $this->platform;
            }
        };
    }
}
