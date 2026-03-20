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
use Symfony\Component\Asset\Version_Strategy\Version_Strategy_Interface;
/**
 * Package that adds a base path to asset URLs in addition to a version.
 *
 * In addition to the provided base path, this package also automatically
 * prepends the current request base path if a Context is available to
 * allow a website to be hosted easily under any given path under the Web
 * Server root directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Path_Package extends Package
{
    private string $base_path;
    /**
     * @param string $basePath The base path to be prepended to relative paths
     */
    public function __construct(string $base_path, Version_Strategy_Interface $version_strategy, ?Context_Interface $context = null)
    {
        parent::__construct($version_strategy, $context);
        if (!$base_path) {
            $this->base_path = '/';
        } else {
            if ('/' != $base_path[0]) {
                $base_path = '/' . $base_path;
            }
            $this->base_path = rtrim($base_path, '/') . '/';
        }
    }
    public function get_url(string $path): string
    {
        $versioned_path = parent::get_url($path);
        // if absolute or begins with /, we're done
        if ($this->is_absolute_url($versioned_path) || $versioned_path && '/' === $versioned_path[0]) {
            return $versioned_path;
        }
        return $this->get_base_path() . ltrim($versioned_path, '/');
    }
    /**
     * Returns the base path.
     */
    public function get_base_path(): string
    {
        return $this->get_context()->get_base_path() . $this->base_path;
    }
}