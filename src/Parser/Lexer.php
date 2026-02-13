<?php

declare(strict_types=1);

namespace O360Main\JsonTransformer\Parser;

final class Lexer
{
    /**
     * @return Token[]
     */
    public function tokenize(string $expression): array
    {
        $expression = trim($expression);
        $tokens = [];
        $length = strlen($expression);
        $pos = 0;

        while ($pos < $length) {
            // Skip whitespace
            if (ctype_space($expression[$pos])) {
                $pos++;
                continue;
            }

            // Pipe operator |>
            if (
                $pos + 1 < $length &&
                $expression[$pos] === "|" &&
                $expression[$pos + 1] === ">"
            ) {
                $tokens[] = new Token(Token::TYPE_PIPE, "|>");
                $pos += 2;
                continue;
            }

            // Comparison operators == !=
            if (
                $pos + 1 < $length &&
                ($expression[$pos] === "=" || $expression[$pos] === "!") &&
                $expression[$pos + 1] === "="
            ) {
                $tokens[] = new Token(
                    Token::TYPE_COMPARISON,
                    $expression[$pos] . $expression[$pos + 1],
                );
                $pos += 2;
                continue;
            }

            // Parentheses
            if ($expression[$pos] === "(") {
                $tokens[] = new Token(Token::TYPE_LPAREN, "(");
                $pos++;
                continue;
            }
            if ($expression[$pos] === ")") {
                $tokens[] = new Token(Token::TYPE_RPAREN, ")");
                $pos++;
                continue;
            }

            // Comma
            if ($expression[$pos] === ",") {
                $tokens[] = new Token(Token::TYPE_COMMA, ",");
                $pos++;
                continue;
            }

            // Ternary ? and :
            if ($expression[$pos] === "?") {
                $tokens[] = new Token(Token::TYPE_QUESTION, "?");
                $pos++;
                continue;
            }
            if ($expression[$pos] === ":") {
                $tokens[] = new Token(Token::TYPE_COLON, ":");
                $pos++;
                continue;
            }

            // String literal (single-quoted)
            if ($expression[$pos] === "'") {
                $pos++;
                $start = $pos;
                while ($pos < $length && $expression[$pos] !== "'") {
                    if ($expression[$pos] === "\\" && $pos + 1 < $length) {
                        $pos += 2;
                    } else {
                        $pos++;
                    }
                }
                $value = substr($expression, $start, $pos - $start);
                $value = str_replace("\\'", "'", $value);
                $tokens[] = new Token(Token::TYPE_STRING, $value);
                $pos++; // skip closing quote
                continue;
            }

            // @ reference (@vars.something or @macro_name)
            if ($expression[$pos] === "@") {
                $pos++;
                $start = $pos;
                while (
                    $pos < $length &&
                    (ctype_alnum($expression[$pos]) ||
                        $expression[$pos] === "_" ||
                        $expression[$pos] === ".")
                ) {
                    $pos++;
                }
                $tokens[] = new Token(
                    Token::TYPE_AT_REF,
                    "@" . substr($expression, $start, $pos - $start),
                );
                continue;
            }

            // Dot (standalone or start of relative path like .node.status)
            if ($expression[$pos] === ".") {
                // Check if this is a dot followed by an identifier (relative path)
                if (
                    $pos + 1 < $length &&
                    (ctype_alpha($expression[$pos + 1]) ||
                        $expression[$pos + 1] === "_")
                ) {
                    $start = $pos;
                    $pos++; // skip the dot
                    while (
                        $pos < $length &&
                        (ctype_alnum($expression[$pos]) ||
                            $expression[$pos] === "_" ||
                            $expression[$pos] === "." ||
                            $expression[$pos] === "[")
                    ) {
                        if ($expression[$pos] === "[") {
                            // consume array index like [0]
                            while (
                                $pos < $length &&
                                $expression[$pos] !== "]"
                            ) {
                                $pos++;
                            }
                            if ($pos < $length) {
                                $pos++; // skip ]
                            }
                        } else {
                            $pos++;
                        }
                    }
                    $tokens[] = new Token(
                        Token::TYPE_DOT,
                        substr($expression, $start, $pos - $start),
                    );
                } else {
                    // Standalone dot
                    $tokens[] = new Token(Token::TYPE_DOT, ".");
                    $pos++;
                }
                continue;
            }

            // Number
            if (
                ctype_digit($expression[$pos]) ||
                ($expression[$pos] === "-" &&
                    $pos + 1 < $length &&
                    ctype_digit($expression[$pos + 1]))
            ) {
                $start = $pos;
                if ($expression[$pos] === "-") {
                    $pos++;
                }
                while (
                    $pos < $length &&
                    (ctype_digit($expression[$pos]) ||
                        $expression[$pos] === ".")
                ) {
                    $pos++;
                }
                $numStr = substr($expression, $start, $pos - $start);
                $tokens[] = new Token(
                    Token::TYPE_NUMBER,
                    str_contains($numStr, ".")
                        ? (float) $numStr
                        : (int) $numStr,
                );
                continue;
            }

            // Identifier (path, function name, boolean, null)
            if (ctype_alpha($expression[$pos]) || $expression[$pos] === "_") {
                $start = $pos;
                while (
                    $pos < $length &&
                    (ctype_alnum($expression[$pos]) ||
                        $expression[$pos] === "_" ||
                        $expression[$pos] === "." ||
                        $expression[$pos] === "[")
                ) {
                    if ($expression[$pos] === "[") {
                        // consume array index like [0]
                        while ($pos < $length && $expression[$pos] !== "]") {
                            $pos++;
                        }
                        if ($pos < $length) {
                            $pos++; // skip ]
                        }
                    } else {
                        $pos++;
                    }
                }
                $word = substr($expression, $start, $pos - $start);

                if ($word === "true" || $word === "false") {
                    $tokens[] = new Token(Token::TYPE_BOOL, $word === "true");
                } elseif ($word === "null") {
                    $tokens[] = new Token(Token::TYPE_NULL, null);
                } else {
                    // Check if it's a function call (followed by '(')
                    $peekPos = $pos;
                    while (
                        $peekPos < $length &&
                        ctype_space($expression[$peekPos])
                    ) {
                        $peekPos++;
                    }
                    if ($peekPos < $length && $expression[$peekPos] === "(") {
                        // It's a function name — but we still emit it as PATH;
                        // the parser will distinguish based on context (after pipe or as standalone function)
                        $tokens[] = new Token(Token::TYPE_PATH, $word);
                    } else {
                        $tokens[] = new Token(Token::TYPE_PATH, $word);
                    }
                }
                continue;
            }

            // Unknown character — skip
            $pos++;
        }

        $tokens[] = new Token(Token::TYPE_EOF, null);
        return $tokens;
    }
}
