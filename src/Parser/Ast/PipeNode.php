<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser\Ast;

final class PipeNode implements Node
{
    /**
     * @param Node[] $steps
     */
    public function __construct(
        public readonly Node $input,
        public readonly array $steps,
    ) {}
}
