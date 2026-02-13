<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class VarRefNode implements Node
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
