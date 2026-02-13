<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Schema;

use Vimal\JsonTransformer\Context;
use Vimal\JsonTransformer\Directive\EachDirective;
use Vimal\JsonTransformer\Directive\IfDirective;
use Vimal\JsonTransformer\Directive\SwitchDirective;
use Vimal\JsonTransformer\Evaluator\ExpressionEvaluator;

final class SchemaWalker
{
    private IfDirective $ifDirective;
    private SwitchDirective $switchDirective;
    private EachDirective $eachDirective;

    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
    ) {
        $this->ifDirective = new IfDirective($this->evaluator);
        $this->switchDirective = new SwitchDirective($this->evaluator);
        $this->eachDirective = new EachDirective(
            $this->evaluator,
            fn(array $template, Context $ctx) => $this->walk($template, $ctx),
        );
    }

    /**
     * Walk a schema template and produce the output.
     */
    public function walk(array $schema, Context $ctx): array
    {
        $result = [];

        foreach ($schema as $key => $value) {
            // Skip directive keys (handled by parent)
            if (str_starts_with($key, '@')) {
                continue;
            }

            // Array iteration: key ends with []
            if (str_ends_with($key, '[]')) {
                $outputKey = substr($key, 0, -2);
                if (is_array($value) && isset($value['@each'])) {
                    $result[$outputKey] = $this->eachDirective->handle($value, $ctx);
                }
                continue;
            }

            // String expression
            if (is_string($value)) {
                $result[$key] = $this->evaluator->evaluateExpression($value, $ctx);
                continue;
            }

            // Object/array value
            if (is_array($value)) {
                // @if directive
                if (isset($value['@if'])) {
                    $result[$key] = $this->ifDirective->handle($value, $ctx);
                    continue;
                }

                // @switch directive
                if (isset($value['@switch'])) {
                    $result[$key] = $this->switchDirective->handle($value, $ctx);
                    continue;
                }

                // Nested object — recurse
                $result[$key] = $this->walk($value, $ctx);
                continue;
            }

            // Scalar pass-through (numbers, booleans, null)
            $result[$key] = $value;
        }

        return $result;
    }
}
