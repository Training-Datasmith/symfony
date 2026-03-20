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

use Symfony\Component\Property_Access\Property_Path_Iterator;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Violation_Path_Iterator extends Property_Path_Iterator
{
    public function __construct(Violation_Path $violation_path)
    {
        parent::__construct($violation_path);
    }
    public function maps_form(): bool
    {
        return $this->path->maps_form($this->key());
    }
}