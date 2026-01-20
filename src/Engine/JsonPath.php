<?php

declare(strict_types=1);

namespace AgentStateLanguage\Engine;

use AgentStateLanguage\Exceptions\ASLException;

/**
 * JSONPath evaluation for ASL.
 */
class JsonPath
{
    /**
     * Evaluate a JSONPath expression against data.
     *
     * @param string $path The JSONPath expression
     * @param array<string, mixed> $data The data to evaluate against
     * @param array<string, mixed>|null $context The context object ($$)
     * @return mixed The evaluated value
     * @throws ASLException If path is invalid
     */
    public static function evaluate(
        string $path,
        array $data,
        ?array $context = null
    ): mixed {
        // Handle context references ($$)
        if (str_starts_with($path, '$$.')) {
            if ($context === null) {
                throw new ASLException(
                    "Context reference '$path' used but no context available",
                    'States.ParameterPathFailure'
                );
            }
            return self::evaluatePath(substr($path, 3), $context);
        }

        // Handle root reference ($)
        if ($path === '$') {
            return $data;
        }

        if (!str_starts_with($path, '$.')) {
            throw new ASLException(
                "Invalid JSONPath expression: '$path'",
                'States.ParameterPathFailure'
            );
        }

        return self::evaluatePath(substr($path, 2), $data);
    }

    /**
     * Evaluate a path against data (without the leading $. or $$.)
     *
     * @param string $path
     * @param mixed $data
     * @return mixed
     */
    private static function evaluatePath(string $path, mixed $data): mixed
    {
        if ($path === '') {
            return $data;
        }

        $segments = self::parsePath($path);
        $current = $data;

        foreach ($segments as $segment) {
            if ($current === null) {
                return null;
            }

            if (is_array($current)) {
                // Handle array index [n]
                if (preg_match('/^\[(\d+)\]$/', $segment, $matches)) {
                    $index = (int) $matches[1];
                    $current = $current[$index] ?? null;
                    continue;
                }

                // Handle negative array index [-n]
                if (preg_match('/^\[(-\d+)\]$/', $segment, $matches)) {
                    $index = (int) $matches[1];
                    $count = count($current);
                    $actualIndex = $count + $index;
                    $current = $current[$actualIndex] ?? null;
                    continue;
                }

                // Handle wildcard [*]
                if ($segment === '[*]') {
                    return array_values($current);
                }

                // Handle regular property access
                $current = $current[$segment] ?? null;
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Parse a path into segments.
     *
     * @param string $path
     * @return array<string>
     */
    private static function parsePath(string $path): array
    {
        $segments = [];
        $current = '';
        $inBracket = false;

        for ($i = 0; $i < strlen($path); $i++) {
            $char = $path[$i];

            if ($char === '[') {
                if ($current !== '') {
                    $segments[] = $current;
                    $current = '';
                }
                $inBracket = true;
                $current = '[';
            } elseif ($char === ']') {
                $current .= ']';
                $segments[] = $current;
                $current = '';
                $inBracket = false;
            } elseif ($char === '.' && !$inBracket) {
                if ($current !== '') {
                    $segments[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current !== '') {
            $segments[] = $current;
        }

        return $segments;
    }

    /**
     * Set a value at a JSONPath location.
     *
     * @param string $path The JSONPath expression
     * @param array<string, mixed> $data The data to modify
     * @param mixed $value The value to set
     * @return array<string, mixed> The modified data
     */
    public static function set(string $path, array $data, mixed $value): array
    {
        if ($path === '$' || $path === '') {
            // Replace entire data
            if (!is_array($value)) {
                return ['value' => $value];
            }
            return $value;
        }

        if ($path === 'null') {
            // Discard result
            return $data;
        }

        if (!str_starts_with($path, '$.')) {
            throw new ASLException(
                "Invalid JSONPath for set: '$path'",
                'States.ResultPathMatchFailure'
            );
        }

        $pathPart = substr($path, 2);
        $segments = explode('.', $pathPart);
        
        return self::setNested($data, $segments, $value);
    }

    /**
     * Set a nested value.
     *
     * @param array<string, mixed> $data
     * @param array<string> $segments
     * @param mixed $value
     * @return array<string, mixed>
     */
    private static function setNested(array $data, array $segments, mixed $value): array
    {
        if (empty($segments)) {
            return is_array($value) ? $value : ['value' => $value];
        }

        $key = array_shift($segments);

        if (empty($segments)) {
            $data[$key] = $value;
        } else {
            $data[$key] = self::setNested(
                $data[$key] ?? [],
                $segments,
                $value
            );
        }

        return $data;
    }

    /**
     * Resolve parameters with JSONPath interpolation.
     *
     * @param array<string, mixed> $parameters
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $context
     * @return array<string, mixed>
     */
    public static function resolveParameters(
        array $parameters,
        array $data,
        ?array $context = null
    ): array {
        $resolved = [];

        foreach ($parameters as $key => $value) {
            // Ensure key is a string for string operations
            $keyStr = (string) $key;
            
            // Check if key ends with .$ (dynamic value)
            if (str_ends_with($keyStr, '.$')) {
                $actualKey = substr($keyStr, 0, -2);
                
                if (is_string($value)) {
                    // Check for intrinsic functions
                    if (str_starts_with($value, 'States.')) {
                        $resolved[$actualKey] = IntrinsicFunctions::evaluate(
                            $value,
                            $data,
                            $context
                        );
                    } else {
                        // Regular JSONPath
                        $resolved[$actualKey] = self::evaluate($value, $data, $context);
                    }
                } else {
                    $resolved[$actualKey] = $value;
                }
            } elseif (is_array($value)) {
                // Recursively resolve nested arrays
                $resolved[$key] = self::resolveParameters($value, $data, $context);
            } else {
                // Static value
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
