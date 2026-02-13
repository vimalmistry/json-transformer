<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Directive;

use O360Main\JsonTransformer\Context;
use O360Main\JsonTransformer\Evaluator\ExpressionEvaluator;

final class IfDirective implements DirectiveHandler
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
    ) {}

    public function handle(array $definition, Context $ctx): mixed
    {
        $condition = $this->evaluator->evaluateExpression(
            $definition["@if"],
            $ctx,
        );

        $branch = $this->isTruthy($condition) ? "@then" : "@else";

        if (!isset($definition[$branch])) {
            return null;
        }

        return $this->evaluator->evaluateExpression($definition[$branch], $ctx);
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return !empty($value);
    }
}
