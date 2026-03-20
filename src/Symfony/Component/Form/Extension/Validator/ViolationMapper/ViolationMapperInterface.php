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
use Symfony\Component\Validator\Constraint_Violation;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Violation_Mapper_Interface
{
    /**
     * Maps a constraint violation to a form in the form tree under
     * the given form.
     *
     * @param bool $allowNonSynchronized Whether to allow mapping to non-synchronized forms
     */
    public function map_violation(Constraint_Violation $violation, Form_Interface $form, bool $allow_non_synchronized = false): void;
}