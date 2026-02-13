<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser\Ast;

final class TernaryNode implements Node
{
    public function __construct(
        public readonly Node $condition,
        public readonly Node $ifTrue,
        public readonly Node $ifFalse,
    ) {
    }
}
