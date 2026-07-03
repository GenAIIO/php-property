<?php

namespace GenAI\Property\Util;

/**
 * Parses a .env file into a flat key => value map.
 *
 * PHP 5.3 has no dotenv library, so this is a small hand-rolled parser:
 *   - blank lines and lines starting with '#' are ignored
 *   - an optional leading "export " is stripped
 *   - KEY=VALUE is split on the first '='
 *   - a value wrapped in matching single or double quotes is unquoted
 *
 * Values are returned as raw strings (no type coercion); inline comments after
 * a value are NOT stripped — keep comments on their own line.
 *
 * Compatible with PHP 5.3.29.
 */
class EnvParser
{
    /**
     * @param string $file Path to a .env file.
     * @return array Flat key => string-value map.
     * @throws \RuntimeException If the file cannot be read.
     */
    public static function parseFile($file)
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Env file "' . $file . '" does not exist.');
        }

        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \RuntimeException('Could not read env file "' . $file . '".');
        }

        return self::parse($contents);
    }

    /**
     * @param string $contents Raw .env text.
     * @return array Flat key => string-value map.
     */
    public static function parse($contents)
    {
        $result = array();

        $lines = preg_split('/\r\n|\r|\n/', $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (strpos($line, 'export ') === 0) {
                $line = ltrim(substr($line, 7));
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = rtrim(substr($line, 0, $pos));
            $value = self::unquote(ltrim(substr($line, $pos + 1)));

            if ($key !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Strip a single pair of matching surrounding quotes, if present.
     *
     * @param string $value
     * @return string
     */
    private static function unquote($value)
    {
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, $len - 2);
            }
        }

        return $value;
    }
}
