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
namespace Symfony\Component\Form\Flow\Step_Accessor;

/**
 * Reads from or writes the current step name to a provided data source.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
interface Step_Accessor_Interface
{
    public function get_step(object|array $data, ?string $default = null): ?string;
    public function set_step(object|array &$data, string $step): void;
}