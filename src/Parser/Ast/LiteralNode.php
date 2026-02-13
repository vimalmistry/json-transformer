<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class LiteralNode implements Node
{
    public function __construct(
        public readonly mixed $value,
    ) {
    }
}
