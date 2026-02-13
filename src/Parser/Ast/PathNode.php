<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class PathNode implements Node
{
    public function __construct(
        public readonly string $path,
    ) {
    }
}
