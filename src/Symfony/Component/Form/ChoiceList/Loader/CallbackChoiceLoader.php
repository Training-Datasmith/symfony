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
namespace Symfony\Component\Form\Choice_List\Loader;

/**
 * Loads an {@link ArrayChoiceList} instance from a callable returning iterable choices.
 *
 * @author Jules Pietri <jules@heahprod.com>
 */
class Callback_Choice_Loader extends Abstract_Choice_Loader
{
    private readonly \Closure $callback;
    /**
     * @param callable $callback The callable returning iterable choices
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback(...);
    }
    protected function load_choices(): iterable
    {
        return ($this->callback)();
    }
}