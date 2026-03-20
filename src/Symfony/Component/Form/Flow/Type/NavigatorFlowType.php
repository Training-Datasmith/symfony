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
namespace Symfony\Component\Form\Flow\Type;

use Symfony\Component\Form\Abstract_Type;
use Symfony\Component\Form\Form_Builder_Interface;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A navigator type that defines default buttons to interact with a form flow.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Navigator_Flow_Type extends Abstract_Type
{
    public function build_form(Form_Builder_Interface $builder, array $options): void
    {
        $builder->add('previous', Previous_Flow_Type::class);
        $builder->add('next', Next_Flow_Type::class);
        $builder->add('finish', Finish_Flow_Type::class);
        if ($options['with_reset']) {
            $builder->add('reset', Reset_Flow_Type::class);
        }
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->set_defaults(['label' => false, 'mapped' => false, 'priority' => -100]);
        $resolver->define('with_reset')->allowed_types('bool')->default(false)->info('Whether to add a reset button to restart the flow from the first step');
    }
}