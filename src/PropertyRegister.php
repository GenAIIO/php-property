<?php

namespace GenAI\Property;

use GenAI\Property\Property\Definition;
use GenAI\Property\Util\Dumper;
use GenAI\Property\Util\EnvParser;

/**
 * The build-time half of the property component: read config, bind Property
 * classes to it, then compile.
 *
 * You load config sources (grouped per file) and register one Definition per
 * Property class. dump() resolves each Definition's data from the loaded config
 * (file -> group -> prefix, prefix stripped) and emits a container fragment that
 * registers each class as a bean. Mirrors ContainerRegister / RouterRegister.
 *
 *   $register = new PropertyRegister();
 *   $register->loadIni(__DIR__ . '/../application.ini');
 *   $register->set(
 *       Definition::of('AppProperty')
 *           ->group('database')
 *           ->fromFile('application.ini')
 *           ->withPrefix('app')
 *   );
 *   $register->dumpToFile(__DIR__ . '/../var/cache/properties.php');
 *
 * Loaded config is only a build-time source; it is not dumped. The compiled
 * file contains the resolved, bound data per class.
 *
 * Compatible with PHP 5.3.29.
 */
class PropertyRegister
{
    /**
     * Loaded config, keyed by file (basename): each holds that file's parsed
     * contents (nested for .ini sections, flat for .env). Build-time source.
     *
     * @var array
     */
    private $config = array();

    /**
     * Registered class bindings, keyed by Property class name.
     *
     * @var Definition[]
     */
    private $definitions = array();

    /**
     * Register a Property class binding.
     *
     * @param Definition $definition
     * @return PropertyRegister $this, for chaining.
     */
    public function set(Definition $definition)
    {
        $this->definitions[$definition->getClass()] = $definition;

        return $this;
    }

    /**
     * Read a .env file into the config under a file key (basename by default).
     *
     * @param string      $file
     * @param string|null $key
     * @return PropertyRegister $this, for chaining.
     * @throws \RuntimeException If the file cannot be read.
     */
    public function loadEnv($file, $key = null)
    {
        if ($key === null) {
            $key = basename($file);
        }

        $this->config[$key] = self::expand(EnvParser::parseFile($file));

        return $this;
    }

    /**
     * Read an .ini file (sections supported) into the config under a file key
     * (basename by default). Sections stay nested under config[$key].
     *
     * @param string      $file
     * @param string|null $key
     * @return PropertyRegister $this, for chaining.
     * @throws \RuntimeException If the file is missing or cannot be parsed.
     */
    public function loadIni($file, $key = null)
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Ini file "' . $file . '" does not exist.');
        }

        $parsed = parse_ini_file($file, true);
        if ($parsed === false) {
            throw new \RuntimeException('Could not parse ini file "' . $file . '".');
        }

        if ($key === null) {
            $key = basename($file);
        }

        $this->config[$key] = self::expand($parsed);

        return $this;
    }

    /**
     * Compile to PHP source (via the Dumper helper): the full config as a
     * $properties array, plus a factory per registered class that calls
     * bindData() with the right slice of it ($properties[file][group][prefix]).
     *
     * Each binding is validated first: the class must extend AbstractProperty,
     * and its config path must exist.
     *
     * @return string PHP source, starting with "<?php", returning a Closure.
     * @throws \RuntimeException If a binding is invalid.
     */
    public function dump()
    {
        foreach ($this->definitions as $definition) {
            $this->assertClass($definition->getClass());
            if (!$definition->isOptional()) {
                $this->assertPath($definition); // optional bindings may resolve to empty
            }
        }

        return Dumper::dump($this->config, $this->definitions);
    }

    /**
     * Compile and write the source to a file. The directory must exist.
     *
     * @param string $path
     * @return int Bytes written.
     * @throws \RuntimeException If the file cannot be written.
     */
    public function dumpToFile($path)
    {
        $bytes = @file_put_contents($path, $this->dump());
        if ($bytes === false) {
            throw new \RuntimeException('Could not write compiled properties to "' . $path . '".');
        }

        return $bytes;
    }

    /**
     * Assert a Property class exists and extends AbstractProperty.
     *
     * @param string $class
     * @return void
     * @throws \RuntimeException
     */
    private function assertClass($class)
    {
        if (!class_exists($class)) {
            throw new \RuntimeException('Property class "' . $class . '" does not exist.');
        }

        if (!is_subclass_of($class, __NAMESPACE__ . '\\AbstractProperty')) {
            throw new \RuntimeException(
                'Property class "' . $class . '" must extend ' . __NAMESPACE__ . '\\AbstractProperty.'
            );
        }
    }

    /**
     * Assert a definition's config path (file -> group -> prefix) exists and
     * resolves to a group (array), not a single value.
     *
     * @param Definition $definition
     * @return void
     * @throws \RuntimeException
     */
    private function assertPath(Definition $definition)
    {
        $node = $this->config;
        $path = array();

        foreach ($this->pathSegments($definition) as $segment) {
            $path[] = $segment;
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                throw new \RuntimeException(sprintf(
                    'Property "%s": config path [%s] does not exist (missing "%s").',
                    $definition->getClass(),
                    implode('][', $path),
                    $segment
                ));
            }
            $node = $node[$segment];
        }

        if (!is_array($node)) {
            throw new \RuntimeException(sprintf(
                'Property "%s": config path [%s] is a single value, not a group to bind.',
                $definition->getClass(),
                implode('][', $path)
            ));
        }
    }

    /**
     * The config path segments a definition points at (file, group, prefix),
     * skipping any that are unset.
     *
     * @param Definition $definition
     * @return string[]
     */
    private function pathSegments(Definition $definition)
    {
        $segments = array();
        if ($definition->getFile() !== null) {
            $segments[] = $definition->getFile();
        }
        if ($definition->getGroup() !== null) {
            $segments[] = $definition->getGroup();
        }
        if ($definition->getPrefix() !== null) {
            $segments[] = $definition->getPrefix();
        }

        return $segments;
    }

    /**
     * Expand dotted keys into nested arrays so a prefix becomes a real index:
     * 'app.name' => 'x' becomes ['app']['name'] => 'x'. Existing nested arrays
     * (e.g. .ini sections) are expanded recursively.
     *
     * @param array $data
     * @return array
     */
    private static function expand($data)
    {
        $result = array();
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::expand($value);
                continue;
            }

            if (is_string($key) && strpos($key, '.') !== false) {
                $segments = explode('.', $key);
                $last     = array_pop($segments);
                $ref      = &$result;
                foreach ($segments as $segment) {
                    if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                        $ref[$segment] = array();
                    }
                    $ref = &$ref[$segment];
                }
                $ref[$last] = $value;
                unset($ref);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
