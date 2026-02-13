<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class MacroCallNode implements Node
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
