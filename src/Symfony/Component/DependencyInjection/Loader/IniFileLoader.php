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

use Symfony\Component\Config\Util\Xml_Utils;
use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
/**
 * IniFileLoader loads parameters from INI files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Ini_File_Loader extends File_Loader
{
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $path = $this->locator->locate($resource);
        $this->container->file_exists($path);
        // first pass to catch parsing errors
        $result = parse_ini_file($path, true);
        if (false === $result || [] === $result) {
            throw new InvalidArgumentException(\sprintf('The "%s" file is not valid.', $resource));
        }
        // real raw parsing
        $result = parse_ini_file($path, true, \INI_SCANNER_RAW);
        if (isset($result['parameters']) && \is_array($result['parameters'])) {
            foreach ($result['parameters'] as $key => $value) {
                if (\is_array($value)) {
                    $this->container->set_parameter($key, array_map($this->phpize(...), $value));
                } else {
                    $this->container->set_parameter($key, $this->phpize($value));
                }
            }
        }
        if ($this->env && \is_array($result['parameters@' . $this->env] ?? null)) {
            foreach ($result['parameters@' . $this->env] as $key => $value) {
                $this->container->set_parameter($key, $this->phpize($value));
            }
        }
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }
        if (null === $type && 'ini' === pathinfo($resource, \PATHINFO_EXTENSION)) {
            return true;
        }
        return 'ini' === $type;
    }
    /**
     * Note that the following features are not supported:
     *  * strings with escaped quotes are not supported "foo\"bar";
     *  * string concatenation ("foo" "bar").
     */
    private function phpize(string $value): mixed
    {
        // trim on the right as comments removal keep whitespaces
        if ($value !== $v = rtrim($value)) {
            $value = '""' === substr_replace($v, '', 1, -1) ? substr($v, 1, -1) : $v;
        }
        $lowercase_value = strtolower($value);
        return match (true) {
            \defined($value) => \constant($value),
            'yes' === $lowercase_value, 'on' === $lowercase_value => true,
            'no' === $lowercase_value, 'off' === $lowercase_value, 'none' === $lowercase_value => false,
            isset($value[1]) && ("'" === $value[0] && "'" === $value[\strlen($value) - 1] || '"' === $value[0] && '"' === $value[\strlen($value) - 1]) => substr($value, 1, -1),
            // quoted string
            default => Xml_Utils::phpize($value),
        };
    }
}