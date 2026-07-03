<?php

/**
 * Bind a config group to a Property class, compile, then resolve at runtime.
 *
 *   build time : read config, register AppProperty -> group/file/prefix,
 *                dump a container fragment (data baked into a bindData() call)
 *   runtime    : the Container hydrates AppProperty like any other bean
 *
 *   composer install
 *   php example.php
 */

require __DIR__ . '/vendor/autoload.php';

use GenAI\Container\Container;
use GenAI\Property\AbstractProperty;
use GenAI\Property\Property\Definition;
use GenAI\Property\PropertyRegister;
use GenAI\Property\Util\Map;

// A Property class — extends AbstractProperty, which forces it to implement
// bindData(). Each class maps the resolved data onto its own fields however it
// likes. ProductProperty etc. follow the same shape.
class AppProperty extends AbstractProperty
{
    private $name;
    private $version;

    public function bindData(Map $data)
    {
        $this->name    = $data->get('name');
        $this->version = $data->get('version');
    }

    public function getName()
    {
        return $this->name;
    }

    public function getVersion()
    {
        return $this->version;
    }
}

// --- BUILD TIME: read config, register the class binding, compile --------

$register = new PropertyRegister();
$register->loadIni(__DIR__ . '/app.ini');     // grouped under config['app.ini']

// AppProperty draws from app.ini, section [database], the app.* keys (prefix
// stripped): app.name -> name, app.version -> version.
$register->set(
    Definition::of('AppProperty')
        ->group('database')
        ->fromFile('app.ini')
        ->withPrefix('app')
);
// Register more classes the same way for other groups/files:
//   $register->set(Definition::of('CacheProperty')->group('cache')->fromFile('app.ini'));

@mkdir(__DIR__ . '/cache', 0777, true);
$file = __DIR__ . '/cache/Properties.php';     // class Cache\Properties (PSR-4: cache/Properties.php)
$register->dumpToFile($file);

echo "--- generated " . basename($file) . " ---\n";
echo file_get_contents($file);
echo "--- end generated ---\n\n";

// --- RUNTIME: register the compiled Property beans onto a container ----------

$container = new Container();
\Cache\Properties::loadInto($container);

$app = $container->get('AppProperty');   // built once (new + bindData), cached
printf("name    : %s\n", $app->getName());
printf("version : %s\n", $app->getVersion());
