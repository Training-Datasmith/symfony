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
namespace Symfony\Component\Form\Extension\Data_Collector;

use Symfony\Component\Form\Abstract_Extension;
/**
 * Extension for collecting data of the forms on a page.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Data_Collector_Extension extends Abstract_Extension
{
    public function __construct(private readonly Form_Data_Collector_Interface $data_collector)
    {
    }
    protected function load_type_extensions(): array
    {
        return [new Type\Data_Collector_Type_Extension($this->data_collector)];
    }
}