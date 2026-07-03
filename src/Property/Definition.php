<?php

namespace GenAI\Property\Property;

/**
 * A property binding: which Property class is hydrated, and where its values
 * come from (file -> group -> prefix).
 *
 *   Definition::of('AppProperty')      // the class to hydrate
 *       ->group('database')            // the config group (.ini section)
 *       ->fromFile('application.ini')  // the source file
 *       ->withPrefix('app');           // take app.* keys, prefix stripped
 *
 *   $def->getClass();   // 'AppProperty'
 *   $def->getGroup();   // 'database'
 *   $def->getFile();    // 'application.ini'
 *   $def->getPrefix();  // 'app'
 *
 * At runtime this resolves to: load `file`, take group `group`, keep the keys
 * starting with `prefix` and strip that prefix, then hand the result to the
 * class:
 *
 *   class AppProperty {
 *       private $name;
 *       public function bindData($data) {
 *           $this->name = $data['name'];   // app.name from [database] in application.ini
 *       }
 *   }
 *
 * Fields:
 *   class  - the Property class to instantiate and bindData() on.
 *   group  - the config group (e.g. an .ini section).
 *   file   - which config file to load (e.g. product.ini, generate.ini).
 *   prefix - key prefix selected within the group, then stripped: 'app' turns
 *            'app.name' into 'name'. null means "all keys, unstripped".
 *
 * Build-time DTO, private constructor + of() factory (mirrors the container and
 * router definitions).
 *
 * Compatible with PHP 5.3.29.
 */
class Definition
{
    /** @var string The Property class to hydrate, e.g. 'AppProperty'. */
    private $class;

    /** @var string|null The config group (e.g. an .ini section). */
    private $group;

    /** @var string|null The config file to load this from. */
    private $file;

    /** @var string|null Key prefix to select and strip (null = all keys). */
    private $prefix;

    /** @var bool When true, a missing file/group binds to empty config instead of erroring. */
    private $optional = false;

    /**
     * Private — build a Definition through of().
     *
     * @param string $class
     */
    private function __construct($class)
    {
        $this->class  = $class;
        $this->group  = null;
        $this->file   = null;
        $this->prefix = null;
    }

    /**
     * Build a definition for a Property class.
     *
     * @param string $class The class to hydrate, e.g. 'AppProperty'.
     * @return Definition
     * @throws \InvalidArgumentException If $class is empty.
     */
    public static function of($class)
    {
        if ($class === '' || $class === null) {
            throw new \InvalidArgumentException('A property Definition needs a non-empty class.');
        }

        return new self($class);
    }

    /**
     * Set the config group this class binds to (e.g. an .ini section).
     *
     * @param string $group
     * @return Definition $this, for chaining.
     */
    public function group($group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Set the config file this group is loaded from (e.g. 'application.ini').
     *
     * @param string $file
     * @return Definition $this, for chaining.
     */
    public function fromFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Select the keys under this prefix and strip it — 'app' turns 'app.name'
     * and 'app.version' into 'name' and 'version'.
     *
     * @param string $prefix
     * @return Definition $this, for chaining.
     */
    public function withPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * @return string The Property class name.
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return string|null The config group, or null if unset.
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * @return string|null The source config file, or null if unset.
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return string|null The key prefix, or null for all keys.
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Mark this binding optional — a missing file/group resolves to empty config.
     *
     * @param bool $optional
     * @return Definition $this, for chaining.
     */
    public function optional($optional = true)
    {
        $this->optional = $optional;

        return $this;
    }

    /**
     * @return bool
     */
    public function isOptional()
    {
        return $this->optional;
    }
}
