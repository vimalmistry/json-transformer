<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser\Ast;

final class FunctionCallNode implements Node
{
    /**
     * @param Node[] $arguments
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {}
}
