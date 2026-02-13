<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer;

use O360Main\JsonTransformer\Evaluator\ExpressionEvaluator;
use O360Main\JsonTransformer\Schema\SchemaWalker;

final class Transformer
{
    private static ?self $instance = null;

    private ExpressionEvaluator $evaluator;
    private ?string $templatePath = null;

    /** @var array<string, array> Cached parsed template schemas */
    private array $templateCache = [];

    public function __construct()
    {
        $this->evaluator = new ExpressionEvaluator();
    }

    /**
     * Get the global singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a custom pipe function.
     *
     * The callable receives (mixed $input, mixed ...$args) and returns mixed.
     */
    public function addFunction(string $name, callable $fn): self
    {
        $this->evaluator->getFunctionRegistry()->add($name, $fn);
        return $this;
    }

    /**
     * Set the base directory for template file discovery.
     */
    public function setPath(string $path): self
    {
        $this->templatePath = rtrim($path, "/");
        return $this;
    }

    /**
     * Apply a schema to source data.
     *
     * @param string|array $schema Template name (e.g. 'final' or 'final.tpl') or inline schema array
     * @param array        $source The source data
     */
    public function apply(string|array $schema, array $source): array
    {
        if (is_array($schema)) {
            return $this->transform($source, $schema);
        }

        if ($this->templatePath === null) {
            throw new \RuntimeException(
                "Template path not set. Call setPath() first.",
            );
        }

        // Strip .tpl suffix if provided: 'final.tpl' -> 'final'
        $name = str_ends_with($schema, ".tpl")
            ? substr($schema, 0, -4)
            : $schema;

        if (!isset($this->templateCache[$name])) {
            $file = $this->findTemplate($name);
            $content = file_get_contents($file);

            $this->templateCache[$name] = str_ends_with($file, ".jsonc")
                ? $this->parseJsonc($content)
                : json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->transform($source, $this->templateCache[$name]);
    }

    /**
     * Transform source data using a schema definition.
     *
     * @param array<string, mixed> $source The source data
     * @param array<string, mixed> $schema The mapping schema
     * @return array<string, mixed> The transformed output
     */
    public function transform(array $source, array $schema): array
    {
        // Extract @vars and @macros from schema
        $varsDefinitions = $schema["@vars"] ?? [];
        $macros = $schema["@macros"] ?? [];

        // Remove meta keys from schema for walking
        $walkableSchema = array_filter(
            $schema,
            fn(string $key) => !str_starts_with($key, "@") &&
                $key !== "version",
            ARRAY_FILTER_USE_KEY,
        );

        // Resolve @vars expressions against source data
        $resolvedVars = $this->resolveVars($varsDefinitions, $source, $macros);

        // Build context
        $ctx = new Context($source, $resolvedVars, $macros);

        // Walk the schema
        $walker = new SchemaWalker($this->evaluator);
        return $walker->walk($walkableSchema, $ctx);
    }

    /**
     * Evaluate a single DSL expression against source data.
     *
     * @param string $expression e.g. "name |> upper", "first + ' ' + last"
     * @param array  $source     The source data
     */
    public function applyString(string $expression, array $source): mixed
    {
        $ctx = new Context($source, [], []);
        return $this->evaluator->evaluateExpression($expression, $ctx);
    }

    /**
     * Transform JSON strings.
     */
    public function transformJson(
        string $sourceJson,
        string $schemaJson,
    ): string {
        $source = json_decode($sourceJson, true, 512, JSON_THROW_ON_ERROR);
        $schema = json_decode($schemaJson, true, 512, JSON_THROW_ON_ERROR);

        $result = $this->transform($source, $schema);

        return json_encode(
            $result,
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param array<string, string> $varsDefinitions
     * @param array<string, mixed>  $source
     * @param array<string, string> $macros
     * @return array<string, mixed>
     */
    private function resolveVars(
        array $varsDefinitions,
        array $source,
        array $macros,
    ): array {
        $resolved = [];
        $ctx = new Context($source, [], $macros);

        foreach ($varsDefinitions as $name => $expression) {
            $value = $this->evaluator->evaluateExpression($expression, $ctx);
            $resolved[$name] = $value;
            $ctx->setVar($name, $value);
        }

        return $resolved;
    }

    /**
     * Recursively search for a template file by name.
     *
     * @throws \RuntimeException If template not found
     */
    private function findTemplate(string $name): string
    {
        $extensions = [".tpl.json", ".tpl.jsonc"];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->templatePath,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $filename = $file->getFilename();
            foreach ($extensions as $ext) {
                if ($filename === $name . $ext) {
                    return $file->getPathname();
                }
            }
        }

        throw new \RuntimeException(
            "Template '{$name}' not found in '{$this->templatePath}'. " .
                "Expected {$name}.tpl.json or {$name}.tpl.jsonc",
        );
    }

    /**
     * Parse JSONC content (JSON with comments).
     *
     * @return array<string, mixed>
     */
    private function parseJsonc(string $content): array
    {
        // Remove single-line comments (// ...)
        $content = preg_replace('#//.*$#m', "", $content);
        // Remove multi-line comments (/* ... */)
        $content = preg_replace("#/\*.*?\*/#s", "", $content);
        // Remove trailing commas before } or ]
        $content = preg_replace("/,\s*([\]}])/s", '$1', $content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}
