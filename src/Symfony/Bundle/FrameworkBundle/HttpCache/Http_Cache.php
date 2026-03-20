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
namespace Symfony\Bundle\Framework_Bundle\Http_Cache;

use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Http_Cache\Esi;
use Symfony\Component\Http_Kernel\Http_Cache\Http_Cache as BaseHttpCache;
use Symfony\Component\Http_Kernel\Http_Cache\Store;
use Symfony\Component\Http_Kernel\Http_Cache\Store_Interface;
use Symfony\Component\Http_Kernel\Http_Cache\Surrogate_Interface;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * Manages HTTP cache objects in a Container.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Http_Cache extends Base_Http_Cache
{
    protected ?string $cache_dir = null;
    private ?Store_Interface $store = null;
    private array $options;
    /**
     * @param $cache The cache directory (default used if null) or the storage instance
     */
    public function __construct(protected Kernel_Interface $kernel, string|Store_Interface|null $cache = null, private readonly ?Surrogate_Interface $surrogate = null, ?array $options = null)
    {
        $this->options = $options ?? [];
        if ($cache instanceof Store_Interface) {
            $this->store = $cache;
        } else {
            $this->cache_dir = $cache;
        }
        if (null === $options && $kernel->is_debug()) {
            $this->options = ['debug' => true];
        }
        if ($this->options['debug'] ?? false) {
            $this->options += ['stale_if_error' => 0];
        }
        parent::__construct($kernel, $this->create_store(), $this->create_surrogate(), array_merge($this->options, $this->get_options()));
    }
    protected function forward(Request $request, bool $catch = false, ?Response $entry = null): Response
    {
        $this->get_kernel()->boot();
        $this->get_kernel()->get_container()->set('cache', $this);
        return parent::forward($request, $catch, $entry);
    }
    /**
     * Returns an array of options to customize the Cache configuration.
     */
    protected function get_options(): array
    {
        return [];
    }
    protected function create_surrogate(): Surrogate_Interface
    {
        return $this->surrogate ?? new Esi();
    }
    protected function create_store(): Store_Interface
    {
        return $this->store ?? new Store($this->cache_dir ?: ($this->kernel->get_share_dir() ?? $this->kernel->get_cache_dir()) . '/http_cache');
    }
}