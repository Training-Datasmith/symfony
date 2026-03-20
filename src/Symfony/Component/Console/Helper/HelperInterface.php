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
namespace Symfony\Component\Console\Helper;

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Helper_Interface
{
    /**
     * Sets the helper set associated with this helper.
     */
    public function set_helper_set(?Helper_Set $helper_set): void;
    /**
     * Gets the helper set associated with this helper.
     */
    public function get_helper_set(): ?Helper_Set;
    /**
     * Returns the canonical name of this helper.
     */
    public function get_name(): string;
}