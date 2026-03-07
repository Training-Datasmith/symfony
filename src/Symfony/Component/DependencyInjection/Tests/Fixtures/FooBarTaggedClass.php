<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

class FooBarTaggedClass
{
    private $param;

    public function __construct($param = [])
    {
        $this->param = $param;
    }

    public function getParam()
    {
        return $this->param;
    }
}
