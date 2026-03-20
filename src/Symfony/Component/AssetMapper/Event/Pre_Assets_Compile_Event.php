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
namespace Symfony\Component\Asset_Mapper\Event;

use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Contracts\Event_Dispatcher\Event;
/**
 * Dispatched during the asset-map:compile command, before the assets are compiled.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class Pre_Assets_Compile_Event extends Event
{
    public function __construct(private readonly Output_Interface $output)
    {
    }
    public function get_output(): Output_Interface
    {
        return $this->output;
    }
}