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
namespace Symfony\Component\Config;

use Symfony\Component\Config\Resource\Resource_Interface;
use Symfony\Component\Config\Resource\Self_Checking_Resource_Checker;
use Symfony\Component\Config\Resource\Skipping_Resource_Checker;
/**
 * ConfigCache caches arbitrary content in files on disk.
 *
 * When in debug mode, those metadata resources that implement
 * \Symfony\Component\Config\Resource\SelfCheckingResourceInterface will
 * be used to check cache freshness.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class Config_Cache extends Resource_Checker_Config_Cache
{
    /**
     * @param string                                 $file                 The absolute cache path
     * @param bool                                   $debug                Whether debugging is enabled or not
     * @param string|null                            $metaFile             The absolute path to the meta file
     * @param class-string<ResourceInterface>[]|null $skippedResourceTypes
     */
    public function __construct(string $file, private readonly bool $debug, ?string $meta_file = null, ?array $skipped_resource_types = null)
    {
        $checkers = [];
        if ($this->debug) {
            if (null !== $skipped_resource_types) {
                $checkers[] = new Skipping_Resource_Checker($skipped_resource_types);
            }
            $checkers[] = new Self_Checking_Resource_Checker();
        }
        parent::__construct($file, $checkers, $meta_file);
    }
    /**
     * Checks if the cache is still fresh.
     *
     * This implementation always returns true when debug is off and the
     * cache file exists.
     */
    public function is_fresh(): bool
    {
        if (!$this->debug && is_file($this->get_path())) {
            return true;
        }
        return parent::is_fresh();
    }
}