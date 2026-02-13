<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer;

use Vimal\JsonTransformer\Evaluator\PathResolver;

final class Context
{
    /** @var array<array{name: string, value: mixed}> */
    private array $scopeStack = [];

    private PathResolver $pathResolver;

    /**
     * @param array<string, mixed> $source  The source data
     * @param array<string, mixed> $vars    Resolved variable values
     * @param array<string, string> $macros Raw macro expressions
     */
    public function __construct(
        public readonly array $source,
        public readonly array $vars,
        public readonly array $macros,
    ) {
        $this->pathResolver = new PathResolver();
    }

    public function pushScope(string $name, mixed $value): void
    {
        $this->scopeStack[] = ['name' => $name, 'value' => $value];
    }

    public function popScope(): void
    {
        array_pop($this->scopeStack);
    }

    /**
     * Resolves a full path like "data.user.id" or "node.name".
     * Checks scope stack first, then source data.
     */
    public function resolvePath(string $path): mixed
    {
        $firstDot = strpos($path, '.');
        $firstSegment = $firstDot !== false ? substr($path, 0, $firstDot) : $path;
        $restPath = $firstDot !== false ? substr($path, $firstDot + 1) : null;

        // Check scope stack (last pushed first)
        for ($i = count($this->scopeStack) - 1; $i >= 0; $i--) {
            if ($this->scopeStack[$i]['name'] === $firstSegment) {
                $scopeValue = $this->scopeStack[$i]['value'];
                if ($restPath !== null) {
                    return $this->pathResolver->resolve($restPath, $scopeValue);
                }
                return $scopeValue;
            }
        }

        // Fall back to source data
        return $this->pathResolver->resolve($path, $this->source);
    }

    /**
     * Resolves a relative path against a given value.
     * Used for dot-context expressions like .node.status
     */
    public function resolveRelative(string $subPath, mixed $value): mixed
    {
        return $this->pathResolver->resolve($subPath, $value);
    }

    public function getVar(string $name): mixed
    {
        return $this->vars[$name] ?? null;
    }

    public function getMacro(string $name): ?string
    {
        return $this->macros[$name] ?? null;
    }
}
