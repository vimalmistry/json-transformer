<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

/**
 * Holds a raw expression string for lazy evaluation (used in filter/sort).
 */
final class RawExpressionNode implements Node
{
    public function __construct(
        public readonly string $expression,
    ) {
    }
}
