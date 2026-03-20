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
namespace Symfony\Component\Config\Resource;

/**
 * ComposerResource tracks the PHP version and Composer dependencies.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @final
 */
class Composer_Resource implements Self_Checking_Resource_Interface
{
    private readonly array $vendors;
    private static array $runtime_vendors;
    public function __construct()
    {
        self::refresh();
        $this->vendors = self::$runtime_vendors;
    }
    public function get_vendors(): array
    {
        return array_keys($this->vendors);
    }
    public function __toString(): string
    {
        return self::class;
    }
    public function is_fresh(int $timestamp): bool
    {
        self::refresh();
        return array_values(self::$runtime_vendors) === array_values($this->vendors);
    }
    public function __serialize(): array
    {
        return ['vendors' => $this->vendors];
    }
    private static function refresh(): void
    {
        self::$runtime_vendors = [];
        foreach (get_declared_classes() as $class) {
            if ('C' === $class[0] && str_starts_with($class, 'ComposerAutoloaderInit')) {
                $r = new \ReflectionClass($class);
                $v = \dirname($r->get_file_name(), 2);
                if (is_file($v . '/composer/installed.json')) {
                    self::$runtime_vendors[$v] = @filemtime($v . '/composer/installed.json');
                }
            }
        }
    }
}