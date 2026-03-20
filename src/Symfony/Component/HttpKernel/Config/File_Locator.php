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
namespace Symfony\Component\Http_Kernel\Config;

use Symfony\Component\Config\File_Locator as BaseFileLocator;
use Symfony\Component\Http_Kernel\Kernel_Interface;
/**
 * FileLocator uses the KernelInterface to locate resources in bundles.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class File_Locator extends Base_File_Locator
{
    public function __construct(private readonly Kernel_Interface $kernel)
    {
        parent::__construct();
    }
    public function locate(string $file, ?string $current_path = null, bool $first = true): string|array
    {
        if (isset($file[0]) && '@' === $file[0]) {
            $resource = $this->kernel->locate_resource($file);
            return $first ? $resource : [$resource];
        }
        return parent::locate($file, $current_path, $first);
    }
}