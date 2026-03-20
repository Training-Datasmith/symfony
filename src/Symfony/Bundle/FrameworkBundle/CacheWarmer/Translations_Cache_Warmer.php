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
namespace Symfony\Bundle\Framework_Bundle\Cache_Warmer;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Warmable_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Generates the catalogues for translations.
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
final class Translations_Cache_Warmer implements Cache_Warmer_Interface, Service_Subscriber_Interface
{
    private Translator_Interface $translator;
    /**
     * As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
     */
    public function __construct(private readonly Container_Interface $container)
    {
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        $this->translator ??= $this->container->get('translator');
        if ($this->translator instanceof Warmable_Interface) {
            return $this->translator->warm_up($cache_dir, $build_dir);
        }
        return [];
    }
    public function is_optional(): bool
    {
        return true;
    }
    public static function get_subscribed_services(): array
    {
        return ['translator' => Translator_Interface::class];
    }
}