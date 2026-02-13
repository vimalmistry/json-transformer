<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Evaluator;

use O360Main\JsonTransformer\Context;
use O360Main\JsonTransformer\Parser\Ast\ComparisonNode;
use O360Main\JsonTransformer\Parser\Ast\ConcatNode;
use O360Main\JsonTransformer\Parser\Ast\DotContextNode;
use O360Main\JsonTransformer\Parser\Ast\FunctionCallNode;
use O360Main\JsonTransformer\Parser\Ast\LiteralNode;
use O360Main\JsonTransformer\Parser\Ast\MacroCallNode;
use O360Main\JsonTransformer\Parser\Ast\Node;
use O360Main\JsonTransformer\Parser\Ast\PathNode;
use O360Main\JsonTransformer\Parser\Ast\PipeNode;
use O360Main\JsonTransformer\Parser\Ast\RawExpressionNode;
use O360Main\JsonTransformer\Parser\Ast\TernaryNode;
use O360Main\JsonTransformer\Parser\Ast\VarRefNode;
use O360Main\JsonTransformer\Parser\ExpressionParser;

final class ExpressionEvaluator
{
    private FunctionRegistry $functions;
    private ExpressionParser $parser;

    /** @var array<string, Node> Cached parsed AST nodes keyed by expression string */
    private array $astCache = [];

    public function __construct()
    {
        $this->functions = new FunctionRegistry();
        $this->parser = new ExpressionParser();
    }

    public function getFunctionRegistry(): FunctionRegistry
    {
        return $this->functions;
    }

    /**
     * Evaluate a parsed AST node.
     *
     * @param Node    $node       The AST node
     * @param Context $ctx        The execution context
     * @param mixed   $pipeValue  Current pipe value (for dot-context)
     */
    public function evaluate(
        Node $node,
        Context $ctx,
        mixed $pipeValue = null,
    ): mixed {
        return match (true) {
            $node instanceof LiteralNode => $node->value,
            $node instanceof PathNode => $this->evaluatePath($node, $ctx),
            $node instanceof VarRefNode => $ctx->getVar($node->name),
            $node instanceof DotContextNode => $this->evaluateDotContext(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof FunctionCallNode => $this->evaluateFunction(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof MacroCallNode => $this->evaluateMacro(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof ConcatNode => $this->evaluateConcat(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof ComparisonNode => $this->evaluateComparison(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof PipeNode => $this->evaluatePipe(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof TernaryNode => $this->evaluateTernary(
                $node,
                $ctx,
                $pipeValue,
            ),
            $node instanceof RawExpressionNode => $node->expression,
            default => null,
        };
    }

    /**
     * Parse and evaluate a raw expression string.
     */
    public function evaluateExpression(
        string $expression,
        Context $ctx,
        mixed $pipeValue = null,
    ): mixed {
        $node = $this->astCache[$expression] ??= $this->parser->parse(
            $expression,
        );
        return $this->evaluate($node, $ctx, $pipeValue);
    }

    private function evaluatePath(PathNode $node, Context $ctx): mixed
    {
        // Check if it's a standalone function like now()
        if ($this->functions->isStandaloneFunction($node->path)) {
            return $this->functions->call($node->path, null);
        }

        return $ctx->resolvePath($node->path);
    }

    private function evaluateDotContext(
        DotContextNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        if ($node->subPath === null) {
            return $pipeValue;
        }
        if (is_array($pipeValue) || is_object($pipeValue)) {
            return $ctx->resolveRelative($node->subPath, $pipeValue);
        }
        return null;
    }

    private function evaluateFunction(
        FunctionCallNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        $name = $node->name;

        // Standalone functions
        if (
            $this->functions->isStandaloneFunction($name) &&
            $pipeValue === null
        ) {
            return $this->functions->call($name, null);
        }

        // filter and sort have special handling — their argument is a raw expression
        if ($name === "filter") {
            return $this->evaluateFilter($node, $ctx, $pipeValue);
        }
        if ($name === "sort") {
            return $this->evaluateSort($node, $ctx, $pipeValue);
        }

        // Resolve arguments
        $resolvedArgs = [];
        foreach ($node->arguments as $arg) {
            $resolvedArgs[] = $this->evaluate($arg, $ctx, $pipeValue);
        }

        return $this->functions->call($name, $pipeValue, $resolvedArgs);
    }

    private function evaluateFilter(
        FunctionCallNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        if (!is_array($pipeValue)) {
            return [];
        }

        $rawExpr = $node->arguments[0] ?? null;
        if (!$rawExpr instanceof RawExpressionNode) {
            return $pipeValue;
        }

        $exprString = $rawExpr->expression;
        $result = [];

        foreach ($pipeValue as $item) {
            $itemValue = $this->evaluateExpression($exprString, $ctx, $item);
            if ($this->isTruthy($itemValue)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function evaluateSort(
        FunctionCallNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        if (!is_array($pipeValue)) {
            return [];
        }

        $rawExpr = $node->arguments[0] ?? null;
        if (!$rawExpr instanceof RawExpressionNode) {
            return $pipeValue;
        }

        $exprString = $rawExpr->expression;
        $items = array_values($pipeValue);

        usort($items, function ($a, $b) use ($exprString, $ctx) {
            $aVal = $this->evaluateExpression($exprString, $ctx, $a);
            $bVal = $this->evaluateExpression($exprString, $ctx, $b);
            return $aVal <=> $bVal;
        });

        return $items;
    }

    private function evaluateMacro(
        MacroCallNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        $macroExpr = $ctx->getMacro($node->name);
        if ($macroExpr === null) {
            return $pipeValue;
        }

        // A macro expression uses "." for current value, pipe through transforms
        return $this->evaluateExpression($macroExpr, $ctx, $pipeValue);
    }

    private function evaluateConcat(
        ConcatNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): string {
        $result = '';
        foreach ($node->parts as $part) {
            $value = $this->evaluate($part, $ctx, $pipeValue);
            $result .= $value === null ? '' : (string) $value;
        }
        return $result;
    }

    private function evaluateComparison(
        ComparisonNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): bool {
        $left = $this->evaluate($node->left, $ctx, $pipeValue);
        $right = $this->evaluate($node->right, $ctx, $pipeValue);

        return match ($node->operator) {
            "==" => $left == $right,
            "!=" => $left != $right,
            default => false,
        };
    }

    private function evaluateTernary(
        TernaryNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        $condition = $this->evaluate($node->condition, $ctx, $pipeValue);

        return $this->isTruthy($condition)
            ? $this->evaluate($node->ifTrue, $ctx, $pipeValue)
            : $this->evaluate($node->ifFalse, $ctx, $pipeValue);
    }

    private function evaluatePipe(
        PipeNode $node,
        Context $ctx,
        mixed $pipeValue,
    ): mixed {
        // Evaluate the input (left side)
        $current = $this->evaluate($node->input, $ctx, $pipeValue);

        // Chain through each step
        foreach ($node->steps as $step) {
            $current = $this->evaluate($step, $ctx, $current);
        }

        return $current;
    }

    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return !empty($value);
    }
}
