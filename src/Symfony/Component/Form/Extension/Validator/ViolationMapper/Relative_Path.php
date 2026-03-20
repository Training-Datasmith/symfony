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
namespace Symfony\Component\Form\Extension\Validator\Violation_Mapper;

use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Property_Access\Property_Path;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Relative_Path extends Property_Path
{
    public function __construct(private readonly Form_Interface $root, string $property_path)
    {
        parent::__construct($property_path);
    }
    public function get_root(): Form_Interface
    {
        return $this->root;
    }
}