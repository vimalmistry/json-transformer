<?php

declare(strict_types=1);

namespace Vimal\JsonTransformer\Parser;

use Vimal\JsonTransformer\Parser\Ast\ComparisonNode;
use Vimal\JsonTransformer\Parser\Ast\DotContextNode;
use Vimal\JsonTransformer\Parser\Ast\FunctionCallNode;
use Vimal\JsonTransformer\Parser\Ast\LiteralNode;
use Vimal\JsonTransformer\Parser\Ast\MacroCallNode;
use Vimal\JsonTransformer\Parser\Ast\Node;
use Vimal\JsonTransformer\Parser\Ast\PathNode;
use Vimal\JsonTransformer\Parser\Ast\PipeNode;
use Vimal\JsonTransformer\Parser\Ast\RawExpressionNode;
use Vimal\JsonTransformer\Parser\Ast\TernaryNode;
use Vimal\JsonTransformer\Parser\Ast\VarRefNode;

final class ExpressionParser
{
    private Lexer $lexer;

    public function __construct()
    {
        $this->lexer = new Lexer();
    }

    public function parse(string $expression): Node
    {
        $tokens = $this->lexer->tokenize($expression);
        $pos = 0;
        $node = $this->parseExpression($tokens, $pos);
        return $node;
    }

    /**
     * @param Token[] $tokens
     */
    private function parseExpression(array $tokens, int &$pos): Node
    {
        $left = $this->parsePrimary($tokens, $pos);

        // Check for comparison operator
        if ($this->peek($tokens, $pos)->type === Token::TYPE_COMPARISON) {
            $op = $tokens[$pos]->value;
            $pos++;
            $right = $this->parsePrimary($tokens, $pos);
            $left = new ComparisonNode($left, $op, $right);
        }

        // Check for ternary: condition ? ifTrue : ifFalse
        if ($this->peek($tokens, $pos)->type === Token::TYPE_QUESTION) {
            $pos++; // consume ?
            $ifTrue = $this->parsePrimary($tokens, $pos);
            if ($this->peek($tokens, $pos)->type === Token::TYPE_COLON) {
                $pos++; // consume :
            }
            $ifFalse = $this->parsePrimary($tokens, $pos);
            $left = new TernaryNode($left, $ifTrue, $ifFalse);
        }

        // Check for pipe chain
        if ($this->peek($tokens, $pos)->type === Token::TYPE_PIPE) {
            $steps = [];
            while ($this->peek($tokens, $pos)->type === Token::TYPE_PIPE) {
                $pos++; // consume |>
                $steps[] = $this->parsePipeStep($tokens, $pos);
            }
            return new PipeNode($left, $steps);
        }

        return $left;
    }

    /**
     * @param Token[] $tokens
     */
    private function parsePrimary(array $tokens, int &$pos): Node
    {
        $token = $this->peek($tokens, $pos);

        switch ($token->type) {
            case Token::TYPE_STRING:
                $pos++;
                return new LiteralNode($token->value);

            case Token::TYPE_NUMBER:
                $pos++;
                return new LiteralNode($token->value);

            case Token::TYPE_BOOL:
                $pos++;
                return new LiteralNode($token->value);

            case Token::TYPE_NULL:
                $pos++;
                return new LiteralNode(null);

            case Token::TYPE_DOT:
                $pos++;
                if ($token->value === ".") {
                    return new DotContextNode();
                }
                // Relative path like .node.status
                return new DotContextNode(substr($token->value, 1));

            case Token::TYPE_AT_REF:
                $pos++;
                $ref = $token->value; // e.g. @vars.currency or @normalize_name
                if (str_starts_with($ref, "@vars.")) {
                    return new VarRefNode(substr($ref, 6)); // strip @vars.
                }
                // Macro reference (used as standalone, not after pipe)
                return new MacroCallNode(substr($ref, 1)); // strip @

            case Token::TYPE_PATH:
                $pos++;
                $name = $token->value;

                // Check if it's a function call (followed by '(')
                if ($this->peek($tokens, $pos)->type === Token::TYPE_LPAREN) {
                    return $this->parseFunctionCall($name, $tokens, $pos);
                }

                return new PathNode($name);

            default:
                // Fallback: return a null literal for unrecognized tokens
                $pos++;
                return new LiteralNode(null);
        }
    }

    /**
     * @param Token[] $tokens
     */
    private function parsePipeStep(array $tokens, int &$pos): Node
    {
        $token = $this->peek($tokens, $pos);

        // Macro call after pipe: |> @normalize_name
        if ($token->type === Token::TYPE_AT_REF) {
            $pos++;
            $ref = $token->value;
            if (str_starts_with($ref, "@vars.")) {
                return new VarRefNode(substr($ref, 6));
            }
            return new MacroCallNode(substr($ref, 1));
        }

        // Function call or bare function name: |> trim or |> default('x')
        if ($token->type === Token::TYPE_PATH) {
            $pos++;
            $name = $token->value;

            if ($this->peek($tokens, $pos)->type === Token::TYPE_LPAREN) {
                return $this->parseFunctionCall($name, $tokens, $pos);
            }

            // Bare function name (no parens) e.g. trim, lower, upper, count
            return new FunctionCallNode($name);
        }

        // Fallback
        $pos++;
        return new FunctionCallNode((string) $token->value);
    }

    /**
     * @param Token[] $tokens
     */
    private function parseFunctionCall(
        string $name,
        array $tokens,
        int &$pos,
    ): FunctionCallNode {
        $pos++; // consume '('
        $args = [];

        // Special handling for filter/sort — the argument is a raw expression
        if ($name === "filter" || $name === "sort") {
            $rawExpr = $this->consumeBalancedParenContent($tokens, $pos);
            $args[] = new RawExpressionNode($rawExpr);
            return new FunctionCallNode($name, $args);
        }

        while (
            $this->peek($tokens, $pos)->type !== Token::TYPE_RPAREN &&
            $this->peek($tokens, $pos)->type !== Token::TYPE_EOF
        ) {
            $args[] = $this->parsePrimary($tokens, $pos);

            if ($this->peek($tokens, $pos)->type === Token::TYPE_COMMA) {
                $pos++;
            }
        }

        if ($this->peek($tokens, $pos)->type === Token::TYPE_RPAREN) {
            $pos++; // consume ')'
        }

        return new FunctionCallNode($name, $args);
    }

    /**
     * Consumes tokens until the matching closing paren, returning the raw expression string.
     *
     * @param Token[] $tokens
     */
    private function consumeBalancedParenContent(
        array $tokens,
        int &$pos,
    ): string {
        $depth = 0;
        $parts = [];

        while ($pos < count($tokens)) {
            $token = $tokens[$pos];

            if ($token->type === Token::TYPE_RPAREN && $depth === 0) {
                $pos++; // consume closing ')'
                break;
            }

            if ($token->type === Token::TYPE_LPAREN) {
                $depth++;
            } elseif ($token->type === Token::TYPE_RPAREN) {
                $depth--;
            }

            // Reconstruct the expression string from tokens
            if ($token->type === Token::TYPE_STRING) {
                $parts[] = "'" . $token->value . "'";
            } elseif ($token->type === Token::TYPE_BOOL) {
                $parts[] = $token->value ? "true" : "false";
            } elseif ($token->type === Token::TYPE_NULL) {
                $parts[] = "null";
            } elseif ($token->type === Token::TYPE_PIPE) {
                $parts[] = "|>";
            } elseif ($token->type === Token::TYPE_COMPARISON) {
                $parts[] = $token->value;
            } else {
                $parts[] = (string) $token->value;
            }

            $pos++;
        }

        return implode(" ", $parts);
    }

    /**
     * @param Token[] $tokens
     */
    private function peek(array $tokens, int $pos): Token
    {
        return $tokens[$pos] ?? new Token(Token::TYPE_EOF, null);
    }
}
