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
namespace Symfony\Component\Http_Kernel\Fragment;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Controller\Controller_Reference;
/**
 * Interface implemented by rendering strategies able to generate a URL for a fragment.
 *
 * @author Kévin Dunglas <kevin@dunglas.fr>
 */
interface Fragment_Uri_Generator_Interface
{
    /**
     * Generates a fragment URI for a given controller.
     *
     * @param bool $absolute Whether to generate an absolute URL or not
     * @param bool $strict   Whether to allow non-scalar attributes or not
     * @param bool $sign     Whether to sign the URL or not
     */
    public function generate(Controller_Reference $controller, ?Request $request = null, bool $absolute = false, bool $strict = true, bool $sign = true): string;
}