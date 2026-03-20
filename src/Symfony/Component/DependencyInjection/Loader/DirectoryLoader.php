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
 * DirectoryLoader is a recursive loader to go through directories.
 *
 * @author Sebastien Lavoie <seb@wemakecustom.com>
 */
class Directory_Loader extends File_Loader
{
    public function load(mixed $file, ?string $type = null): mixed
    {
        $file = rtrim((string) $file, '/');
        $path = $this->locator->locate($file);
        $this->container->file_exists($path, false);
        foreach (scandir($path) as $dir) {
            if ('.' !== $dir[0]) {
                if (is_dir($path . '/' . $dir)) {
                    $dir .= '/';
                    // append / to allow recursion
                }
                $this->set_current_dir($path);
                $this->import($dir, null, false, $path);
            }
        }
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        if ('directory' === $type) {
            return true;
        }
        return null === $type && \is_string($resource) && str_ends_with($resource, '/');
    }
}