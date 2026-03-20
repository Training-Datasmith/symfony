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

use Symfony\Component\Dependency_Injection\Exception\InvalidArgumentException;
use Symfony\Component\Dependency_Injection\Exception\RuntimeException;
use Symfony\Component\Dependency_Injection\Extension\Extension_Interface;
use Symfony\Component\Yaml\Exception\Parse_Exception;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;
/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Yaml_File_Loader extends File_Loader
{
    use Content_Loader_Trait;
    protected bool $auto_register_aliases_for_singly_implemented_interfaces = false;
    private Yaml_Parser $yaml_parser;
    public function load(mixed $resource, ?string $type = null): mixed
    {
        $path = $this->locator->locate($resource);
        $content = $this->load_file($path);
        $this->container->file_exists($path);
        // empty file
        if (null === $content) {
            return null;
        }
        ++$this->importing;
        try {
            $this->load_content($content, $path);
            // per-env configuration
            if ($this->env && isset($content[$when = 'when@' . $this->env])) {
                if (!\is_array($content[$when])) {
                    throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $when, $path));
                }
                $this->load_content($content[$when], $path);
            }
        } finally {
            --$this->importing;
        }
        $this->load_extension_configs();
        return null;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }
        if (null === $type && \in_array(pathinfo($resource, \PATHINFO_EXTENSION), ['yaml', 'yml'], true)) {
            return true;
        }
        return \in_array($type, ['yaml', 'yml'], true);
    }
    /**
     * Loads a YAML file.
     *
     * @throws InvalidArgumentException when the given file is not a local file or when it does not exist
     */
    protected function load_file(string $file): ?array
    {
        if (!class_exists(Yaml_Parser::class)) {
            throw new RuntimeException('Unable to load YAML config files as the Symfony Yaml Component is not installed. Try running "composer require symfony/yaml".');
        }
        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(\sprintf('This is not a local file "%s".', $file));
        }
        if (!is_file($file)) {
            throw new InvalidArgumentException(\sprintf('The file "%s" does not exist.', $file));
        }
        $this->yaml_parser ??= new Yaml_Parser();
        try {
            $configuration = $this->yaml_parser->parse_file($file, Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);
        } catch (Parse_Exception $e) {
            throw new InvalidArgumentException(\sprintf('The file "%s" does not contain valid YAML: ', $file) . $e->get_message(), 0, $e);
        }
        return $this->validate($configuration, $file);
    }
    /**
     * Validates a YAML file.
     *
     * @throws InvalidArgumentException When service file is not valid
     */
    private function validate(mixed $content, string $file): ?array
    {
        if (null === $content) {
            return $content;
        }
        if (!\is_array($content)) {
            throw new InvalidArgumentException(\sprintf('The service file "%s" is not valid. It should contain an array.', $file));
        }
        foreach ($content as $namespace => $data) {
            if (\in_array($namespace, ['imports', 'parameters', 'services'], true)) {
                continue;
            }
            if (str_starts_with((string) $namespace, 'when@')) {
                continue;
            }
            if (!$this->prepend && !$this->container->has_extension($namespace)) {
                $extension_namespaces = array_filter(array_map(static fn(Extension_Interface $ext): string => $ext->get_alias(), $this->container->get_extensions()));
                throw new InvalidArgumentException(Undefined_Extension_Handler::get_error_message($namespace, $file, $namespace, $extension_namespaces));
            }
        }
        return $content;
    }
}