<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class ComparisonNode implements Node
{
    public function __construct(
        public readonly Node $left,
        public readonly string $operator,
        public readonly Node $right,
    ) {
    }
}
