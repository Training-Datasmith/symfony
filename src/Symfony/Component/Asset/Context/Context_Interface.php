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
 * Holds information about the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Context_Interface
{
    /**
     * Gets the base path.
     */
    public function get_base_path(): string;
    /**
     * Checks whether the request is secure or not.
     */
    public function is_secure(): bool;
}