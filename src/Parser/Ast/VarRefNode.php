<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser\Ast;

final class VarRefNode implements Node
{
    public function __construct(public readonly string $name) {}
}
