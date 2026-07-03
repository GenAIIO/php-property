<?php

namespace GenAI\Property\Attribute;

use GenAI\Attribute\AttributeProcessor;
use GenAI\Attribute\Context;
use GenAI\Property\Property\Definition;
use GenAI\Property\PropertyRegister;

/**
 * Ships with genai/property: turns #[Property] on Property classes into a
 * compiled properties file. A consumer scans the GenAI\Property\Attribute
 * namespace (or addProcessor) and gets config-binding-from-attributes for free.
 *
 * It defers to compile(): process() records each binding, then compile() loads
 * the referenced config files (resolved against the context's config dir) and
 * dumps.
 *
 * BUILD-TIME ONLY (PHP 8). Requires the genai/attribute scanner.
 */
class PropertyProcessor implements AttributeProcessor
{
    /** @var array<int, array{0:string,1:string,2:string,3:?string}> */
    private array $bindings = [];

    public function getAttributeClass(): string
    {
        return Property::class;
    }

    public function process(object $attribute, \Reflector $target): void
    {
        /** @var \ReflectionClass $target */
        $this->bindings[] = [$target->getName(), $attribute->group, $attribute->file, $attribute->prefix, $attribute->optional];
    }

    public function compile(Context $context): void
    {
        $register = new PropertyRegister();
        $loaded = [];

        foreach ($this->bindings as [$class, $group, $file, $prefix, $optional]) {
            if (!isset($loaded[$file])) {
                $path = $context->config($file);
                if (is_file($path)) {
                    $register->loadIni($path);
                    $loaded[$file] = true;
                } elseif (!$optional) {
                    $register->loadIni($path); // not optional + missing -> throw a clear error
                }
                // optional + missing: leave the file unloaded; the binding resolves to empty.
            }

            $register->set(
                Definition::of($class)->group($group)->fromFile($file)->withPrefix($prefix)->optional($optional)
            );
        }

        $register->dumpToFile($context->output('Properties.php')); // class Cache\Properties
    }
}
