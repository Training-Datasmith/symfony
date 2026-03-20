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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\Node_Visitor\Translation_Default_Domain_Node_Visitor;
use Symfony\Bridge\Twig\Node_Visitor\Translation_Node_Visitor;
use Symfony\Bridge\Twig\Token_Parser\Trans_Default_Domain_Token_Parser;
use Symfony\Bridge\Twig\Token_Parser\Trans_Token_Parser;
use Symfony\Component\Translation\Translatable_Message;
use Symfony\Contracts\Translation\Translatable_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
use Symfony\Contracts\Translation\Translator_Trait;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
use Twig\Twig_Function;
// Help opcache.preload discover always-needed symbols
class_exists(Translator_Interface::class);
class_exists(Translator_Trait::class);
/**
 * Provides integration of the Translation component with Twig.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Translation_Extension extends Abstract_Extension
{
    public function __construct(private ?Translator_Interface $translator = null, private ?Translation_Node_Visitor $translation_node_visitor = null)
    {
    }
    public function get_translator(): Translator_Interface
    {
        if (null === $this->translator) {
            if (!interface_exists(Translator_Interface::class)) {
                throw new \LogicException(\sprintf('You cannot use the "%s" if the Translation Contracts are not available. Try running "composer require symfony/translation".', self::class));
            }
            $this->translator = new class implements Translator_Interface
            {
                use Translator_Trait;
            };
        }
        return $this->translator;
    }
    public function get_functions(): array
    {
        return [new Twig_Function('t', $this->create_translatable(...))];
    }
    public function get_filters(): array
    {
        return [new Twig_Filter('trans', $this->trans(...))];
    }
    public function get_token_parsers(): array
    {
        return [
            // {% trans %}Symfony is great!{% endtrans %}
            new Trans_Token_Parser(),
            // {% trans_default_domain "foobar" %}
            new Trans_Default_Domain_Token_Parser(),
        ];
    }
    public function get_node_visitors(): array
    {
        return [$this->get_translation_node_visitor(), new Translation_Default_Domain_Node_Visitor()];
    }
    public function get_translation_node_visitor(): Translation_Node_Visitor
    {
        return $this->translation_node_visitor ?: $this->translation_node_visitor = new Translation_Node_Visitor();
    }
    /**
     * @param array|string $arguments Can be the locale as a string when $message is a TranslatableInterface
     */
    public function trans(string|\Stringable|Translatable_Interface|null $message, array|string $arguments = [], ?string $domain = null, ?string $locale = null, ?int $count = null): string
    {
        if ($message instanceof Translatable_Interface) {
            if ([] !== $arguments && !\is_string($arguments)) {
                throw new \TypeError(\sprintf('Argument 2 passed to "%s()" must be a locale passed as a string when the message is a "%s", "%s" given.', __METHOD__, Translatable_Interface::class, get_debug_type($arguments)));
            }
            if ($message instanceof Translatable_Message && '' === $message->get_message()) {
                return '';
            }
            return $message->trans($this->get_translator(), $locale ?? (\is_string($arguments) ? $arguments : null));
        }
        if (!\is_array($arguments)) {
            throw new \TypeError(\sprintf('Unless the message is a "%s", argument 2 passed to "%s()" must be an array of parameters, "%s" given.', Translatable_Interface::class, __METHOD__, get_debug_type($arguments)));
        }
        if ('' === $message = (string) $message) {
            return '';
        }
        if (null !== $count) {
            $arguments['%count%'] = $count;
        }
        return $this->get_translator()->trans($message, $arguments, $domain, $locale);
    }
    public function create_translatable(string $message, array $parameters = [], ?string $domain = null): Translatable_Message
    {
        if (!class_exists(Translatable_Message::class)) {
            throw new \LogicException(\sprintf('You cannot use the "%s" as the Translation Component is not installed. Try running "composer require symfony/translation".', self::class));
        }
        return new Translatable_Message($message, $parameters, $domain);
    }
}