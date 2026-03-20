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
namespace Symfony\Component\Dependency_Injection\Loader;

/**
 * GlobFileLoader loads files from a glob pattern.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Glob_File_Loader extends File_Loader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        foreach ($this->glob($resource, false, $glob_resource) as $path => $info) {
            $this->import($path);
        }
        $this->container->add_resource($glob_resource);
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'glob' === $type;
    }
}