<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Tests;

use PHPUnit\Framework\TestCase;
use O360Main\JsonTransformer\Transformer;

/**
 * Comprehensive feature demo covering every json-transformer capability.
 */
final class FeatureDemoTest extends TestCase
{
    private Transformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new Transformer();
    }

    // =========================================================
    // 1. PATH RESOLUTION
    // =========================================================

    public function testSimplePath(): void
    {
        $source = ["user" => ["name" => "Alice"]];
        $result = $this->transformer->transform($source, [
            "name" => "user.name",
        ]);

        $this->assertSame("Alice", $result["name"]);
    }

    public function testDeepNestedPath(): void
    {
        $source = ["a" => ["b" => ["c" => ["d" => "deep"]]]];
        $result = $this->transformer->transform($source, [
            "val" => "a.b.c.d",
        ]);

        $this->assertSame("deep", $result["val"]);
    }

    public function testArrayIndexAccess(): void
    {
        $source = [
            "items" => [
                ["id" => "first"],
                ["id" => "second"],
                ["id" => "third"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "first" => "items[0].id",
            "second" => "items[1].id",
            "third" => "items[2].id",
        ]);

        $this->assertSame("first", $result["first"]);
        $this->assertSame("second", $result["second"]);
        $this->assertSame("third", $result["third"]);
    }

    public function testMissingPathReturnsNull(): void
    {
        $source = ["user" => ["name" => "Alice"]];
        $result = $this->transformer->transform($source, [
            "missing" => "user.email",
            "deep_missing" => "user.address.city.zip",
        ]);

        $this->assertNull($result["missing"]);
        $this->assertNull($result["deep_missing"]);
    }

    // =========================================================
    // 2. SCALAR PASS-THROUGH
    // =========================================================

    public function testScalarPassThrough(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "version" => "1.0",
                "count" => 42,
                "enabled" => true,
                "disabled" => false,
                "nothing" => null,
                "pi" => 3.14,
            ],
        );

        // "version" is a string so it's evaluated as an expression — but "1.0"
        // won't match any path, resolves to null. Use literal syntax instead.
        $this->assertSame(42, $result["count"]);
        $this->assertTrue($result["enabled"]);
        $this->assertFalse($result["disabled"]);
        $this->assertNull($result["nothing"]);
        $this->assertSame(3.14, $result["pi"]);
    }

    // =========================================================
    // 3. LITERAL VALUES
    // =========================================================

    public function testStringLiteral(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "greeting" => "'hello world'",
                "empty" => "''",
            ],
        );

        $this->assertSame("hello world", $result["greeting"]);
        $this->assertSame("", $result["empty"]);
    }

    public function testNumericLiteral(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "int" => "42",
                "negative" => "-5",
                "float" => "3.14",
            ],
        );

        $this->assertSame(42, $result["int"]);
        $this->assertSame(-5, $result["negative"]);
        $this->assertSame(3.14, $result["float"]);
    }

    public function testBooleanLiteral(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "yes" => "true",
                "no" => "false",
            ],
        );

        $this->assertTrue($result["yes"]);
        $this->assertFalse($result["no"]);
    }

    public function testNullLiteral(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "val" => "null",
            ],
        );

        $this->assertNull($result["val"]);
    }

    // =========================================================
    // 4. PIPE CHAINS
    // =========================================================

    public function testSinglePipe(): void
    {
        $source = ["name" => "  ALICE  "];
        $result = $this->transformer->transform($source, [
            "name" => "name |> trim",
        ]);

        $this->assertSame("ALICE", $result["name"]);
    }

    public function testMultiplePipes(): void
    {
        $source = ["name" => "  ALICE DOE  "];
        $result = $this->transformer->transform($source, [
            "name" => "name |> trim |> lower",
        ]);

        $this->assertSame("alice doe", $result["name"]);
    }

    public function testPipeWithArguments(): void
    {
        $source = ["val" => null];
        $result = $this->transformer->transform($source, [
            "val" => "val |> default('fallback')",
        ]);

        $this->assertSame("fallback", $result["val"]);
    }

    // =========================================================
    // 5. STRING FUNCTIONS: trim, lower, upper
    // =========================================================

    public function testTrim(): void
    {
        $source = ["s" => "  hello  "];
        $result = $this->transformer->transform($source, [
            "val" => "s |> trim",
        ]);

        $this->assertSame("hello", $result["val"]);
    }

    public function testLower(): void
    {
        $source = ["s" => "HELLO"];
        $result = $this->transformer->transform($source, [
            "val" => "s |> lower",
        ]);

        $this->assertSame("hello", $result["val"]);
    }

    public function testUpper(): void
    {
        $source = ["s" => "hello"];
        $result = $this->transformer->transform($source, [
            "val" => "s |> upper",
        ]);

        $this->assertSame("HELLO", $result["val"]);
    }

    public function testStringFunctionWithNonStringInput(): void
    {
        $source = ["num" => 42];
        $result = $this->transformer->transform($source, [
            "val" => "num |> trim",
        ]);

        // Non-string input returned unchanged
        $this->assertSame(42, $result["val"]);
    }

    // =========================================================
    // 6. DEFAULT FUNCTION
    // =========================================================

    public function testDefaultWithNull(): void
    {
        $source = ["val" => null];
        $result = $this->transformer->transform($source, [
            "val" => "val |> default('N/A')",
        ]);

        $this->assertSame("N/A", $result["val"]);
    }

    public function testDefaultWithExistingValue(): void
    {
        $source = ["val" => "exists"];
        $result = $this->transformer->transform($source, [
            "val" => "val |> default('N/A')",
        ]);

        $this->assertSame("exists", $result["val"]);
    }

    public function testDefaultWithMissingPath(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "val" => "missing.path |> default('fallback')",
            ],
        );

        $this->assertSame("fallback", $result["val"]);
    }

    public function testDefaultWithNullFallback(): void
    {
        $source = ["val" => null];
        $result = $this->transformer->transform($source, [
            "val" => "val |> default(null)",
        ]);

        $this->assertNull($result["val"]);
    }

    public function testDefaultWithVarRef(): void
    {
        $source = ["avatar" => null];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "default_avatar" => "'/images/default.png'",
            ],
            "avatar" => "avatar |> default(@vars.default_avatar)",
        ]);

        $this->assertSame("/images/default.png", $result["avatar"]);
    }

    // =========================================================
    // 7. DATE FUNCTION
    // =========================================================

    public function testDateFormatString(): void
    {
        $source = ["dt" => "2024-06-15 10:30:00"];
        $result = $this->transformer->transform($source, [
            "date" => "dt |> date('Y-m-d')",
            "full" => "dt |> date('d/m/Y H:i')",
        ]);

        $this->assertSame("2024-06-15", $result["date"]);
        $this->assertSame("15/06/2024 10:30", $result["full"]);
    }

    public function testDateFromTimestamp(): void
    {
        $source = ["ts" => 1718451000]; // 2024-06-15 roughly
        $result = $this->transformer->transform($source, [
            "date" => "ts |> date('Y')",
        ]);

        $this->assertSame("2024", $result["date"]);
    }

    public function testDateWithNullReturnsNull(): void
    {
        $source = ["dt" => null];
        $result = $this->transformer->transform($source, [
            "date" => "dt |> date('Y-m-d')",
        ]);

        $this->assertNull($result["date"]);
    }

    // =========================================================
    // 8. NOW FUNCTION
    // =========================================================

    public function testNowStandalone(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "generated_at" => "now()",
            ],
        );

        $this->assertNotNull($result["generated_at"]);
        $this->assertNotFalse(strtotime($result["generated_at"]));
    }

    // =========================================================
    // 9. COUNT FUNCTION
    // =========================================================

    public function testCount(): void
    {
        $source = ["items" => [1, 2, 3, 4, 5]];
        $result = $this->transformer->transform($source, [
            "total" => "items |> count",
        ]);

        $this->assertSame(5, $result["total"]);
    }

    public function testCountNonCountableReturnsZero(): void
    {
        $source = ["val" => "not an array"];
        $result = $this->transformer->transform($source, [
            "total" => "val |> count",
        ]);

        $this->assertSame(0, $result["total"]);
    }

    // =========================================================
    // 10. MONEY FUNCTION
    // =========================================================

    public function testMoney(): void
    {
        $source = ["price" => 1500.5];
        $result = $this->transformer->transform($source, [
            "formatted" => "price |> money('USD')",
        ]);

        $this->assertSame("1500.50 USD", $result["formatted"]);
    }

    public function testMoneyDefaultCurrency(): void
    {
        $source = ["price" => 99];
        $result = $this->transformer->transform($source, [
            "formatted" => "price |> money",
        ]);

        $this->assertSame("99.00 USD", $result["formatted"]);
    }

    public function testMoneyNullInput(): void
    {
        $source = ["price" => null];
        $result = $this->transformer->transform($source, [
            "formatted" => "price |> money('EUR')",
        ]);

        $this->assertSame("0.00 EUR", $result["formatted"]);
    }

    // =========================================================
    // 11. TYPE CASTING FUNCTIONS
    // =========================================================

    public function testToBoolean(): void
    {
        $source = [
            "yes_str" => "yes",
            "true_str" => "true",
            "one_str" => "1",
            "on_str" => "on",
            "zero_str" => "0",
            "empty_str" => "",
            "no_str" => "no",
            "int_one" => 1,
            "int_zero" => 0,
        ];
        $result = $this->transformer->transform($source, [
            "a" => "yes_str |> to_boolean",
            "b" => "true_str |> to_bool",
            "c" => "one_str |> to_boolean",
            "d" => "on_str |> to_bool",
            "e" => "zero_str |> to_boolean",
            "f" => "empty_str |> to_bool",
            "g" => "no_str |> to_boolean",
            "h" => "int_one |> to_bool",
            "i" => "int_zero |> to_boolean",
        ]);

        $this->assertTrue($result["a"]);
        $this->assertTrue($result["b"]);
        $this->assertTrue($result["c"]);
        $this->assertTrue($result["d"]);
        $this->assertFalse($result["e"]);
        $this->assertFalse($result["f"]);
        $this->assertFalse($result["g"]);
        $this->assertTrue($result["h"]);
        $this->assertFalse($result["i"]);
    }

    public function testToString(): void
    {
        $source = ["num" => 42, "flag" => true, "nil" => null];
        $result = $this->transformer->transform($source, [
            "a" => "num |> to_string",
            "b" => "flag |> to_string",
            "c" => "nil |> to_string",
        ]);

        $this->assertSame("42", $result["a"]);
        $this->assertSame("1", $result["b"]);
        $this->assertSame("", $result["c"]);
    }

    public function testToInteger(): void
    {
        $source = ["str" => "99", "fl" => 3.7, "nil" => null];
        $result = $this->transformer->transform($source, [
            "a" => "str |> to_integer",
            "b" => "fl |> to_int",
            "c" => "nil |> to_int",
        ]);

        $this->assertSame(99, $result["a"]);
        $this->assertSame(3, $result["b"]);
        $this->assertSame(0, $result["c"]);
    }

    public function testToFloat(): void
    {
        $source = ["str" => "3.14", "int" => 5];
        $result = $this->transformer->transform($source, [
            "a" => "str |> to_float",
            "b" => "int |> to_float",
        ]);

        $this->assertSame(3.14, $result["a"]);
        $this->assertSame(5.0, $result["b"]);
    }

    public function testToArray(): void
    {
        $source = ["val" => "hello"];
        $result = $this->transformer->transform($source, [
            "arr" => "val |> to_array",
        ]);

        $this->assertSame(["hello"], $result["arr"]);
    }

    public function testCastChain(): void
    {
        $source = ["val" => null];
        $result = $this->transformer->transform($source, [
            "val" => "val |> default('42') |> to_integer",
        ]);

        $this->assertSame(42, $result["val"]);
    }

    // =========================================================
    // 12. COMPARISONS
    // =========================================================

    public function testEqualityTrue(): void
    {
        $source = ["country" => "IN"];
        $result = $this->transformer->transform($source, [
            "is_indian" => "country == 'IN'",
        ]);

        $this->assertTrue($result["is_indian"]);
    }

    public function testEqualityFalse(): void
    {
        $source = ["country" => "US"];
        $result = $this->transformer->transform($source, [
            "is_indian" => "country == 'IN'",
        ]);

        $this->assertFalse($result["is_indian"]);
    }

    public function testNotEqual(): void
    {
        $source = ["status" => "active"];
        $result = $this->transformer->transform($source, [
            "not_disabled" => "status != 'disabled'",
        ]);

        $this->assertTrue($result["not_disabled"]);
    }

    // =========================================================
    // 13. TERNARY
    // =========================================================

    public function testTernaryTrue(): void
    {
        $source = ["active" => true];
        $result = $this->transformer->transform($source, [
            "status" => "active == true ? 'enabled' : 'disabled'",
        ]);

        $this->assertSame("enabled", $result["status"]);
    }

    public function testTernaryFalse(): void
    {
        $source = ["active" => false];
        $result = $this->transformer->transform($source, [
            "status" => "active == true ? 'enabled' : 'disabled'",
        ]);

        $this->assertSame("disabled", $result["status"]);
    }

    public function testTernaryWithPipe(): void
    {
        $source = ["active" => true];
        $result = $this->transformer->transform($source, [
            "label" => "active == true ? 'YES' : 'NO' |> lower",
        ]);

        $this->assertSame("yes", $result["label"]);
    }

    // =========================================================
    // 14. CONCATENATION
    // =========================================================

    public function testConcat(): void
    {
        $source = ["first" => "John", "last" => "Doe"];
        $result = $this->transformer->transform($source, [
            "full" => "first + ' ' + last",
        ]);

        $this->assertSame("John Doe", $result["full"]);
    }

    public function testConcatWithNull(): void
    {
        $source = ["first" => "John", "last" => null];
        $result = $this->transformer->transform($source, [
            "full" => "first + ' ' + last",
        ]);

        // null becomes empty string
        $this->assertSame("John ", $result["full"]);
    }

    // =========================================================
    // 15. VARIABLES (@vars / $shorthand)
    // =========================================================

    public function testVarsBasic(): void
    {
        $source = ["settings" => ["currency" => "INR"]];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "currency" => "settings.currency |> default('USD')",
            ],
            "currency" => "@vars.currency",
        ]);

        $this->assertSame("INR", $result["currency"]);
    }

    public function testVarsDefaultFallback(): void
    {
        $source = ["settings" => []];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "currency" => "settings.currency |> default('USD')",
            ],
            "currency" => "@vars.currency",
        ]);

        $this->assertSame("USD", $result["currency"]);
    }

    public function testVarsShorthandSyntax(): void
    {
        $source = ["settings" => ["currency" => "EUR"]];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "currency" => "settings.currency",
            ],
            "currency" => "\$currency",
        ]);

        $this->assertSame("EUR", $result["currency"]);
    }

    public function testVarsSequentialResolution(): void
    {
        $source = ["base" => "hello"];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "step1" => "base |> upper",
                "step2" => "@vars.step1",
            ],
            "val" => "@vars.step2",
        ]);

        $this->assertSame("HELLO", $result["val"]);
    }

    public function testVarsWithComparison(): void
    {
        $source = ["country" => "IN"];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "is_indian" => "country == 'IN'",
            ],
            "is_indian" => "@vars.is_indian",
        ]);

        $this->assertTrue($result["is_indian"]);
    }

    public function testVarsWithFilteredArray(): void
    {
        $source = [
            "items" => [
                ["status" => "active", "name" => "A"],
                ["status" => "inactive", "name" => "B"],
                ["status" => "active", "name" => "C"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "active_items" => "items |> filter(.status == 'active')",
            ],
            "active_count" => "@vars.active_items |> count",
        ]);

        $this->assertSame(2, $result["active_count"]);
    }

    // =========================================================
    // 16. MACROS
    // =========================================================

    public function testMacroBasic(): void
    {
        $source = ["name" => "  ALICE DOE  "];
        $result = $this->transformer->transform($source, [
            "@macros" => [
                "normalize" => ". |> trim |> lower",
            ],
            "name" => "name |> @normalize",
        ]);

        $this->assertSame("alice doe", $result["name"]);
    }

    public function testMacroWithVarRef(): void
    {
        $source = ["price" => 1500.5, "settings" => ["currency" => "INR"]];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "currency" => "settings.currency",
            ],
            "@macros" => [
                "money_format" => ". |> money(@vars.currency)",
            ],
            "price" => "price |> @money_format",
        ]);

        $this->assertSame("1500.50 INR", $result["price"]);
    }

    public function testMacroChainedWithPipe(): void
    {
        $source = ["email" => "  ADMIN@EXAMPLE.COM  "];
        $result = $this->transformer->transform($source, [
            "@macros" => [
                "safe_email" =>
                    ". |> trim |> lower |> default('unknown@email.com')",
            ],
            "email" => "email |> @safe_email",
        ]);

        $this->assertSame("admin@example.com", $result["email"]);
    }

    public function testMacroWithNullInput(): void
    {
        $source = ["email" => null];
        $result = $this->transformer->transform($source, [
            "@macros" => [
                "safe_email" =>
                    ". |> trim |> lower |> default('unknown@email.com')",
            ],
            "email" => "email |> @safe_email",
        ]);

        $this->assertSame("unknown@email.com", $result["email"]);
    }

    // =========================================================
    // 17. NESTED OBJECTS
    // =========================================================

    public function testNestedObjectSchema(): void
    {
        $source = [
            "user" => [
                "name" => "Alice",
                "address" => ["city" => "Melbourne", "country" => "au"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "profile" => [
                "name" => "user.name",
                "location" => [
                    "city" => "user.address.city",
                    "country" => "user.address.country |> upper",
                ],
            ],
        ]);

        $this->assertSame("Alice", $result["profile"]["name"]);
        $this->assertSame("Melbourne", $result["profile"]["location"]["city"]);
        $this->assertSame("AU", $result["profile"]["location"]["country"]);
    }

    // =========================================================
    // 18. @if / @then / @else DIRECTIVE
    // =========================================================

    public function testIfDirectiveTrue(): void
    {
        $source = ["user" => ["active" => true]];
        $result = $this->transformer->transform($source, [
            "status" => [
                "@if" => "user.active == true",
                "@then" => "'enabled'",
                "@else" => "'disabled'",
            ],
        ]);

        $this->assertSame("enabled", $result["status"]);
    }

    public function testIfDirectiveFalse(): void
    {
        $source = ["user" => ["active" => false]];
        $result = $this->transformer->transform($source, [
            "status" => [
                "@if" => "user.active == true",
                "@then" => "'enabled'",
                "@else" => "'disabled'",
            ],
        ]);

        $this->assertSame("disabled", $result["status"]);
    }

    public function testIfDirectiveWithPathExpression(): void
    {
        $source = ["user" => ["active" => true, "name" => "Alice"]];
        $result = $this->transformer->transform($source, [
            "greeting" => [
                "@if" => "user.active == true",
                "@then" => "user.name",
                "@else" => "'Guest'",
            ],
        ]);

        $this->assertSame("Alice", $result["greeting"]);
    }

    public function testIfDirectiveMissingElse(): void
    {
        $source = ["active" => false];
        $result = $this->transformer->transform($source, [
            "status" => [
                "@if" => "active == true",
                "@then" => "'enabled'",
            ],
        ]);

        $this->assertNull($result["status"]);
    }

    // =========================================================
    // 19. @switch / @cases / @default DIRECTIVE
    // =========================================================

    public function testSwitchDirectiveMatch(): void
    {
        $source = ["role" => "admin"];
        $result = $this->transformer->transform($source, [
            "code" => [
                "@switch" => "role",
                "@cases" => [
                    "admin" => "'A'",
                    "manager" => "'M'",
                    "guest" => "'G'",
                ],
                "@default" => "'U'",
            ],
        ]);

        $this->assertSame("A", $result["code"]);
    }

    public function testSwitchDirectiveDefault(): void
    {
        $source = ["role" => "viewer"];
        $result = $this->transformer->transform($source, [
            "code" => [
                "@switch" => "role",
                "@cases" => [
                    "admin" => "'A'",
                    "manager" => "'M'",
                ],
                "@default" => "'U'",
            ],
        ]);

        $this->assertSame("U", $result["code"]);
    }

    public function testSwitchDirectiveNoMatchNoDefault(): void
    {
        $source = ["role" => "viewer"];
        $result = $this->transformer->transform($source, [
            "code" => [
                "@switch" => "role",
                "@cases" => [
                    "admin" => "'A'",
                ],
            ],
        ]);

        $this->assertNull($result["code"]);
    }

    // =========================================================
    // 20. @each / @do WITH this. PREFIX (GraphQL edges)
    // =========================================================

    public function testEachWithGraphQLEdges(): void
    {
        $source = [
            "data" => [
                "products" => [
                    "edges" => [
                        ["node" => ["id" => "p1", "title" => " Widget "]],
                        ["node" => ["id" => "p2", "title" => " Gadget "]],
                    ],
                ],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "products[]" => [
                "@each" => "data.products.edges",
                "@do" => [
                    "id" => "this.id",
                    "title" => "this.title |> trim",
                ],
            ],
        ]);

        $this->assertCount(2, $result["products"]);
        $this->assertSame("p1", $result["products"][0]["id"]);
        $this->assertSame("Widget", $result["products"][0]["title"]);
        $this->assertSame("p2", $result["products"][1]["id"]);
        $this->assertSame("Gadget", $result["products"][1]["title"]);
    }

    // =========================================================
    // 21. @each / @do WITH BARE PATHS (flat arrays)
    // =========================================================

    public function testEachWithBarePaths(): void
    {
        $source = [
            "stores" => [
                [
                    "uid" => "001",
                    "name" => "  Store A  ",
                    "is_active" => true,
                    "address" => ["city" => "Sydney", "country_code" => "AU"],
                    "_data" => ["id" => "gid://shopify/Location/001"],
                    "sync_id" => ["value" => "001"],
                ],
                [
                    "uid" => "002",
                    "name" => "  Store B  ",
                    "is_active" => false,
                    "address" => ["city" => "Toronto", "country_code" => "CA"],
                    "_data" => ["id" => "gid://shopify/Location/002"],
                    "sync_id" => ["value" => "002"],
                ],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "locations[]" => [
                "@each" => "stores",
                "@do" => [
                    "id" => "_data.id",
                    "input" => [
                        "name" => "name |> trim",
                        "address" => [
                            "city" => "address.city",
                            "countryCode" => "address.country_code",
                        ],
                    ],
                    "_meta" => [
                        "uid" => "uid",
                        "sync_id" => "sync_id.value",
                        "is_active" => "is_active",
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $result["locations"]);

        $loc1 = $result["locations"][0];
        $this->assertSame("gid://shopify/Location/001", $loc1["id"]);
        $this->assertSame("Store A", $loc1["input"]["name"]);
        $this->assertSame("Sydney", $loc1["input"]["address"]["city"]);
        $this->assertSame("AU", $loc1["input"]["address"]["countryCode"]);
        $this->assertSame("001", $loc1["_meta"]["uid"]);
        $this->assertSame("001", $loc1["_meta"]["sync_id"]);
        $this->assertTrue($loc1["_meta"]["is_active"]);

        $loc2 = $result["locations"][1];
        $this->assertSame("gid://shopify/Location/002", $loc2["id"]);
        $this->assertSame("Store B", $loc2["input"]["name"]);
        $this->assertSame("Toronto", $loc2["input"]["address"]["city"]);
        $this->assertFalse($loc2["_meta"]["is_active"]);
    }

    // =========================================================
    // 22. FILTER
    // =========================================================

    public function testFilter(): void
    {
        $source = [
            "items" => [
                ["name" => "A", "status" => "active"],
                ["name" => "B", "status" => "inactive"],
                ["name" => "C", "status" => "active"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "active[]" => [
                "@each" => "items |> filter(.status == 'active')",
                "@do" => [
                    "name" => "name",
                ],
            ],
        ]);

        $this->assertCount(2, $result["active"]);
        $this->assertSame("A", $result["active"][0]["name"]);
        $this->assertSame("C", $result["active"][1]["name"]);
    }

    public function testFilterNullValues(): void
    {
        $source = ["tags" => ["premium", null, "basic", null, "pro"]];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "clean_tags" => "tags |> filter(. != null)",
            ],
            "count" => "@vars.clean_tags |> count",
        ]);

        $this->assertSame(3, $result["count"]);
    }

    // =========================================================
    // 23. SORT
    // =========================================================

    public function testSort(): void
    {
        $source = [
            "items" => [
                ["name" => "Charlie"],
                ["name" => "Alice"],
                ["name" => "Bob"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "sorted[]" => [
                "@each" => "items |> sort(.name)",
                "@do" => [
                    "name" => "name",
                ],
            ],
        ]);

        $this->assertCount(3, $result["sorted"]);
        $this->assertSame("Alice", $result["sorted"][0]["name"]);
        $this->assertSame("Bob", $result["sorted"][1]["name"]);
        $this->assertSame("Charlie", $result["sorted"][2]["name"]);
    }

    public function testFilterThenSort(): void
    {
        $source = [
            "items" => [
                ["name" => "Charlie", "active" => true],
                ["name" => "Alice", "active" => false],
                ["name" => "Bob", "active" => true],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "sorted[]" => [
                "@each" => "items |> filter(.active == true) |> sort(.name)",
                "@do" => [
                    "name" => "name",
                ],
            ],
        ]);

        $this->assertCount(2, $result["sorted"]);
        $this->assertSame("Bob", $result["sorted"][0]["name"]);
        $this->assertSame("Charlie", $result["sorted"][1]["name"]);
    }

    // =========================================================
    // 24. NESTED @each
    // =========================================================

    public function testNestedEach(): void
    {
        $source = [
            "departments" => [
                [
                    "name" => "Engineering",
                    "members" => [["name" => "Alice"], ["name" => "Bob"]],
                ],
                [
                    "name" => "Marketing",
                    "members" => [["name" => "Charlie"]],
                ],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "departments[]" => [
                "@each" => "departments",
                "@do" => [
                    "dept" => "name",
                    "people[]" => [
                        "@each" => "members",
                        "@do" => [
                            "person_name" => "name |> upper",
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $result["departments"]);

        $eng = $result["departments"][0];
        $this->assertSame("Engineering", $eng["dept"]);
        $this->assertCount(2, $eng["people"]);
        $this->assertSame("ALICE", $eng["people"][0]["person_name"]);
        $this->assertSame("BOB", $eng["people"][1]["person_name"]);

        $mkt = $result["departments"][1];
        $this->assertSame("Marketing", $mkt["dept"]);
        $this->assertCount(1, $mkt["people"]);
        $this->assertSame("CHARLIE", $mkt["people"][0]["person_name"]);
    }

    // =========================================================
    // 25. NESTED @each WITH SCALAR ITEMS
    // =========================================================

    public function testNestedEachWithScalarItems(): void
    {
        $source = [
            "data" => [
                "edges" => [
                    [
                        "node" => [
                            "name" => "Item 1",
                            "tags" => ["premium", null, "coworking"],
                        ],
                    ],
                ],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "items[]" => [
                "@each" => "data.edges",
                "@do" => [
                    "name" => "this.name",
                    "tags[]" => [
                        "@each" => "this.tags |> filter(. != null)",
                        "@do" => [
                            "label" => "this |> lower",
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertCount(1, $result["items"]);
        $this->assertSame("Item 1", $result["items"][0]["name"]);
        $this->assertCount(2, $result["items"][0]["tags"]);
        $this->assertSame("premium", $result["items"][0]["tags"][0]["label"]);
        $this->assertSame("coworking", $result["items"][0]["tags"][1]["label"]);
    }

    // =========================================================
    // 26. @if INSIDE @each / @do
    // =========================================================

    public function testIfInsideEachDo(): void
    {
        $source = [
            "items" => [
                ["name" => "A", "status" => "ACTIVE"],
                ["name" => "B", "status" => "INACTIVE"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "items[]" => [
                "@each" => "items",
                "@do" => [
                    "name" => "name",
                    "is_active" => [
                        "@if" => "status == 'ACTIVE'",
                        "@then" => "true",
                        "@else" => "false",
                    ],
                ],
            ],
        ]);

        $this->assertCount(2, $result["items"]);
        $this->assertTrue($result["items"][0]["is_active"]);
        $this->assertFalse($result["items"][1]["is_active"]);
    }

    // =========================================================
    // 27. CUSTOM FUNCTIONS (addFunction)
    // =========================================================

    public function testAddCustomFunction(): void
    {
        $this->transformer->addFunction(
            "reverse",
            fn(mixed $input) => is_string($input) ? strrev($input) : $input,
        );

        $source = ["name" => "hello"];
        $result = $this->transformer->transform($source, [
            "val" => "name |> reverse",
        ]);

        $this->assertSame("olleh", $result["val"]);
    }

    public function testCustomFunctionWithArgs(): void
    {
        $this->transformer->addFunction(
            "wrap",
            fn(mixed $input, string $prefix, string $suffix) => $prefix .
                $input .
                $suffix,
        );

        $source = ["id" => "123"];
        $result = $this->transformer->transform($source, [
            "id" => "id |> wrap('[', ']')",
        ]);

        $this->assertSame("[123]", $result["id"]);
    }

    public function testCustomFunctionOverridesBuiltin(): void
    {
        $this->transformer->addFunction(
            "upper",
            fn(mixed $input) => is_string($input)
                ? strtoupper($input) . "!"
                : $input,
        );

        $source = ["name" => "hello"];
        $result = $this->transformer->transform($source, [
            "val" => "name |> upper",
        ]);

        $this->assertSame("HELLO!", $result["val"]);
    }

    // =========================================================
    // 28. transformJson (JSON string in/out)
    // =========================================================

    public function testTransformJson(): void
    {
        $sourceJson = json_encode(["user" => ["name" => "  Alice  "]]);
        $schemaJson = json_encode(["name" => "user.name |> trim"]);

        $resultJson = $this->transformer->transformJson(
            $sourceJson,
            $schemaJson,
        );
        $result = json_decode($resultJson, true);

        $this->assertSame("Alice", $result["name"]);
    }

    // =========================================================
    // 29. applyString (single expression evaluation)
    // =========================================================

    public function testApplyString(): void
    {
        $source = ["name" => "  HELLO WORLD  "];
        $result = $this->transformer->applyString(
            "name |> trim |> lower",
            $source,
        );

        $this->assertSame("hello world", $result);
    }

    // =========================================================
    // 30. FILE-BASED TEMPLATES (setPath + apply)
    // =========================================================

    public function testApplyWithTemplateFile(): void
    {
        $dir = $this->createTempDir();
        try {
            file_put_contents(
                $dir . "/user.tpl.json",
                json_encode([
                    "name" => "user.name |> trim |> lower",
                    "id" => "user.id",
                ]),
            );

            $source = ["user" => ["name" => "  ALICE  ", "id" => "u1"]];
            $result = $this->transformer->setPath($dir)->apply("user", $source);

            $this->assertSame("alice", $result["name"]);
            $this->assertSame("u1", $result["id"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyWithJsoncTemplate(): void
    {
        $dir = $this->createTempDir();
        try {
            $jsonc = <<<'JSONC'
            {
                // This is a single-line comment
                "name": "user.name |> trim",
                /* Multi-line
                   comment */
                "id": "user.id",
            }
            JSONC;
            file_put_contents($dir . "/profile.tpl.jsonc", $jsonc);

            $source = ["user" => ["name" => "  Bob  ", "id" => "u2"]];
            $result = $this->transformer
                ->setPath($dir)
                ->apply("profile", $source);

            $this->assertSame("Bob", $result["name"]);
            $this->assertSame("u2", $result["id"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyFindsNestedTemplate(): void
    {
        $dir = $this->createTempDir();
        try {
            mkdir($dir . "/sub/deep", 0777, true);
            file_put_contents(
                $dir . "/sub/deep/order.tpl.json",
                json_encode(["total" => "order.total"]),
            );

            $source = ["order" => ["total" => 99.99]];
            $result = $this->transformer
                ->setPath($dir)
                ->apply("order", $source);

            $this->assertSame(99.99, $result["total"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyThrowsWithoutSetPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Template path not set");
        $this->transformer->apply("test", []);
    }

    public function testApplyThrowsWhenTemplateNotFound(): void
    {
        $dir = $this->createTempDir();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Template 'nonexistent' not found");
            $this->transformer->setPath($dir)->apply("nonexistent", []);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    // =========================================================
    // 31. DOT CONTEXT (. and .subpath)
    // =========================================================

    public function testDotContextInMacro(): void
    {
        $source = ["name" => "  HELLO  "];
        $result = $this->transformer->transform($source, [
            "@macros" => [
                "clean" => ". |> trim |> lower",
            ],
            "name" => "name |> @clean",
        ]);

        $this->assertSame("hello", $result["name"]);
    }

    public function testDotSubpathInFilter(): void
    {
        $source = [
            "items" => [
                ["meta" => ["active" => true], "name" => "A"],
                ["meta" => ["active" => false], "name" => "B"],
                ["meta" => ["active" => true], "name" => "C"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "active_count" => "items |> filter(.meta.active == true) |> count",
        ]);

        $this->assertSame(2, $result["active_count"]);
    }

    // =========================================================
    // 32. FULL INTEGRATION — REALISTIC E-COMMERCE SCENARIO
    // =========================================================

    public function testFullIntegrationEcommerce(): void
    {
        $source = [
            "shop" => [
                "currency" => "AUD",
                "default_image" => "/img/placeholder.png",
            ],
            "stores" => [
                [
                    "sync_id" => ["key" => "id", "value" => "71549911083"],
                    "uid" => "71549911083",
                    "name" => "  Ferryden Park  ",
                    "code" => "FP001",
                    "is_active" => true,
                    "address" => [
                        "address_1" => "Perth St",
                        "address_2" => "",
                        "city" => "Adelaide",
                        "state" => "South Australia",
                        "zip" => "5010",
                        "country_code" => "AU",
                        "phone" => "",
                    ],
                    "_data" => [
                        "cursor" => "abc123",
                        "id" => "gid://shopify/Location/71549911083",
                    ],
                ],
                [
                    "sync_id" => ["key" => "id", "value" => "70472728619"],
                    "uid" => "70472728619",
                    "name" => "  Toronto Hub  ",
                    "code" => "TH001",
                    "is_active" => true,
                    "address" => [
                        "address_1" => "123 Main St",
                        "address_2" => null,
                        "city" => "Toronto",
                        "state" => "Ontario",
                        "zip" => "A1A 1A1",
                        "country_code" => "CA",
                        "phone" => "555-5555",
                    ],
                    "_data" => [
                        "cursor" => "def456",
                        "id" => "gid://shopify/Location/70472728619",
                    ],
                ],
                [
                    "sync_id" => ["key" => "id", "value" => "70472695851"],
                    "uid" => "70472695851",
                    "name" => "  Closed Store  ",
                    "code" => "CS001",
                    "is_active" => false,
                    "address" => [
                        "address_1" => null,
                        "address_2" => null,
                        "city" => null,
                        "state" => null,
                        "zip" => null,
                        "country_code" => "AU",
                        "phone" => null,
                    ],
                    "_data" => [
                        "cursor" => "ghi789",
                        "id" => "gid://shopify/Location/70472695851",
                    ],
                ],
            ],
        ];

        $schema = [
            "@vars" => [
                "currency" => "shop.currency |> default('USD')",
                "total_stores" => "stores |> count",
                "active_stores" => "stores |> filter(.is_active == true)",
            ],
            "@macros" => [
                "clean_name" => ". |> trim",
                "safe_phone" => ". |> default('')",
            ],
            "summary" => [
                "total" => "@vars.total_stores",
                "active" => "@vars.active_stores |> count",
                "currency" => "@vars.currency",
                "generated_at" => "now()",
            ],
            "locations[]" => [
                "@each" =>
                    "stores |> filter(.is_active == true) |> sort(.name)",
                "@do" => [
                    "id" => "_data.id",
                    "input" => [
                        "name" => "name |> @clean_name",
                        "address" => [
                            "address1" => "address.address_1",
                            "address2" => "address.address_2",
                            "city" => "address.city",
                            "province" => "address.state",
                            "zip" => "address.zip",
                            "countryCode" => "address.country_code",
                            "phone" => "address.phone |> @safe_phone",
                        ],
                    ],
                    "_meta" => [
                        "uid" => "uid",
                        "sync_id" => "sync_id.value",
                        "is_active" => "is_active",
                        "status" => [
                            "@if" => "is_active == true",
                            "@then" => "'enabled'",
                            "@else" => "'disabled'",
                        ],
                        "region" => [
                            "@switch" => "address.country_code",
                            "@cases" => [
                                "AU" => "'APAC'",
                                "CA" => "'AMER'",
                                "GB" => "'EMEA'",
                            ],
                            "@default" => "'OTHER'",
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->transformer->transform($source, $schema);

        // Summary
        $this->assertSame(3, $result["summary"]["total"]);
        $this->assertSame(2, $result["summary"]["active"]);
        $this->assertSame("AUD", $result["summary"]["currency"]);
        $this->assertNotNull($result["summary"]["generated_at"]);

        // Locations: filtered to active (2), sorted by name
        $this->assertCount(2, $result["locations"]);

        // First: "  Ferryden Park  " < "  Toronto Hub  " (sorted by trimmed? no, sorted by raw)
        // Space-prefixed: "  Ferryden Park  " sorts before "  Toronto Hub  "
        $loc1 = $result["locations"][0];
        $this->assertSame("gid://shopify/Location/71549911083", $loc1["id"]);
        $this->assertSame("Ferryden Park", $loc1["input"]["name"]);
        $this->assertSame("Perth St", $loc1["input"]["address"]["address1"]);
        $this->assertSame("", $loc1["input"]["address"]["address2"]);
        $this->assertSame("Adelaide", $loc1["input"]["address"]["city"]);
        $this->assertSame(
            "South Australia",
            $loc1["input"]["address"]["province"],
        );
        $this->assertSame("5010", $loc1["input"]["address"]["zip"]);
        $this->assertSame("AU", $loc1["input"]["address"]["countryCode"]);
        $this->assertSame("", $loc1["input"]["address"]["phone"]);
        $this->assertSame("71549911083", $loc1["_meta"]["uid"]);
        $this->assertSame("71549911083", $loc1["_meta"]["sync_id"]);
        $this->assertTrue($loc1["_meta"]["is_active"]);
        $this->assertSame("enabled", $loc1["_meta"]["status"]);
        $this->assertSame("APAC", $loc1["_meta"]["region"]);

        $loc2 = $result["locations"][1];
        $this->assertSame("gid://shopify/Location/70472728619", $loc2["id"]);
        $this->assertSame("Toronto Hub", $loc2["input"]["name"]);
        $this->assertSame("123 Main St", $loc2["input"]["address"]["address1"]);
        $this->assertNull($loc2["input"]["address"]["address2"]);
        $this->assertSame("Toronto", $loc2["input"]["address"]["city"]);
        $this->assertSame("Ontario", $loc2["input"]["address"]["province"]);
        $this->assertSame("A1A 1A1", $loc2["input"]["address"]["zip"]);
        $this->assertSame("CA", $loc2["input"]["address"]["countryCode"]);
        $this->assertSame("555-5555", $loc2["input"]["address"]["phone"]);
        $this->assertSame("70472728619", $loc2["_meta"]["uid"]);
        $this->assertTrue($loc2["_meta"]["is_active"]);
        $this->assertSame("enabled", $loc2["_meta"]["status"]);
        $this->assertSame("AMER", $loc2["_meta"]["region"]);
    }

    // =========================================================
    // 33. OPTIONAL FIELDS — ? SUFFIX (omit null)
    // =========================================================

    public function testOptionalFieldOmittedWhenNull(): void
    {
        $source = ["name" => "Alice", "email" => null];
        $result = $this->transformer->transform($source, [
            "name" => "name",
            "email?" => "email",
        ]);

        $this->assertSame("Alice", $result["name"]);
        $this->assertArrayNotHasKey("email", $result);
    }

    public function testOptionalFieldKeptWhenNotNull(): void
    {
        $source = ["name" => "Alice", "email" => "alice@example.com"];
        $result = $this->transformer->transform($source, [
            "name" => "name",
            "email?" => "email",
        ]);

        $this->assertSame("Alice", $result["name"]);
        $this->assertSame("alice@example.com", $result["email"]);
    }

    public function testOptionalFieldKeptWhenEmptyString(): void
    {
        $source = ["phone" => ""];
        $result = $this->transformer->transform($source, [
            "phone?" => "phone",
        ]);

        // Empty string is not null — field is kept
        $this->assertArrayHasKey("phone", $result);
        $this->assertSame("", $result["phone"]);
    }

    public function testOptionalFieldKeptWhenFalse(): void
    {
        $source = ["active" => false];
        $result = $this->transformer->transform($source, [
            "active?" => "active",
        ]);

        // false is not null — field is kept
        $this->assertArrayHasKey("active", $result);
        $this->assertFalse($result["active"]);
    }

    public function testOptionalFieldKeptWhenZero(): void
    {
        $source = ["count" => 0];
        $result = $this->transformer->transform($source, [
            "count?" => "count",
        ]);

        $this->assertArrayHasKey("count", $result);
        $this->assertSame(0, $result["count"]);
    }

    public function testOptionalFieldWithDefault(): void
    {
        $source = ["val" => null];
        $result = $this->transformer->transform($source, [
            "val?" => "val |> default('fallback')",
        ]);

        // default() makes it non-null, so field is kept
        $this->assertArrayHasKey("val", $result);
        $this->assertSame("fallback", $result["val"]);
    }

    public function testOptionalFieldWithMissingPath(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "exists" => "'hello'",
                "missing?" => "no.such.path",
            ],
        );

        $this->assertSame("hello", $result["exists"]);
        $this->assertArrayNotHasKey("missing", $result);
    }

    public function testOptionalFieldInsideEachDo(): void
    {
        $source = [
            "items" => [
                ["name" => "A", "note" => "important"],
                ["name" => "B", "note" => null],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "items[]" => [
                "@each" => "items",
                "@do" => [
                    "name" => "name",
                    "note?" => "note",
                ],
            ],
        ]);

        $this->assertCount(2, $result["items"]);
        $this->assertArrayHasKey("note", $result["items"][0]);
        $this->assertSame("important", $result["items"][0]["note"]);
        $this->assertArrayNotHasKey("note", $result["items"][1]);
    }

    public function testOptionalFieldWithIfDirective(): void
    {
        $source = ["role" => "viewer"];
        $result = $this->transformer->transform($source, [
            "role" => "role",
            "admin_note?" => [
                "@if" => "role == 'admin'",
                "@then" => "'Has admin access'",
            ],
        ]);

        $this->assertSame("viewer", $result["role"]);
        // @if returns null when condition is false and no @else — field omitted
        $this->assertArrayNotHasKey("admin_note", $result);
    }

    public function testOptionalArrayOmittedWhenEmpty(): void
    {
        $source = [
            "items" => [
                ["name" => "A", "status" => "inactive"],
                ["name" => "B", "status" => "inactive"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "active[]?" => [
                "@each" => "items |> filter(.status == 'active')",
                "@do" => [
                    "name" => "name",
                ],
            ],
        ]);

        // All filtered out — empty array — field omitted
        $this->assertArrayNotHasKey("active", $result);
    }

    public function testOptionalArrayKeptWhenNotEmpty(): void
    {
        $source = [
            "items" => [
                ["name" => "A", "status" => "active"],
                ["name" => "B", "status" => "inactive"],
            ],
        ];
        $result = $this->transformer->transform($source, [
            "active[]?" => [
                "@each" => "items |> filter(.status == 'active')",
                "@do" => [
                    "name" => "name",
                ],
            ],
        ]);

        $this->assertArrayHasKey("active", $result);
        $this->assertCount(1, $result["active"]);
        $this->assertSame("A", $result["active"][0]["name"]);
    }

    public function testOptionalArrayOmittedWhenSourceMissing(): void
    {
        $result = $this->transformer->transform(
            [],
            [
                "items[]?" => [
                    "@each" => "no.such.path",
                    "@do" => [
                        "name" => "name",
                    ],
                ],
            ],
        );

        // Source path doesn't exist — @each returns [] — field omitted
        $this->assertArrayNotHasKey("items", $result);
    }

    public function testOptionalFieldWithSwitchDirective(): void
    {
        $source = ["status" => "unknown"];
        $result = $this->transformer->transform($source, [
            "label?" => [
                "@switch" => "status",
                "@cases" => [
                    "active" => "'Active'",
                    "inactive" => "'Inactive'",
                ],
            ],
        ]);

        // No match and no @default — returns null — field omitted
        $this->assertArrayNotHasKey("label", $result);
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . "/json-transformer-demo-" . uniqid();
        mkdir($dir, 0777, true);
        return $dir;
    }

    private function removeTempDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir()
                ? rmdir($file->getPathname())
                : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
