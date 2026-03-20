<?php

declare (strict_types=1);
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Symfony\Component\Dependency_Injection\Container_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Reference_Configurator extends Abstract_Configurator implements \Stringable
{
    /** @internal */
    protected int $invalid_behavior = Container_Interface::EXCEPTION_ON_INVALID_REFERENCE;
    public function __construct(
        /** @internal */
        protected string $id
    )
    {
    }
    /**
     * @return $this
     */
    final public function ignore_on_invalid(): static
    {
        $this->invalid_behavior = Container_Interface::IGNORE_ON_INVALID_REFERENCE;
        return $this;
    }
    /**
     * @return $this
     */
    final public function null_on_invalid(): static
    {
        $this->invalid_behavior = Container_Interface::NULL_ON_INVALID_REFERENCE;
        return $this;
    }
    /**
     * @return $this
     */
    final public function ignore_on_uninitialized(): static
    {
        $this->invalid_behavior = Container_Interface::IGNORE_ON_UNINITIALIZED_REFERENCE;
        return $this;
    }
    public function __toString(): string
    {
        return $this->id;
    }
}