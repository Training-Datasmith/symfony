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
namespace Symfony\Component\Html_Sanitizer\Visitor\Model;

use Symfony\Component\Html_Sanitizer\Visitor\Node\Node_Interface;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 *
 * @internal
 */
final class Cursor
{
    public function __construct(public ?Node_Interface $node)
    {
    }
}