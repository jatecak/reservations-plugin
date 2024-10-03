<?php

namespace Reservations\Utils;

use Nette\Reflection;

trait ServiceContainer
{
    protected function createService($classType, $propertyName = null)
    {
        $reflection = Reflection\ClassType::from($classType);

        $constructorArgCount = 0;
        $constructor         = $reflection->getMethod("__construct");
        if ($constructor) {
            $constructorArgCount = $constructor->getNumberOfRequiredParameters();
        }

        if ($constructorArgCount === 1) {
            $instance = $reflection->newInstance($this);
        } else {
            $instance = $reflection->newInstance();
        }

        if (!is_null($propertyName)) {
            $this->{$propertyName} = $instance;
        }

        if ($reflection->hasMethod("init")) {
            $instance->init();
        }

        return $instance;
    }
}
