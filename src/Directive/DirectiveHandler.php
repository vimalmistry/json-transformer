<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Directive;

use O360Main\JsonTransformer\Context;

interface DirectiveHandler
{
    public function handle(array $definition, Context $ctx): mixed;
}
