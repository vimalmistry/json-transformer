<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Directive;

use O360Main\JsonTransformer\Context;
use O360Main\JsonTransformer\Evaluator\ExpressionEvaluator;

final class EachDirective implements DirectiveHandler
{
    /** @var callable(array, Context): array */
    private $schemaWalkerCallback;

    /**
     * @param callable(array, Context): array $schemaWalkerCallback
     */
    public function __construct(
        private readonly ExpressionEvaluator $evaluator,
        callable $schemaWalkerCallback,
    ) {
        $this->schemaWalkerCallback = $schemaWalkerCallback;
    }

    public function handle(array $definition, Context $ctx): mixed
    {
        $items = $this->evaluator->evaluateExpression(
            $definition["@each"],
            $ctx,
        );

        if (!is_array($items)) {
            return [];
        }

        $alias = $definition["@as"] ?? "this";
        $template = $definition["@do"] ?? [];
        $result = [];

        foreach ($items as $item) {
            // For GraphQL edges pattern: if item has a 'node' key, auto-extract
            // so that expressions like this.id resolve to edge.node.id
            $scopeValue =
                is_array($item) && array_key_exists("node", $item)
                    ? $item["node"]
                    : $item;
            $ctx->pushScope($alias, $scopeValue);
            $ctx->pushItem($scopeValue);
            $result[] = ($this->schemaWalkerCallback)($template, $ctx);
            $ctx->popItem();
            $ctx->popScope();
        }

        return $result;
    }
}
