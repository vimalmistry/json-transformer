<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Directive;

use O360Main\JsonTransformer\Context;
use O360Main\JsonTransformer\Evaluator\ExpressionEvaluator;

final class SwitchDirective implements DirectiveHandler
{
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
    ) {}

    public function handle(array $definition, Context $ctx): mixed
    {
        $switchValue = $this->evaluator->evaluateExpression(
            $definition["@switch"],
            $ctx,
        );
        $cases = $definition["@cases"] ?? [];

        foreach ($cases as $caseKey => $caseExpr) {
            if ((string) $switchValue === (string) $caseKey) {
                return $this->evaluator->evaluateExpression($caseExpr, $ctx);
            }
        }

        if (isset($definition["@default"])) {
            return $this->evaluator->evaluateExpression(
                $definition["@default"],
                $ctx,
            );
        }

        return null;
    }
}
