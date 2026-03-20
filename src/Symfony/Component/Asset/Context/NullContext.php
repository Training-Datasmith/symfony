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
namespace Symfony\Component\Asset\Context;

/**
 * A context that does nothing.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Null_Context implements Context_Interface
{
    public function get_base_path(): string
    {
        return '';
    }
    public function is_secure(): bool
    {
        return false;
    }
}