<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser\Ast;

final class ConcatNode implements Node
{
    /** @var Node[] */
    public readonly array $parts;

    public function __construct(Node ...$parts)
    {
        $this->parts = $parts;
    }
}
