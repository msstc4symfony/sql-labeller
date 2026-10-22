<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Domain\Enum;

enum StatementType: string
{
    case SELECT = 'select';
    case INSERT = 'insert';
    case UPDATE = 'update';
    case DELETE = 'delete';
    case OTHER = 'other';
}
