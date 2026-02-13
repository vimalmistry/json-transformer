<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser;

final class Token
{
    public const TYPE_PATH = "PATH";
    public const TYPE_PIPE = "PIPE";
    public const TYPE_LPAREN = "LPAREN";
    public const TYPE_RPAREN = "RPAREN";
    public const TYPE_STRING = "STRING";
    public const TYPE_NUMBER = "NUMBER";
    public const TYPE_BOOL = "BOOL";
    public const TYPE_NULL = "NULL";
    public const TYPE_DOT = "DOT";
    public const TYPE_COMPARISON = "COMPARISON";
    public const TYPE_COMMA = "COMMA";
    public const TYPE_QUESTION = "QUESTION";
    public const TYPE_COLON = "COLON";
    public const TYPE_AT_REF = "AT_REF";
    public const TYPE_CONCAT = "CONCAT";
    public const TYPE_EOF = "EOF";

    public function __construct(
        public readonly string $type,
        public readonly mixed $value,
    ) {}
}
