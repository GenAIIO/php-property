<?php

namespace GenAI\Property;

use GenAI\Property\Util\Map;

/**
 * Base class for Property objects.
 *
 * The container calls bindData($data) on every Property bean it builds (the
 * compiled fragment does `new X(); $x->bindData(...)`). Making bindData abstract
 * guarantees each Property class defines it — and leaves each one in full control
 * of how it maps the resolved config onto its own fields:
 *
 *   class AppProperty extends AbstractProperty {
 *       private $name;
 *       private $version;
 *       public function bindData(Map $data) {
 *           $this->name    = $data->get('name');
 *           $this->version = $data->get('version');
 *       }
 *       public function getName()    { return $this->name; }
 *       public function getVersion() { return $this->version; }
 *   }
 *
 *   class ProductProperty extends AbstractProperty {
 *       private $sku;
 *       public function bindData(Map $data) {
 *           $this->sku = $data->get('sku');
 *       }
 *       public function getSku() { return $this->sku; }
 *   }
 *
 * Compatible with PHP 5.3.29.
 */
abstract class AbstractProperty
{
    /**
     * Map the resolved config onto this object's fields. Each Property class
     * implements this itself, reading values via $data->get('key').
     *
     * @param Map $data The resolved group, as a read-only accessor.
     * @return void
     */
    abstract public function bindData(Map $data);
}
