<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Schema;

use O360Main\JsonTransformer\Context;
use O360Main\JsonTransformer\Directive\EachDirective;
use O360Main\JsonTransformer\Directive\IfDirective;
use O360Main\JsonTransformer\Directive\SwitchDirective;
use O360Main\JsonTransformer\Evaluator\ExpressionEvaluator;

final class SchemaWalker
{
    private IfDirective $ifDirective;
    private SwitchDirective $switchDirective;
    private EachDirective $eachDirective;

    public function __construct(private readonly ExpressionEvaluator $evaluator)
    {
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
            if (str_starts_with($key, "@")) {
                continue;
            }

            // Optional field: key ends with ? — omit from output if null
            $omitNull = false;
            if (str_ends_with($key, "?")) {
                $omitNull = true;
                $key = substr($key, 0, -1);
            }

            // Array iteration: key ends with []
            if (str_ends_with($key, "[]")) {
                $outputKey = substr($key, 0, -2);
                if (is_array($value) && isset($value["@each"])) {
                    $resolved = $this->eachDirective->handle($value, $ctx);
                    if ($omitNull && ($resolved === null || $resolved === [])) {
                        continue;
                    }
                    $result[$outputKey] = $resolved;
                }
                continue;
            }

            // String expression
            if (is_string($value)) {
                $resolved = $this->evaluator->evaluateExpression($value, $ctx);
                if ($omitNull && $resolved === null) {
                    continue;
                }
                $result[$key] = $resolved;
                continue;
            }

            // Object/array value
            if (is_array($value)) {
                // @if directive
                if (isset($value["@if"])) {
                    $resolved = $this->ifDirective->handle($value, $ctx);
                    if ($omitNull && $resolved === null) {
                        continue;
                    }
                    $result[$key] = $resolved;
                    continue;
                }

                // @switch directive
                if (isset($value["@switch"])) {
                    $resolved = $this->switchDirective->handle($value, $ctx);
                    if ($omitNull && $resolved === null) {
                        continue;
                    }
                    $result[$key] = $resolved;
                    continue;
                }

                // Nested object — recurse
                $result[$key] = $this->walk($value, $ctx);
                continue;
            }

            // Scalar pass-through (numbers, booleans, null)
            if ($omitNull && $value === null) {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
