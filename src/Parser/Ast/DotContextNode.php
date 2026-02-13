<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser\Ast;

final class DotContextNode implements Node
{
    public function __construct(public readonly ?string $subPath = null) {}
}
