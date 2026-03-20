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
namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Event_Listener\Transformation_Failure_Listener;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class Transformation_Failure_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly ?Translator_Interface $translator = null)
    {
    }
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        if (!isset($options['constraints'])) {
            $builder->add_event_subscriber(new Transformation_Failure_Listener($this->translator));
        }
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}