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
namespace Symfony\Component\Form\Extension\Data_Collector\Proxy;

use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Collector_Interface;
use Symfony\Component\Form\Form_Type_Interface;
use Symfony\Component\Form\Resolved_Form_Type_Factory_Interface;
use Symfony\Component\Form\Resolved_Form_Type_Interface;
/**
 * Proxy that wraps resolved types into {@link ResolvedTypeDataCollectorProxy}
 * instances.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Resolved_Type_Factory_Data_Collector_Proxy implements Resolved_Form_Type_Factory_Interface
{
    public function __construct(private readonly Resolved_Form_Type_Factory_Interface $proxied_factory, private readonly Form_Data_Collector_Interface $data_collector)
    {
    }
    public function create_resolved_type(Form_Type_Interface $type, array $type_extensions, ?Resolved_Form_Type_Interface $parent = null): Resolved_Form_Type_Interface
    {
        return new Resolved_Type_Data_Collector_Proxy($this->proxied_factory->create_resolved_type($type, $type_extensions, $parent), $this->data_collector);
    }
}