# JSON Transformer

A declarative JSON mapping DSL for PHP. Define your desired output structure as a JSON schema with expressions, and the library transforms your source data to match.

Zero dependencies. PHP 8.1+.

## Installation

```bash
composer require vimal/json-transformer
```

## Quick Start

```php
use Vimal\JsonTransformer\Transformer;

// Configure once globally
Transformer::getInstance()->setPath(__DIR__ . '/templates');

// Use anywhere — no need to create or pass instances
$result = Transformer::getInstance()->apply('user.tpl', $source);
```

Or use a local instance:

```php
$transformer = new Transformer();

$source = [
    'data' => [
        'user' => [
            'name' => '  John Doe  ',
            'email' => '  JOHN@EXAMPLE.COM  ',
            'active' => true,
            'age' => '30',
        ],
    ],
];

$schema = [
    'name' => 'data.user.name |> trim |> lower',
    'email' => 'data.user.email |> trim |> lower',
    'status' => "data.user.active == true ? 'enabled' : 'disabled'",
    'age' => 'data.user.age |> to_integer',
];

$result = $transformer->transform($source, $schema);
// [
//     'name' => 'john doe',
//     'email' => 'john@example.com',
//     'status' => 'enabled',
//     'age' => 30,
// ]
```

You can also work with raw JSON strings:

```php
$resultJson = $transformer->transformJson($sourceJson, $schemaJson);
```

## Schema DSL

The schema is output-driven. Its structure IS the output structure. Values are expressions that pull and transform data from the source.

### Path Expressions

Access source data using dot notation:

```json
{
    "id": "data.user.id",
    "city": "data.user.address.city",
    "first": "data.items[0].name"
}
```

Array index access is supported: `data.items[0].name`.

Missing paths return `null`.

### Pipe Expressions

Chain transformations with `|>`:

```json
{
    "name": "data.user.name |> trim |> lower",
    "country": "data.user.country |> upper"
}
```

### Literals

String, boolean, number, and null literals:

```json
{
    "label": "'hello'",
    "flag": "true",
    "nothing": "null",
    "count": "42"
}
```

### Variables (`@vars`)

Pre-compute values before mapping. Variables can reference source data and use expressions. Later variables can reference earlier ones.

```json
{
    "@vars": {
        "currency": "data.settings.currency |> default('USD')",
        "country_code": "data.user.country |> upper",
        "is_indian": "data.user.country == 'IN'"
    },
    "currency": "@vars.currency",
    "country": "@vars.country_code",
    "is_indian": "@vars.is_indian"
}
```

### Macros (`@macros`)

Reusable transformation pipelines. Use `.` for the incoming pipe value. Reference with `@name` after `|>`.

```json
{
    "@macros": {
        "normalize_name": ". |> trim |> lower",
        "safe_email": ". |> trim |> lower |> default('unknown@email.com')",
        "money_format": ". |> money(@vars.currency)"
    },
    "name": "data.user.name |> @normalize_name",
    "email": "data.user.email |> @safe_email",
    "price": "data.product.price |> @money_format"
}
```

### Conditionals

Block form with `@if`:

```json
{
    "status": {
        "@if": "data.user.active == true",
        "@then": "'enabled'",
        "@else": "'disabled'"
    }
}
```

Inline ternary (single line):

```json
{
    "status": "data.user.active == true ? 'enabled' : 'disabled'"
}
```

Both forms work. Ternary can also be chained with pipes:

```json
{
    "label": "data.user.active == true ? 'Active' : 'Inactive' |> upper"
}
```

### Switch (`@switch`)

```json
{
    "role_code": {
        "@switch": "data.user.role",
        "@cases": {
            "admin": "'A'",
            "manager": "'M'",
            "guest": "'G'"
        },
        "@default": "'U'"
    }
}
```

### Array Iteration (`@each`)

Use a key ending with `[]` and provide `@each` (the source array expression) and `@do` (the template for each item).

Inside `@do`, `node` refers to the current item.

```json
{
    "users[]": {
        "@each": "data.users |> filter(.active == true) |> sort(.name)",
        "@do": {
            "id": "node.id",
            "name": "node.name |> trim",
            "email": "node.email |> lower"
        }
    }
}
```

Nested iteration is supported. The inner `@each` rebinds `node` to the inner item:

```json
{
    "users[]": {
        "@each": "data.users",
        "@do": {
            "name": "node.name",
            "tags[]": {
                "@each": "node.tags |> filter(. != null)",
                "@do": {
                    "label": "node |> lower"
                }
            }
        }
    }
}
```

For GraphQL connection patterns, items shaped as `{node: {...}}` are automatically unwrapped so `node.id` resolves to `edge.node.id`.

### Comparisons

`==` and `!=` operators:

```json
{
    "is_admin": "data.user.role == 'admin'",
    "is_active": "data.user.status != 'disabled'"
}
```

## Custom Functions

Register custom pipe functions with `addFunction()`. The callable receives `(mixed $input, mixed ...$args)`:

```php
$transformer = new Transformer();

$transformer->addFunction('reverse', fn(mixed $input) => is_string($input) ? strrev($input) : $input);

$transformer->addFunction('wrap', fn(mixed $input, string $prefix, string $suffix) => $prefix . $input . $suffix);

$result = $transformer->transform($source, [
    'name' => 'data.user.name |> trim |> reverse',
    'id' => "data.user.id |> wrap('[', ']')",
]);
// ['name' => 'eoD nhoJ', 'id' => '[usr_123]']
```

Custom functions can also override built-in ones. Calls are chainable:

```php
$transformer
    ->addFunction('slug', fn(mixed $input) => strtolower(preg_replace('/\s+/', '-', trim($input))))
    ->addFunction('prefix', fn(mixed $input, string $p) => $p . $input);
```

## Template Files

Store schemas as `.tpl.json` or `.tpl.jsonc` files and apply them by name. The library searches the directory recursively, including all nested subdirectories.

```
templates/
  user.tpl.json
  api/
    order.tpl.jsonc
    nested/
      report.tpl.json
```

```php
$transformer = new Transformer();
$transformer->setPath(__DIR__ . '/templates');

$result = $transformer->apply('user.tpl', $source);    // finds templates/user.tpl.json
$result = $transformer->apply('order', $source);        // finds templates/api/order.tpl.jsonc
$result = $transformer->apply('report.tpl', $source);   // finds templates/api/nested/report.tpl.json
```

`apply()` also accepts an inline schema array directly:

```php
$result = $transformer->apply(['name' => 'data.user.name |> trim'], $source);
```

JSONC files support single-line (`//`) and multi-line (`/* */`) comments, plus trailing commas:

```jsonc
{
    // User mapping template
    "name": "data.user.name |> trim |> lower",
    /* ID field */
    "id": "data.user.id",
}
```

## Global Instance

Configure once and use anywhere without passing instances:

```php
// Bootstrap (once)
Transformer::getInstance()->setPath(__DIR__ . '/templates');
Transformer::getInstance()->addFunction('slug', fn($v) => strtolower(preg_replace('/\s+/', '-', trim($v))));

// Anywhere in your app
$user = Transformer::getInstance()->apply('user.tpl', $source);
$order = Transformer::getInstance()->apply('order.tpl', $source);
```

## Built-in Functions

| Function | Description | Example |
|----------|-------------|---------|
| `trim` | Trim whitespace | `data.name \|> trim` |
| `lower` | Lowercase | `data.name \|> lower` |
| `upper` | Uppercase | `data.code \|> upper` |
| `default(value)` | Fallback if null | `data.name \|> default('N/A')` |
| `date(format)` | Format date string | `data.created_at \|> date('Y-m-d')` |
| `count` | Count array items | `data.items \|> count` |
| `filter(expr)` | Filter array by expression | `data.items \|> filter(.active == true)` |
| `sort(expr)` | Sort array by expression | `data.items \|> sort(.name)` |
| `money(currency)` | Format as money | `data.price \|> money('USD')` |
| `now()` | Current ISO datetime | `now()` |

### Casting Functions

| Function | Alias | Description | Example |
|----------|-------|-------------|---------|
| `to_boolean` | `to_bool` | Cast to boolean | `data.flag \|> to_boolean` |
| `to_string` | — | Cast to string | `data.num \|> to_string` |
| `to_integer` | `to_int` | Cast to integer | `data.age \|> to_integer` |
| `to_float` | — | Cast to float | `data.score \|> to_float` |
| `to_array` | — | Cast to array | `data.val \|> to_array` |

`to_boolean` recognizes strings `'true'`, `'1'`, `'yes'`, `'on'` as `true`.

Casting works naturally with `default`:

```json
{
    "max_login": "data.user.max_login |> default('10') |> to_int"
}
```

## Full Example

```php
$source = [
    'data' => [
        'settings' => ['currency' => 'INR'],
        'user' => [
            'id' => 'usr_123',
            'name' => '  John Doe  ',
            'email' => '  JOHN@EXAMPLE.COM  ',
            'avatar' => null,
            'country' => 'IN',
            'active' => true,
            'role' => 'admin',
            'created_at' => '2024-06-15 10:30:00',
            'age' => '30',
            'score' => '4.8',
            'verified' => 'yes',
        ],
        'locations' => [
            'edges' => [
                ['node' => ['id' => 'loc_1', 'name' => 'Mumbai Office', 'status' => 'ACTIVE', 'price' => 1500.50]],
                ['node' => ['id' => 'loc_2', 'name' => 'Delhi Hub', 'status' => 'INACTIVE', 'price' => 2000.00]],
            ],
        ],
    ],
];

$schema = [
    '@vars' => [
        'currency' => "data.settings.currency |> default('USD')",
    ],
    '@macros' => [
        'money_format' => '. |> money(@vars.currency)',
    ],
    'user' => [
        'id' => 'data.user.id',
        'name' => 'data.user.name |> trim |> lower',
        'age' => 'data.user.age |> to_integer',
        'score' => 'data.user.score |> to_float',
        'verified' => 'data.user.verified |> to_boolean',
        'status' => "data.user.active == true ? 'enabled' : 'disabled'",
    ],
    'locations[]' => [
        '@each' => "data.locations.edges |> filter(.node.status == 'ACTIVE')",
        '@do' => [
            'id' => 'node.id',
            'name' => 'node.name',
            'price' => 'node.price |> @money_format',
        ],
    ],
    'stats' => [
        'total' => 'data.locations.edges |> count',
    ],
    'meta' => [
        'generated_at' => 'now()',
        'currency' => '@vars.currency',
    ],
];

$result = (new Transformer())->transform($source, $schema);
```

## Development

```bash
make install   # composer install
make test      # run tests
make clean     # remove vendor/ and composer.lock
```

## License

MIT
