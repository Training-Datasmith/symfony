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
namespace Symfony\Bundle\Twig_Bundle\Cache_Warmer;

use Psr\Container\Container_Interface;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Interface;
use Symfony\Contracts\Service\Service_Subscriber_Interface;
use Twig\Cache\Cache_Interface;
use Twig\Cache\Null_Cache;
use Twig\Environment;
use Twig\Error\Error;
/**
 * Generates the Twig cache for all templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class Template_Cache_Warmer implements Cache_Warmer_Interface, Service_Subscriber_Interface
{
    private Environment $twig;
    /**
     * As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
     */
    public function __construct(private readonly Container_Interface $container, private readonly iterable $iterator, private readonly ?Cache_Interface $cache = null)
    {
    }
    public function warm_up(string $cache_dir, ?string $build_dir = null): array
    {
        $this->twig ??= $this->container->get('twig');
        $original_cache = $this->twig->get_cache();
        if ($original_cache instanceof Null_Cache) {
            // There's no point to warm up a cache that won't be used afterward
            return [];
        }
        if (null !== $this->cache) {
            if (!$build_dir) {
                /*
                 * The cache has already been warmup during the build of the container, when $buildDir was set.
                 */
                return [];
            }
            // Swap the cache for the warmup as the Twig Environment has the ChainCache injected
            $this->twig->set_cache($this->cache);
        }
        try {
            foreach ($this->iterator as $template) {
                try {
                    $this->twig->load($template);
                } catch (Error) {
                    /*
                     * Problem during compilation, give up for this template (e.g. syntax errors).
                     * Failing silently here allows to ignore templates that rely on functions that aren't available in
                     * the current environment. For example, the WebProfilerBundle shouldn't be available in the prod
                     * environment, but some templates that are never used in prod might rely on functions the bundle provides.
                     * As we can't detect which templates are "really" important, we try to load all of them and ignore
                     * errors. Error checks may be performed by calling the lint:twig command.
                     */
                }
            }
        } finally {
            $this->twig->set_cache($original_cache);
        }
        return [];
    }
    public function is_optional(): bool
    {
        return true;
    }
    public static function get_subscribed_services(): array
    {
        return ['twig' => Environment::class];
    }
}