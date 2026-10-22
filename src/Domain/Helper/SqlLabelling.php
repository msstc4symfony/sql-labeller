<?php

declare(strict_types=1);

namespace Msstc4Symfony\SqlLabeller\Domain\Helper;

use Msstc4Symfony\SqlLabeller\Domain\ValueObject\SqlLabels;

interface SqlLabelling
{
    public function label(string $sql): SqlLabels;
}
