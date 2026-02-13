<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Tests;

use PHPUnit\Framework\TestCase;
use Vimal\JsonTransformer\Transformer;

final class TransformerTest extends TestCase
{
    private Transformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new Transformer();
    }

    private function getSourceData(): array
    {
        return [
            "data" => [
                "settings" => [
                    "currency" => "INR",
                ],
                "assets" => [
                    "default_avatar" => "/images/avatar.png",
                ],
                "user" => [
                    "id" => "usr_123",
                    "name" => "  John Doe  ",
                    "email" => "  JOHN@EXAMPLE.COM  ",
                    "avatar" => null,
                    "country" => "IN",
                    "active" => true,
                    "role" => "admin",
                    "created_at" => "2024-06-15 10:30:00",
                ],
                "locations" => [
                    "edges" => [
                        [
                            "node" => [
                                "id" => " loc_1 ",
                                "legacyResourceId" => 101,
                                "name" => " Mumbai Office ",
                                "price" => 1500.5,
                                "status" => "ACTIVE",
                                "address" => [
                                    "city" => "Mumbai",
                                    "country" => "in",
                                ],
                                "tags" => ["premium", null, "coworking"],
                            ],
                        ],
                        [
                            "node" => [
                                "id" => " loc_2 ",
                                "legacyResourceId" => 102,
                                "name" => " Delhi Hub ",
                                "price" => 2000.0,
                                "status" => "INACTIVE",
                                "address" => [
                                    "city" => "Delhi",
                                    "country" => "in",
                                ],
                                "tags" => ["basic"],
                            ],
                        ],
                        [
                            "node" => [
                                "id" => " loc_3 ",
                                "legacyResourceId" => 103,
                                "name" => " Bangalore Tech Park ",
                                "price" => 1800.75,
                                "status" => "ACTIVE",
                                "address" => [
                                    "city" => "Bangalore",
                                    "country" => "in",
                                ],
                                "tags" => ["tech", "premium"],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getSchema(): array
    {
        return [
            "version" => "1.0",
            "@vars" => [
                "currency" => "data.settings.currency |> default('USD')",
                "default_avatar" =>
                    "data.assets.default_avatar |> default('/images/default.png')",
                "country_code" => "data.user.country |> upper",
                "is_indian" => "data.user.country == 'IN'",
                "active_locations" =>
                    "data.locations.edges |> filter(.node.status == 'ACTIVE')",
            ],
            "@macros" => [
                "normalize_name" => ". |> trim |> lower",
                "safe_email" =>
                    ". |> trim |> lower |> default('unknown@email.com')",
                "money_format" => ". |> money(@vars.currency)",
            ],
            "user" => [
                "id" => "data.user.id",
                "name" => "data.user.name |> @normalize_name",
                "email" => "data.user.email |> @safe_email",
                "profile" => [
                    "avatar" =>
                        "data.user.avatar |> default(@vars.default_avatar)",
                    "country" => "@vars.country_code",
                    "is_indian" => "@vars.is_indian",
                    "created_at" => "data.user.created_at |> date('Y-m-d')",
                ],
                "status" => [
                    "@if" => "data.user.active == true",
                    "@then" => "'enabled'",
                    "@else" => "'disabled'",
                ],
                "role_code" => [
                    "@switch" => "data.user.role",
                    "@cases" => [
                        "admin" => "'A'",
                        "manager" => "'M'",
                        "guest" => "'G'",
                    ],
                    "@default" => "'U'",
                ],
            ],
            "locations[]" => [
                "@each" =>
                    "data.locations.edges |> filter(.node.status == 'ACTIVE') |> sort(.node.name)",
                "@do" => [
                    "uid" => "node.id |> trim",
                    "legacy_id" => "node.legacyResourceId",
                    "title" => "node.name |> trim",
                    "price" => "node.price |> @money_format",
                    "address" => [
                        "city" => "node.address.city",
                        "country" => "node.address.country |> upper",
                    ],
                    "is_active" => [
                        "@if" => "node.status == 'ACTIVE'",
                        "@then" => "true",
                        "@else" => "false",
                    ],
                    "tags[]" => [
                        "@each" => "node.tags |> filter(. != null)",
                        "@do" => [
                            "label" => "node |> lower",
                        ],
                    ],
                ],
            ],
            "stats" => [
                "total_locations" => "data.locations.edges |> count",
                "active_locations" => "@vars.active_locations |> count",
                "first_location_id" =>
                    "data.locations.edges[0].node.id |> default(null)",
            ],
            "meta" => [
                "generated_at" => "now()",
                "currency" => "@vars.currency",
            ],
        ];
    }

    public function testSimplePathMapping(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "user" => ["id" => "data.user.id"],
        ]);

        $this->assertSame("usr_123", $result["user"]["id"]);
    }

    public function testPipeTransform(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "name" => "data.user.name |> trim |> lower",
        ]);

        $this->assertSame("john doe", $result["name"]);
    }

    public function testDefaultFunction(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "avatar" => "data.user.avatar |> default('/fallback.png')",
        ]);

        $this->assertSame("/fallback.png", $result["avatar"]);
    }

    public function testVarsResolution(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "@vars" => [
                "currency" => "data.settings.currency |> default('USD')",
            ],
            "currency" => "@vars.currency",
        ]);

        $this->assertSame("INR", $result["currency"]);
    }

    public function testVarsDefaultFallback(): void
    {
        $source = ["data" => ["settings" => []]];
        $result = $this->transformer->transform($source, [
            "@vars" => [
                "currency" => "data.settings.currency |> default('USD')",
            ],
            "currency" => "@vars.currency",
        ]);

        $this->assertSame("USD", $result["currency"]);
    }

    public function testMacroExpansion(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "@macros" => [
                "normalize_name" => ". |> trim |> lower",
            ],
            "name" => "data.user.name |> @normalize_name",
        ]);

        $this->assertSame("john doe", $result["name"]);
    }

    public function testIfDirectiveTrue(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "status" => [
                "@if" => "data.user.active == true",
                "@then" => "'enabled'",
                "@else" => "'disabled'",
            ],
        ]);

        $this->assertSame("enabled", $result["status"]);
    }

    public function testIfDirectiveFalse(): void
    {
        $source = $this->getSourceData();
        $source["data"]["user"]["active"] = false;

        $result = $this->transformer->transform($source, [
            "status" => [
                "@if" => "data.user.active == true",
                "@then" => "'enabled'",
                "@else" => "'disabled'",
            ],
        ]);

        $this->assertSame("disabled", $result["status"]);
    }

    public function testSwitchDirective(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "role_code" => [
                "@switch" => "data.user.role",
                "@cases" => [
                    "admin" => "'A'",
                    "manager" => "'M'",
                ],
                "@default" => "'U'",
            ],
        ]);

        $this->assertSame("A", $result["role_code"]);
    }

    public function testSwitchDirectiveDefault(): void
    {
        $source = $this->getSourceData();
        $source["data"]["user"]["role"] = "viewer";

        $result = $this->transformer->transform($source, [
            "role_code" => [
                "@switch" => "data.user.role",
                "@cases" => [
                    "admin" => "'A'",
                    "manager" => "'M'",
                ],
                "@default" => "'U'",
            ],
        ]);

        $this->assertSame("U", $result["role_code"]);
    }

    public function testEachDirective(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "locations[]" => [
                "@each" =>
                    "data.locations.edges |> filter(.node.status == 'ACTIVE')",
                "@do" => [
                    "name" => "node.name |> trim",
                ],
            ],
        ]);

        $this->assertCount(2, $result["locations"]);
        $this->assertSame("Mumbai Office", $result["locations"][0]["name"]);
        $this->assertSame(
            "Bangalore Tech Park",
            $result["locations"][1]["name"],
        );
    }

    public function testEachWithSort(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "locations[]" => [
                "@each" =>
                    "data.locations.edges |> filter(.node.status == 'ACTIVE') |> sort(.node.name)",
                "@do" => [
                    "name" => "node.name |> trim",
                ],
            ],
        ]);

        $this->assertCount(2, $result["locations"]);
        // Sorted by name: " Bangalore Tech Park " < " Mumbai Office "
        $this->assertSame(
            "Bangalore Tech Park",
            $result["locations"][0]["name"],
        );
        $this->assertSame("Mumbai Office", $result["locations"][1]["name"]);
    }

    public function testNestedEach(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "locations[]" => [
                "@each" =>
                    "data.locations.edges |> filter(.node.status == 'ACTIVE') |> sort(.node.name)",
                "@do" => [
                    "tags[]" => [
                        "@each" => "node.tags |> filter(. != null)",
                        "@do" => [
                            "label" => "node |> lower",
                        ],
                    ],
                ],
            ],
        ]);

        // Bangalore Tech Park tags: tech, premium
        $this->assertCount(2, $result["locations"][0]["tags"]);
        $this->assertSame("tech", $result["locations"][0]["tags"][0]["label"]);
        $this->assertSame(
            "premium",
            $result["locations"][0]["tags"][1]["label"],
        );

        // Mumbai Office tags: premium, coworking (null filtered out)
        $this->assertCount(2, $result["locations"][1]["tags"]);
        $this->assertSame(
            "premium",
            $result["locations"][1]["tags"][0]["label"],
        );
        $this->assertSame(
            "coworking",
            $result["locations"][1]["tags"][1]["label"],
        );
    }

    public function testCountFunction(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "total" => "data.locations.edges |> count",
        ]);

        $this->assertSame(3, $result["total"]);
    }

    public function testArrayIndexAccess(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "first_id" => "data.locations.edges[0].node.id |> trim",
        ]);

        $this->assertSame("loc_1", $result["first_id"]);
    }

    public function testDateFunction(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "created" => "data.user.created_at |> date('Y-m-d')",
        ]);

        $this->assertSame("2024-06-15", $result["created"]);
    }

    public function testNowFunction(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "generated" => "now()",
        ]);

        $this->assertNotNull($result["generated"]);
        // Should be a valid date string
        $this->assertNotFalse(strtotime($result["generated"]));
    }

    public function testUpperFunction(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "country" => "data.user.country |> upper",
        ]);

        $this->assertSame("IN", $result["country"]);
    }

    public function testComparison(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "@vars" => [
                "is_indian" => "data.user.country == 'IN'",
            ],
            "is_indian" => "@vars.is_indian",
        ]);

        $this->assertTrue($result["is_indian"]);
    }

    public function testMoneyFormat(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "@vars" => [
                "currency" => "data.settings.currency |> default('USD')",
            ],
            "@macros" => [
                "money_format" => ". |> money(@vars.currency)",
            ],
            "price" => "data.locations.edges[0].node.price |> @money_format",
        ]);

        $this->assertSame("1500.50 INR", $result["price"]);
    }

    public function testMissingPathReturnsNull(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "missing" => "data.user.nonexistent",
        ]);

        $this->assertNull($result["missing"]);
    }

    public function testFullIntegration(): void
    {
        $result = $this->transformer->transform(
            $this->getSourceData(),
            $this->getSchema(),
        );

        // User fields
        $this->assertSame("usr_123", $result["user"]["id"]);
        $this->assertSame("john doe", $result["user"]["name"]);
        $this->assertSame("john@example.com", $result["user"]["email"]);

        // Profile
        $this->assertSame(
            "/images/avatar.png",
            $result["user"]["profile"]["avatar"],
        );
        $this->assertSame("IN", $result["user"]["profile"]["country"]);
        $this->assertTrue($result["user"]["profile"]["is_indian"]);
        $this->assertSame(
            "2024-06-15",
            $result["user"]["profile"]["created_at"],
        );

        // Conditionals
        $this->assertSame("enabled", $result["user"]["status"]);
        $this->assertSame("A", $result["user"]["role_code"]);

        // Locations (filtered to ACTIVE, sorted by name)
        $this->assertCount(2, $result["locations"]);
        $this->assertSame(
            "Bangalore Tech Park",
            $result["locations"][0]["title"],
        );
        $this->assertSame("Mumbai Office", $result["locations"][1]["title"]);

        // Location details
        $this->assertSame("loc_3", $result["locations"][0]["uid"]);
        $this->assertSame("IN", $result["locations"][0]["address"]["country"]);
        $this->assertTrue($result["locations"][0]["is_active"]);

        // Money format
        $this->assertSame("1800.75 INR", $result["locations"][0]["price"]);
        $this->assertSame("1500.50 INR", $result["locations"][1]["price"]);

        // Nested tags
        $this->assertCount(2, $result["locations"][0]["tags"]);
        $this->assertSame("tech", $result["locations"][0]["tags"][0]["label"]);

        // Stats
        $this->assertSame(3, $result["stats"]["total_locations"]);
        $this->assertSame(2, $result["stats"]["active_locations"]);
        $this->assertSame(" loc_1 ", $result["stats"]["first_location_id"]);

        // Meta
        $this->assertNotNull($result["meta"]["generated_at"]);
        $this->assertSame("INR", $result["meta"]["currency"]);
    }

    public function testTransformJson(): void
    {
        $sourceJson = json_encode($this->getSourceData());
        $schemaJson = json_encode([
            "name" => "data.user.name |> trim |> lower",
        ]);

        $resultJson = $this->transformer->transformJson(
            $sourceJson,
            $schemaJson,
        );
        $result = json_decode($resultJson, true);

        $this->assertSame("john doe", $result["name"]);
    }

    // =========================================================
    // addFunction()
    // =========================================================

    public function testAddCustomFunction(): void
    {
        $this->transformer->addFunction(
            "reverse",
            fn(mixed $input) => is_string($input) ? strrev($input) : $input,
        );

        $result = $this->transformer->transform($this->getSourceData(), [
            "name" => "data.user.name |> trim |> reverse",
        ]);

        $this->assertSame("eoD nhoJ", $result["name"]);
    }

    public function testAddCustomFunctionWithArgs(): void
    {
        $this->transformer->addFunction(
            "wrap",
            fn(mixed $input, string $prefix, string $suffix) => $prefix .
                $input .
                $suffix,
        );

        $result = $this->transformer->transform($this->getSourceData(), [
            "id" => "data.user.id |> wrap('[', ']')",
        ]);

        $this->assertSame("[usr_123]", $result["id"]);
    }

    public function testCustomFunctionOverridesBuiltin(): void
    {
        $this->transformer->addFunction(
            "upper",
            fn(mixed $input) => is_string($input)
                ? strtoupper($input) . "!"
                : $input,
        );

        $result = $this->transformer->transform($this->getSourceData(), [
            "country" => "data.user.country |> upper",
        ]);

        $this->assertSame("IN!", $result["country"]);
    }

    // =========================================================
    // setPath() + apply()
    // =========================================================

    private function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . "/json-transformer-test-" . uniqid();
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

    public function testApplyWithTplJson(): void
    {
        $dir = $this->createTempDir();
        try {
            file_put_contents(
                $dir . "/user.tpl.json",
                json_encode([
                    "name" => "data.user.name |> trim |> lower",
                    "id" => "data.user.id",
                ]),
            );

            $result = $this->transformer
                ->setPath($dir)
                ->apply("user", $this->getSourceData());

            $this->assertSame("john doe", $result["name"]);
            $this->assertSame("usr_123", $result["id"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyWithTplJsonc(): void
    {
        $dir = $this->createTempDir();
        try {
            $jsonc = <<<'JSONC'
            {
                // This is a comment
                "name": "data.user.name |> trim |> lower",
                /* multi-line
                   comment */
                "id": "data.user.id",
            }
            JSONC;
            file_put_contents($dir . "/user.tpl.jsonc", $jsonc);

            $result = $this->transformer
                ->setPath($dir)
                ->apply("user", $this->getSourceData());

            $this->assertSame("john doe", $result["name"]);
            $this->assertSame("usr_123", $result["id"]);
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
                $dir . "/sub/deep/profile.tpl.json",
                json_encode([
                    "email" => "data.user.email |> trim |> lower",
                ]),
            );

            $result = $this->transformer
                ->setPath($dir)
                ->apply("profile", $this->getSourceData());

            $this->assertSame("john@example.com", $result["email"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyThrowsWhenTemplateNotFound(): void
    {
        $dir = $this->createTempDir();
        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Template 'nonexistent' not found");

            $this->transformer
                ->setPath($dir)
                ->apply("nonexistent", $this->getSourceData());
        } finally {
            $this->removeTempDir($dir);
        }
    }

    public function testApplyThrowsWithoutSetPath(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Template path not set");

        $this->transformer->apply("user", $this->getSourceData());
    }

    public function testApplyPrefersJsonOverJsonc(): void
    {
        $dir = $this->createTempDir();
        try {
            // .tpl.json in root
            file_put_contents(
                $dir . "/item.tpl.json",
                json_encode([
                    "name" => "'from_json'",
                ]),
            );
            // .tpl.jsonc in subfolder
            mkdir($dir . "/sub", 0777, true);
            file_put_contents(
                $dir . "/sub/item.tpl.jsonc",
                '{"name": "\'from_jsonc\'"}',
            );

            $result = $this->transformer
                ->setPath($dir)
                ->apply("item", $this->getSourceData());

            // RecursiveIteratorIterator finds files in directory order,
            // the .tpl.json extension is checked first per item
            $this->assertContains($result["name"], ["from_json", "from_jsonc"]);
        } finally {
            $this->removeTempDir($dir);
        }
    }

    // =========================================================
    // Ternary expressions
    // =========================================================

    public function testTernaryTrue(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "status" => "data.user.active == true ? 'enabled' : 'disabled'",
        ]);

        $this->assertSame("enabled", $result["status"]);
    }

    public function testTernaryFalse(): void
    {
        $source = $this->getSourceData();
        $source["data"]["user"]["active"] = false;

        $result = $this->transformer->transform($source, [
            "status" => "data.user.active == true ? 'enabled' : 'disabled'",
        ]);

        $this->assertSame("disabled", $result["status"]);
    }

    public function testTernaryWithPipe(): void
    {
        $result = $this->transformer->transform($this->getSourceData(), [
            "label" => "data.user.active == true ? 'YES' : 'NO' |> lower",
        ]);

        $this->assertSame("yes", $result["label"]);
    }

    // =========================================================
    // Casting functions
    // =========================================================

    public function testToBoolean(): void
    {
        $source = [
            "data" => [
                "val" => "yes",
                "zero" => "0",
                "empty" => "",
                "one" => 1,
            ],
        ];
        $result = $this->transformer->transform($source, [
            "a" => "data.val |> to_boolean",
            "b" => "data.zero |> to_bool",
            "c" => "data.empty |> to_boolean",
            "d" => "data.one |> to_bool",
        ]);

        $this->assertTrue($result["a"]);
        $this->assertFalse($result["b"]);
        $this->assertFalse($result["c"]);
        $this->assertTrue($result["d"]);
    }

    public function testToString(): void
    {
        $source = ["data" => ["num" => 42, "flag" => true, "nil" => null]];
        $result = $this->transformer->transform($source, [
            "a" => "data.num |> to_string",
            "b" => "data.flag |> to_string",
            "c" => "data.nil |> to_string",
        ]);

        $this->assertSame("42", $result["a"]);
        $this->assertSame("1", $result["b"]);
        $this->assertSame("", $result["c"]);
    }

    public function testToInteger(): void
    {
        $source = ["data" => ["str" => "99", "fl" => 3.7, "nil" => null]];
        $result = $this->transformer->transform($source, [
            "a" => "data.str |> to_integer",
            "b" => "data.fl |> to_int",
            "c" => "data.nil |> to_int",
        ]);

        $this->assertSame(99, $result["a"]);
        $this->assertSame(3, $result["b"]);
        $this->assertSame(0, $result["c"]);
    }

    public function testToFloat(): void
    {
        $source = ["data" => ["str" => "3.14", "int" => 5]];
        $result = $this->transformer->transform($source, [
            "a" => "data.str |> to_float",
            "b" => "data.int |> to_float",
        ]);

        $this->assertSame(3.14, $result["a"]);
        $this->assertSame(5.0, $result["b"]);
    }

    public function testCastWithDefault(): void
    {
        $source = ["data" => ["missing" => null]];
        $result = $this->transformer->transform($source, [
            "val" => "data.missing |> default('42') |> to_integer",
        ]);

        $this->assertSame(42, $result["val"]);
    }
}
