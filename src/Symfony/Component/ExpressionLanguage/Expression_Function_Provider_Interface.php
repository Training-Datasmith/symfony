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
namespace Symfony\Component\Expression_Language;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface Expression_Function_Provider_Interface
{
    /**
     * @return ExpressionFunction[]
     */
    public function get_functions(): array;
}