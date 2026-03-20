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
use Symfony\Component\Form\Extension\Core\Type\Submit_Type;
use Symfony\Component\Form\Flow\Button_Flow_Type_Interface;
use Symfony\Component\Form\Flow\Form_Flow_Cursor;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * A submit button with a callable handler for a form flow.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class Button_Flow_Type extends Abstract_Type implements Button_Flow_Type_Interface
{
    public function configure_options(Options_Resolver $resolver): void
    {
        $resolver->define('handler')->info('The callable that will be called when this button is clicked')->required()->allowed_types('callable');
        $resolver->define('include_if')->info('Decide whether to include this button in the current form')->default(null)->allowed_types('null', 'array', 'callable')->normalize(static function (Options $options, mixed $value) {
            if (\is_array($value)) {
                return static fn(Form_Flow_Cursor $cursor): bool => \in_array($cursor->get_current_step(), $value, true);
            }
            return $value;
        });
        $resolver->define('clear_submission')->info('Whether the submitted data will be cleared when this button is clicked')->default(false)->allowed_types('bool');
        $resolver->set_default('validate', static fn(Options $options): bool => !$options['clear_submission']);
        $resolver->set_default('validation_groups', static fn(Options $options): ?false => $options['clear_submission'] ? false : null);
    }
    public function get_parent(): string
    {
        return Submit_Type::class;
    }
}