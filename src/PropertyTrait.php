<?php

declare(strict_types=1);

namespace IPTools;

/**
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
