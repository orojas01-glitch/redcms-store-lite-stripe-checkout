<?php

declare(strict_types=1);

/**
 * P3E-3 dependency-free JSON decoder with duplicate-key refusal.
 *
 * It accepts only bounded UTF-8 JSON, limits nesting and value count, and
 * rejects duplicate keys before returning one data-only PHP value.
 */
final class RED_CMS_Store_Lite_Stripe_Bounded_Json_Decoder
{
    private const MAX_BYTES = 262144;
    private const MAX_DEPTH = 16;
    private const MAX_VALUES = 4096;

    private int $position = 0;
    private int $values = 0;
    private int $length;

    private function __construct(private string $json)
    {
        $this->length = strlen($json);
    }

    public static function decode(string $json): array
    {
        $bytes = strlen($json);
        if ($bytes < 2
            || $bytes > self::MAX_BYTES
            || preg_match('//u', $json) !== 1
        ) {
            return self::invalid();
        }

        try {
            $decoder = new self($json);
            $value = $decoder->parseValue(0);
            $decoder->skipWhitespace();
            if ($decoder->position !== $decoder->length) {
                return self::invalid();
            }
            return [
                'valid' => true,
                'value' => $value,
                'errors' => [],
            ];
        } catch (Throwable $throwable) {
            return self::invalid();
        }
    }

    private function parseValue(int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH || ++$this->values > self::MAX_VALUES) {
            throw new RuntimeException('json_bound_exceeded');
        }
        $this->skipWhitespace();
        if ($this->position >= $this->length) {
            throw new RuntimeException('json_value_missing');
        }

        $character = $this->json[$this->position];
        return match ($character) {
            '{' => $this->parseObject($depth),
            '[' => $this->parseArray($depth),
            '"' => $this->parseString(),
            't' => $this->parseLiteral('true', true),
            'f' => $this->parseLiteral('false', false),
            'n' => $this->parseLiteral('null', null),
            default => $this->parseNumber(),
        };
    }

    private function parseObject(int $depth): array
    {
        $this->position++;
        $this->skipWhitespace();
        if ($this->consume('}')) {
            return [];
        }

        $result = [];
        while (true) {
            $this->skipWhitespace();
            if (($this->json[$this->position] ?? null) !== '"') {
                throw new RuntimeException('json_object_key_invalid');
            }
            $key = $this->parseString();
            if (array_key_exists($key, $result)) {
                throw new RuntimeException('json_duplicate_key');
            }
            $this->skipWhitespace();
            if (!$this->consume(':')) {
                throw new RuntimeException('json_object_colon_missing');
            }
            $result[$key] = $this->parseValue($depth + 1);
            $this->skipWhitespace();
            if ($this->consume('}')) {
                return $result;
            }
            if (!$this->consume(',')) {
                throw new RuntimeException('json_object_separator_invalid');
            }
        }
    }

    private function parseArray(int $depth): array
    {
        $this->position++;
        $this->skipWhitespace();
        if ($this->consume(']')) {
            return [];
        }

        $result = [];
        while (true) {
            $result[] = $this->parseValue($depth + 1);
            $this->skipWhitespace();
            if ($this->consume(']')) {
                return $result;
            }
            if (!$this->consume(',')) {
                throw new RuntimeException('json_array_separator_invalid');
            }
        }
    }

    private function parseString(): string
    {
        $start = $this->position;
        $this->position++;
        while ($this->position < $this->length) {
            $character = $this->json[$this->position];
            if ($character === '"') {
                $this->position++;
                $token = substr(
                    $this->json,
                    $start,
                    $this->position - $start
                );
                $decoded = json_decode(
                    $token,
                    true,
                    2,
                    JSON_THROW_ON_ERROR
                );
                if (!is_string($decoded)) {
                    throw new RuntimeException('json_string_invalid');
                }
                return $decoded;
            }
            if ($character === '\\') {
                $this->position++;
                $escape = $this->json[$this->position] ?? null;
                if (!is_string($escape)
                    || !str_contains('"\\/bfnrtu', $escape)
                ) {
                    throw new RuntimeException('json_escape_invalid');
                }
                if ($escape === 'u') {
                    $hex = substr($this->json, $this->position + 1, 4);
                    if (strlen($hex) !== 4
                        || preg_match('/\A[0-9A-Fa-f]{4}\z/D', $hex) !== 1
                    ) {
                        throw new RuntimeException('json_unicode_invalid');
                    }
                    $this->position += 4;
                }
            } elseif (ord($character) < 32) {
                throw new RuntimeException('json_control_invalid');
            }
            $this->position++;
        }
        throw new RuntimeException('json_string_unterminated');
    }

    private function parseLiteral(string $token, mixed $value): mixed
    {
        if (substr($this->json, $this->position, strlen($token)) !== $token) {
            throw new RuntimeException('json_literal_invalid');
        }
        $this->position += strlen($token);
        return $value;
    }

    private function parseNumber(): int|float
    {
        $remaining = substr($this->json, $this->position);
        if (preg_match(
            '/\A-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/',
            $remaining,
            $matches
        ) !== 1) {
            throw new RuntimeException('json_number_invalid');
        }
        $token = $matches[0];
        $decoded = json_decode($token, true, 2, JSON_THROW_ON_ERROR);
        if ((!is_int($decoded) && !is_float($decoded))
            || (is_float($decoded) && !is_finite($decoded))
        ) {
            throw new RuntimeException('json_number_invalid');
        }
        $this->position += strlen($token);
        return $decoded;
    }

    private function skipWhitespace(): void
    {
        while ($this->position < $this->length
            && str_contains(" \t\r\n", $this->json[$this->position])
        ) {
            $this->position++;
        }
    }

    private function consume(string $character): bool
    {
        if (($this->json[$this->position] ?? null) !== $character) {
            return false;
        }
        $this->position++;
        return true;
    }

    private static function invalid(): array
    {
        return [
            'valid' => false,
            'value' => null,
            'errors' => ['json_invalid'],
        ];
    }
}
