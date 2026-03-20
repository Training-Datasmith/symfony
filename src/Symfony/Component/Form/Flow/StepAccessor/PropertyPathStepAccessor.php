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

use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Component\Property_Access\Property_Path_Interface;
/**
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Property_Path_Step_Accessor implements Step_Accessor_Interface
{
    public function __construct(private readonly Property_Accessor_Interface $property_accessor, private readonly Property_Path_Interface $property_path)
    {
    }
    public function get_step(object|array $data, ?string $default = null): ?string
    {
        return $this->property_accessor->get_value($data, $this->property_path) ?: $default;
    }
    public function set_step(object|array &$data, string $step): void
    {
        $this->property_accessor->set_value($data, $this->property_path, $step);
    }
}