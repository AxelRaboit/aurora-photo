<?php

declare(strict_types=1);

namespace Aurora\Module\Photo\Gallery\Exception;

use DomainException;

final class MaxPicksReachedException extends DomainException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct(sprintf('Visitor reached the gallery max picks limit of %d.', $limit));
    }
}
