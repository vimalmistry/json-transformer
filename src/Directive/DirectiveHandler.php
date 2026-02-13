<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Directive;

use Vimal\JsonTransformer\Context;

interface DirectiveHandler
{
    public function handle(array $definition, Context $ctx): mixed;
}
