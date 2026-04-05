<?php

declare(strict_types=1);

namespace IPTools;

/**
 * Enables property-style access to getter/setter methods.
 *
 * Resolution order for `$obj->foo`:
 *   1. `$obj->foo()` — direct method name match
 *   2. `$obj->getFoo()` — standard getter prefix
 *   3. `$obj->toFoo()` — conversion prefix (e.g., `$ip->hex` calls `toHex()`)
 *
 * For writes, `$obj->foo = $value` calls `$obj->setFoo($value)`.
 *
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 */
trait PropertyTrait
{
    public function __get(string $name): mixed
    {
        if (method_exists($this, $name)) {
            return $this->$name();
        }

        // Try 'get' first (getVersion), then 'to' (toHex, toBin, toLong)
        foreach (['get', 'to'] as $prefix) {
            $method = $prefix.ucfirst($name);
            if (method_exists($this, $method)) {
                return $this->$method();
            }
        }

        trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name));

        return null;
    }

    public function __set(string $name, mixed $value): void
    {
        $method = 'set'.ucfirst($name);
        if (! method_exists($this, $method)) {
            trigger_error(sprintf('Undefined property: %s::$%s', static::class, $name));

            return;
        }
        $this->$method($value);
    }
}
