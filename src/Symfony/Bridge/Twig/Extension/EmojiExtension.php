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

use Symfony\Component\Emoji\Emoji_Transliterator;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
final class Emoji_Extension extends Abstract_Extension
{
    private static array $transliterators = [];
    public function __construct(private readonly string $default_catalog = 'text')
    {
        if (!class_exists(Emoji_Transliterator::class)) {
            throw new \LogicException('You cannot use the "emojify" filter as the "Emoji" component is not installed. Try running "composer require symfony/emoji".');
        }
    }
    public function get_filters(): array
    {
        return [new Twig_Filter('emojify', $this->emojify(...))];
    }
    /**
     * Converts emoji short code (:wave:) to real emoji (👋).
     */
    public function emojify(string $string, ?string $catalog = null): string
    {
        $catalog ??= $this->default_catalog;
        try {
            $tr = self::$transliterators[$catalog] ??= Emoji_Transliterator::create($catalog, Emoji_Transliterator::REVERSE);
        } catch (\Intl_Exception $e) {
            throw new \LogicException(\sprintf('The emoji catalog "%s" is not available.', $catalog), previous: $e);
        }
        return (string) $tr->transliterate($string);
    }
}