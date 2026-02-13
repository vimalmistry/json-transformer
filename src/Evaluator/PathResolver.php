<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Evaluator;

final class PathResolver
{
    /** @var array<string, array> Cached parsed path segments */
    private array $segmentCache = [];

    /**
     * Resolves a dot-notation path against data.
     * Supports array index access like edges[0].node.id
     * Returns null for missing paths.
     */
    public function resolve(string $path, mixed $data): mixed
    {
        $segments = $this->parsePath($path);

        $current = $data;
        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }

            if ($segment["type"] === "key") {
                if (
                    is_array($current) &&
                    array_key_exists($segment["value"], $current)
                ) {
                    $current = $current[$segment["value"]];
                } elseif (
                    is_object($current) &&
                    property_exists($current, $segment["value"])
                ) {
                    $current = $current->{$segment["value"]};
                } else {
                    return null;
                }
            } elseif ($segment["type"] === "index") {
                if (
                    is_array($current) &&
                    array_key_exists($segment["value"], $current)
                ) {
                    $current = $current[$segment["value"]];
                } else {
                    return null;
                }
            }
        }

        return $current;
    }

    /**
     * @return array<array{type: string, value: string|int}>
     */
    private function parsePath(string $path): array
    {
        if (isset($this->segmentCache[$path])) {
            return $this->segmentCache[$path];
        }

        $segments = [];
        $parts = explode(".", $path);

        foreach ($parts as $part) {
            // Check for array index: edges[0]
            if (
                preg_match(
                    '/^([a-zA-Z_][a-zA-Z0-9_]*)\[(\d+)\]$/',
                    $part,
                    $matches,
                )
            ) {
                $segments[] = ["type" => "key", "value" => $matches[1]];
                $segments[] = ["type" => "index", "value" => (int) $matches[2]];
            } else {
                $segments[] = ["type" => "key", "value" => $part];
            }
        }

        return $this->segmentCache[$path] = $segments;
    }
}
