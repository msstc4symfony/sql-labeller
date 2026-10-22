<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Domain\ValueObject;

use InvalidArgumentException;
use Msstc4Symfony\SqlLabeller\Domain\Enum\StatementType;

final readonly class SqlLabels
{
    /**
     * @param non-empty-string|null $table unquoted, without schema; null when no table is found
     *
     * @throws InvalidArgumentException for an empty table name
     */
    public function __construct(
        public StatementType $type,
        public ?string $table,
    ) {
        if ($table === '') {
            throw new InvalidArgumentException('A table name cannot be empty; null stands for no table.');
        }
    }
}
