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
namespace Symfony\Component\Asset;

use Symfony\Component\Asset\Context\Context_Interface;
use Symfony\Component\Asset\Context\Null_Context;
use Symfony\Component\Asset\Version_Strategy\Version_Strategy_Interface;
/**
 * Basic package that adds a version to asset URLs.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Package implements Package_Interface
{
    public function __construct(private readonly Version_Strategy_Interface $version_strategy, private readonly ?Context_Interface $context = new Null_Context())
    {
    }
    public function get_version(string $path): string
    {
        return $this->version_strategy->get_version($path);
    }
    public function get_url(string $path): string
    {
        if ($this->is_absolute_url($path)) {
            return $path;
        }
        return $this->version_strategy->apply_version($path);
    }
    protected function get_context(): Context_Interface
    {
        return $this->context;
    }
    protected function get_version_strategy(): Version_Strategy_Interface
    {
        return $this->version_strategy;
    }
    protected function is_absolute_url(string $url): bool
    {
        return str_contains($url, '://') || str_starts_with($url, '//');
    }
}