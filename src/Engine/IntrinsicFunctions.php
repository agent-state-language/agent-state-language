<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

use AgentStateLanguage\Exceptions\ASLException;

/**
 * Intrinsic functions for ASL.
 */
class IntrinsicFunctions
{
    /**
     * Evaluate an intrinsic function.
     *
     * @param string $expression The function expression (e.g., "States.Format('{}', $.name)")
     * @param array<string, mixed> $data The current state data
     * @param array<string, mixed>|null $context The execution context
     * @return mixed The evaluated result
     */
    public static function evaluate(
        string $expression,
        array $data,
        ?array $context = null
    ): mixed {
        // Parse function name and arguments
        if (!preg_match('/^States\.(\w+)\((.*)\)$/s', $expression, $matches)) {
            throw new ASLException(
                "Invalid intrinsic function: '$expression'",
                'States.IntrinsicFailure'
            );
        }

        $functionName = $matches[1];
        $argsString = $matches[2];
        $args = self::parseArguments($argsString, $data, $context);

        return match ($functionName) {
            'Format' => self::format($args),
            'StringToJson' => self::stringToJson($args),
            'JsonToString' => self::jsonToString($args),
            'Array' => self::array($args),
            'ArrayPartition' => self::arrayPartition($args),
            'ArrayContains' => self::arrayContains($args),
            'ArrayConcat' => self::arrayConcat($args),
            'ArrayGetItem' => self::arrayGetItem($args),
            'ArrayLength' => self::arrayLength($args),
            'ArrayRange' => self::arrayRange($args),
            'ArrayUnique' => self::arrayUnique($args),
            'UUID' => self::uuid(),
            'Hash' => self::hash($args),
            'Base64Encode' => self::base64Encode($args),
            'Base64Decode' => self::base64Decode($args),
            'MathRandom' => self::mathRandom(),
            'MathAdd' => self::mathAdd($args),
            'MathSubtract' => self::mathSubtract($args),
            'MathMultiply' => self::mathMultiply($args),
            'TokenCount' => self::tokenCount($args),
            'Truncate' => self::truncate($args),
            'Merge' => self::merge($args),
            'Pick' => self::pick($args),
            'Omit' => self::omit($args),
            'CurrentCost' => self::currentCost($context),
            'CurrentTokens' => self::currentTokens($context),
            default => throw new ASLException(
                "Unknown intrinsic function: States.{$functionName}",
                'States.IntrinsicFailure'
            ),
        };
    }

    /**
     * Parse function arguments.
     *
     * @param string $argsString
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $context
     * @return array<mixed>
     */
    private static function parseArguments(
        string $argsString,
        array $data,
        ?array $context
    ): array {
        if (trim($argsString) === '') {
            return [];
        }

        $args = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($argsString); $i++) {
            $char = $argsString[$i];

            if (!$inString) {
                if ($char === '"' || $char === "'") {
                    $inString = true;
                    $stringChar = $char;
                    $current .= $char;
                } elseif ($char === '(' || $char === '[') {
                    $depth++;
                    $current .= $char;
                } elseif ($char === ')' || $char === ']') {
                    $depth--;
                    $current .= $char;
                } elseif ($char === ',' && $depth === 0) {
                    $args[] = self::resolveArgument(trim($current), $data, $context);
                    $current = '';
                } else {
                    $current .= $char;
                }
            } else {
                if ($char === $stringChar && ($i === 0 || $argsString[$i - 1] !== '\\')) {
                    $inString = false;
                }
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $args[] = self::resolveArgument(trim($current), $data, $context);
        }

        return $args;
    }

    /**
     * Resolve a single argument.
     *
     * @param string $arg
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $context
     * @return mixed
     */
    private static function resolveArgument(
        string $arg,
        array $data,
        ?array $context
    ): mixed {
        // String literal
        if (preg_match('/^["\'](.*)["\']/s', $arg, $matches)) {
            return $matches[1];
        }

        // Number
        if (is_numeric($arg)) {
            return str_contains($arg, '.') ? (float) $arg : (int) $arg;
        }

        // Boolean
        if ($arg === 'true') {
            return true;
        }
        if ($arg === 'false') {
            return false;
        }

        // Null
        if ($arg === 'null') {
            return null;
        }

        // JSONPath reference
        if (str_starts_with($arg, '$')) {
            return JsonPath::evaluate($arg, $data, $context);
        }

        // Nested function
        if (str_starts_with($arg, 'States.')) {
            return self::evaluate($arg, $data, $context);
        }

        return $arg;
    }

    /**
     * States.Format - Format a string with placeholders.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function format(array $args): string
    {
        if (count($args) < 1) {
            throw new ASLException(
                'States.Format requires at least 1 argument',
                'States.IntrinsicFailure'
            );
        }

        $template = (string) array_shift($args);
        $result = $template;

        foreach ($args as $arg) {
            $result = preg_replace('/\{\}/', (string) $arg, $result, 1);
        }

        return $result;
    }

    /**
     * States.StringToJson - Parse JSON string.
     *
     * @param array<mixed> $args
     * @return mixed
     */
    private static function stringToJson(array $args): mixed
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.StringToJson requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        $decoded = json_decode((string) $args[0], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ASLException(
                'States.StringToJson: Invalid JSON string',
                'States.IntrinsicFailure'
            );
        }

        return $decoded;
    }

    /**
     * States.JsonToString - Serialize to JSON.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function jsonToString(array $args): string
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.JsonToString requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        return json_encode($args[0]) ?: '';
    }

    /**
     * States.Array - Create array from arguments.
     *
     * @param array<mixed> $args
     * @return array<mixed>
     */
    private static function array(array $args): array
    {
        return $args;
    }

    /**
     * States.ArrayPartition - Split array into chunks.
     *
     * @param array<mixed> $args
     * @return array<array<mixed>>
     */
    private static function arrayPartition(array $args): array
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.ArrayPartition requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<mixed> $array */
        $array = $args[0];
        $chunkSize = (int) $args[1];

        return array_chunk($array, $chunkSize);
    }

    /**
     * States.ArrayContains - Check if array contains value.
     *
     * @param array<mixed> $args
     * @return bool
     */
    private static function arrayContains(array $args): bool
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.ArrayContains requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<mixed> $array */
        $array = $args[0];
        $value = $args[1];

        return in_array($value, $array, true);
    }

    /**
     * States.ArrayGetItem - Get item at index.
     *
     * @param array<mixed> $args
     * @return mixed
     */
    private static function arrayGetItem(array $args): mixed
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.ArrayGetItem requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<mixed> $array */
        $array = $args[0];
        $index = (int) $args[1];

        if ($index < 0) {
            $index = count($array) + $index;
        }

        return $array[$index] ?? null;
    }

    /**
     * States.ArrayLength - Get array length.
     *
     * @param array<mixed> $args
     * @return int
     */
    private static function arrayLength(array $args): int
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.ArrayLength requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<mixed> $array */
        $array = $args[0];

        return count($array);
    }

    /**
     * States.ArrayRange - Create range array.
     *
     * @param array<mixed> $args
     * @return array<int>
     */
    private static function arrayRange(array $args): array
    {
        if (count($args) < 2 || count($args) > 3) {
            throw new ASLException(
                'States.ArrayRange requires 2 or 3 arguments',
                'States.IntrinsicFailure'
            );
        }

        $start = (int) $args[0];
        $end = (int) $args[1];
        $step = isset($args[2]) ? (int) $args[2] : 1;

        return range($start, $end - 1, $step);
    }

    /**
     * States.ArrayUnique - Remove duplicates.
     *
     * @param array<mixed> $args
     * @return array<mixed>
     */
    private static function arrayUnique(array $args): array
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.ArrayUnique requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<mixed> $array */
        $array = $args[0];

        return array_values(array_unique($array, SORT_REGULAR));
    }

    /**
     * States.UUID - Generate UUID.
     *
     * @return string
     */
    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * States.Hash - Calculate hash.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function hash(array $args): string
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.Hash requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        $data = (string) $args[0];
        $algorithm = strtolower(str_replace('-', '', (string) $args[1]));

        $algo = match ($algorithm) {
            'md5' => 'md5',
            'sha1' => 'sha1',
            'sha256' => 'sha256',
            'sha384' => 'sha384',
            'sha512' => 'sha512',
            default => throw new ASLException(
                "Unknown hash algorithm: {$args[1]}",
                'States.IntrinsicFailure'
            ),
        };

        return hash($algo, $data);
    }

    /**
     * States.Base64Encode - Encode to base64.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function base64Encode(array $args): string
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.Base64Encode requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        return base64_encode((string) $args[0]);
    }

    /**
     * States.Base64Decode - Decode from base64.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function base64Decode(array $args): string
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.Base64Decode requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        $decoded = base64_decode((string) $args[0], true);

        if ($decoded === false) {
            throw new ASLException(
                'States.Base64Decode: Invalid base64 string',
                'States.IntrinsicFailure'
            );
        }

        return $decoded;
    }

    /**
     * States.MathRandom - Generate random number.
     *
     * @return float
     */
    private static function mathRandom(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    /**
     * States.MathAdd - Add two numbers.
     *
     * @param array<mixed> $args
     * @return float|int
     */
    private static function mathAdd(array $args): float|int
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.MathAdd requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        $a = is_numeric($args[0]) ? $args[0] : 0;
        $b = is_numeric($args[1]) ? $args[1] : 0;

        return $a + $b;
    }

    /**
     * States.TokenCount - Estimate token count.
     *
     * @param array<mixed> $args
     * @return int
     */
    private static function tokenCount(array $args): int
    {
        if (count($args) !== 1) {
            throw new ASLException(
                'States.TokenCount requires exactly 1 argument',
                'States.IntrinsicFailure'
            );
        }

        $text = is_string($args[0]) ? $args[0] : json_encode($args[0]);
        // Rough estimation: ~4 characters per token
        return (int) ceil(strlen($text) / 4);
    }

    /**
     * States.Truncate - Truncate text to token limit.
     *
     * @param array<mixed> $args
     * @return string
     */
    private static function truncate(array $args): string
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.Truncate requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        $text = (string) $args[0];
        $maxTokens = (int) $args[1];
        $maxChars = $maxTokens * 4; // Rough estimation

        if (strlen($text) <= $maxChars) {
            return $text;
        }

        return substr($text, 0, $maxChars) . '...';
    }

    /**
     * States.Merge - Deep merge objects.
     *
     * @param array<mixed> $args
     * @return array<string, mixed>
     */
    private static function merge(array $args): array
    {
        $result = [];

        foreach ($args as $arg) {
            if (is_array($arg)) {
                $result = array_merge_recursive($result, $arg);
            }
        }

        return $result;
    }

    /**
     * States.Pick - Pick specific fields.
     *
     * @param array<mixed> $args
     * @return array<string, mixed>
     */
    private static function pick(array $args): array
    {
        if (count($args) < 2) {
            throw new ASLException(
                'States.Pick requires at least 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<string, mixed> $object */
        $object = array_shift($args);
        $result = [];

        foreach ($args as $field) {
            $key = (string) $field;
            if (isset($object[$key])) {
                $result[$key] = $object[$key];
            }
        }

        return $result;
    }

    /**
     * States.Omit - Omit specific fields.
     *
     * @param array<mixed> $args
     * @return array<string, mixed>
     */
    private static function omit(array $args): array
    {
        if (count($args) < 2) {
            throw new ASLException(
                'States.Omit requires at least 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        /** @var array<string, mixed> $object */
        $object = array_shift($args);
        $fieldsToOmit = array_map('strval', $args);

        return array_diff_key($object, array_flip($fieldsToOmit));
    }

    /**
     * States.ArrayConcat - Concatenate arrays.
     *
     * @param array<mixed> $args
     * @return array<mixed>
     */
    private static function arrayConcat(array $args): array
    {
        $result = [];

        foreach ($args as $arg) {
            if (is_array($arg)) {
                $result = array_merge($result, $arg);
            } else {
                $result[] = $arg;
            }
        }

        return $result;
    }

    /**
     * States.MathSubtract - Subtract two numbers.
     *
     * @param array<mixed> $args
     * @return float|int
     */
    private static function mathSubtract(array $args): float|int
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.MathSubtract requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        $a = is_numeric($args[0]) ? $args[0] : 0;
        $b = is_numeric($args[1]) ? $args[1] : 0;

        return $a - $b;
    }

    /**
     * States.MathMultiply - Multiply two numbers.
     *
     * @param array<mixed> $args
     * @return float|int
     */
    private static function mathMultiply(array $args): float|int
    {
        if (count($args) !== 2) {
            throw new ASLException(
                'States.MathMultiply requires exactly 2 arguments',
                'States.IntrinsicFailure'
            );
        }

        $a = is_numeric($args[0]) ? $args[0] : 0;
        $b = is_numeric($args[1]) ? $args[1] : 0;

        return $a * $b;
    }

    /**
     * States.CurrentCost - Get current workflow execution cost.
     *
     * @param array<string, mixed>|null $context
     * @return float
     */
    private static function currentCost(?array $context): float
    {
        if ($context === null) {
            return 0.0;
        }

        return (float) ($context['Execution']['Cost'] ?? 0.0);
    }

    /**
     * States.CurrentTokens - Get total tokens used in execution.
     *
     * @param array<string, mixed>|null $context
     * @return int
     */
    private static function currentTokens(?array $context): int
    {
        if ($context === null) {
            return 0;
        }

        return (int) ($context['Execution']['TokensUsed'] ?? 0);
    }
}
