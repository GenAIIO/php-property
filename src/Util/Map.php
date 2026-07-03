<?php

namespace GenAI\Property\Util;

/**
 * A small read-only key => value accessor passed to Property::bindData().
 *
 * It spares each bindData() the PHP 5.3 "isset(...) ? ... : null" dance (there
 * is no ?? operator until PHP 7) and avoids undefined-index notices when a key
 * is missing or mistyped:
 *
 *   $this->name = $data->get('name');
 *   $this->ttl  = $data->get('ttl', 3600);   // with a default
 *
 * Use at() to navigate a nested array by a path: it never errors on a missing
 * group/prefix, returning an empty Map instead. The generated bindData() calls
 * use it so a wrong/absent path is harmless at runtime (the build-time check in
 * PropertyRegister already flags it loudly).
 *
 * Compatible with PHP 5.3.29.
 */
class Map
{
    /** @var array */
    private $data;

    /**
     * @param mixed $data Wrapped as-is if an array, otherwise an empty map.
     */
    public function __construct($data)
    {
        $this->data = is_array($data) ? $data : array();
    }

    /**
     * Navigate $data by $path (a list of keys) and wrap the result in a Map.
     * If any segment is missing or not an array, an empty Map is returned.
     *
     * @param array    $data
     * @param string[] $path
     * @return Map
     */
    public static function at($data, $path)
    {
        $node = $data;
        foreach ($path as $segment) {
            if (is_array($node) && array_key_exists($segment, $node)) {
                $node = $node[$segment];
            } else {
                $node = array();
                break;
            }
        }

        return new self($node);
    }

    /**
     * Value for $key, or $default if absent.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @return array The whole underlying map.
     */
    public function all()
    {
        return $this->data;
    }
}
