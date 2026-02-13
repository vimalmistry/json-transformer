<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Evaluator;

final class FunctionRegistry
{
    /** @var array<string, callable> */
    private array $custom = [];

    /**
     * Register a custom function.
     *
     * The callable receives (mixed $input, mixed ...$args) and returns mixed.
     */
    public function add(string $name, callable $fn): void
    {
        $this->custom[$name] = $fn;
    }

    /**
     * Execute a built-in or custom function.
     *
     * @param string $name      Function name
     * @param mixed  $input     The pipe input value
     * @param array  $args      Resolved arguments
     * @return mixed
     */
    public function call(string $name, mixed $input, array $args = []): mixed
    {
        if (isset($this->custom[$name])) {
            return $this->custom[$name]($input, ...$args);
        }

        return match ($name) {
            "trim" => is_string($input) ? trim($input) : $input,
            "lower" => is_string($input) ? strtolower($input) : $input,
            "upper" => is_string($input) ? strtoupper($input) : $input,
            "default" => $input ?? ($args[0] ?? null),
            "date" => $this->formatDate($input, $args[0] ?? "Y-m-d H:i:s"),
            "count" => is_countable($input) ? count($input) : 0,
            "money" => $this->formatMoney($input, $args[0] ?? "USD"),
            "now" => date("Y-m-d\TH:i:sP"),
            "to_boolean", "to_bool" => $this->toBoolean($input),
            "to_string" => $input === null ? "" : (string) $input,
            "to_integer", "to_int" => $input === null ? 0 : (int) $input,
            "to_float" => $input === null ? 0.0 : (float) $input,
            "to_array" => (array) $input,
            default => $input,
        };
    }

    public function isStandaloneFunction(string $name): bool
    {
        return $name === "now";
    }

    public function isCustom(string $name): bool
    {
        return isset($this->custom[$name]);
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(
                strtolower($value),
                ["true", "1", "yes", "on"],
                true,
            );
        }
        return (bool) $value;
    }

    private function formatDate(mixed $value, string $format): ?string
    {
        if ($value === null) {
            return null;
        }
        $timestamp = is_numeric($value)
            ? (int) $value
            : strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }
        return date($format, $timestamp);
    }

    private function formatMoney(mixed $value, string $currency): string
    {
        $amount = is_numeric($value) ? (float) $value : 0.0;
        return number_format($amount, 2, ".", "") . " " . $currency;
    }
}
