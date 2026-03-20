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
namespace Symfony\Component\Http_Kernel\Event;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Http_Kernel_Interface;
/**
 * Triggered whenever a request is fully processed.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
final class Finish_Request_Event extends Kernel_Event
{
    public function __construct(Http_Kernel_Interface $kernel, Request $request, ?int $request_type, public readonly ?Controller_Metadata $controller_metadata = null)
    {
        parent::__construct($kernel, $request, $request_type);
    }
}