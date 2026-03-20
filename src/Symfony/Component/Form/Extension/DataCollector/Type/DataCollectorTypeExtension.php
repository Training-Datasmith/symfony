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
namespace Symfony\Component\Form\Extension\Data_Collector\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Form\Extension\Data_Collector\Event_Listener\Data_Collector_Listener;
use Symfony\Component\Form\Extension\Data_Collector\Form_Data_Collector_Interface;
use Symfony\Component\Form\Form_Builder_Interface;
/**
 * Type extension for collecting data of a form with this type.
 *
 * @author Robert Schönthal <robert.schoenthal@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Data_Collector_Type_Extension extends Abstract_Type_Extension
{
    private readonly Data_Collector_Listener $listener;
    public function __construct(Form_Data_Collector_Interface $data_collector)
    {
        $this->listener = new Data_Collector_Listener($data_collector);
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add_event_subscriber($this->listener);
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}