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
namespace Symfony\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\Abstract_Type_Extension;
use Symfony\Component\Form\Extension\Core\Type\Form_Type;
use Symfony\Component\Options_Resolver\Options;
use Symfony\Component\Options_Resolver\Options_Resolver;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Abdellatif Ait boudad <a.aitboudad@gmail.com>
 * @author David Badura <d.a.badura@gmail.com>
 */
class Upload_Validator_Extension extends Abstract_Type_Extension
{
    public function __construct(private readonly Translator_Interface $translator, private readonly ?string $translation_domain = null)
    {
    }
    public function configure_options(Options_Resolver $resolver): void
    {
        $translator = $this->translator;
        $translation_domain = $this->translation_domain;
        $resolver->set_normalizer('upload_max_size_message', static fn(Options $options, $message): \Closure => static fn(): string => $translator->trans($message(), [], $translation_domain));
    }
    public static function get_extended_types(): iterable
    {
        return [Form_Type::class];
    }
}