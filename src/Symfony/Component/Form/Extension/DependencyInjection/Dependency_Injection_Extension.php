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
namespace Symfony\Component\Form\Extension\Dependency_Injection;

use Psr\Container\Container_Interface;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Form_Extension_Interface;
use Symfony\Component\Form\Form_Type_Extension_Interface;
use Symfony\Component\Form\Form_Type_Guesser_Chain;
use Symfony\Component\Form\Form_Type_Guesser_Interface;
use Symfony\Component\Form\Form_Type_Interface;
class Dependency_Injection_Extension implements Form_Extension_Interface
{
    private ?Form_Type_Guesser_Chain $guesser = null;
    private bool $guesser_loaded = false;
    /**
     * @param array<string, iterable<FormTypeExtensionInterface>> $typeExtensionServices
     */
    public function __construct(private readonly Container_Interface $type_container, private array $type_extension_services, private readonly iterable $guesser_services)
    {
    }
    public function get_type(string $name): Form_Type_Interface
    {
        if (!$this->type_container->has($name)) {
            throw new InvalidArgumentException(\sprintf('The field type "%s" is not registered in the service container.', $name));
        }
        return $this->type_container->get($name);
    }
    public function has_type(string $name): bool
    {
        return $this->type_container->has($name);
    }
    public function get_type_extensions(string $name): array
    {
        $extensions = [];
        if (isset($this->type_extension_services[$name])) {
            foreach ($this->type_extension_services[$name] as $extension) {
                $extensions[] = $extension;
                $extended_types = [];
                foreach ($extension::get_extended_types() as $extended_type) {
                    $extended_types[] = $extended_type;
                }
                // validate the result of getExtendedTypes() to ensure it is consistent with the service definition
                if (!\in_array($name, $extended_types, true)) {
                    throw new InvalidArgumentException(\sprintf('The extended type "%s" specified for the type extension class "%s" does not match any of the actual extended types (["%s"]).', $name, $extension::class, implode('", "', $extended_types)));
                }
            }
        }
        return $extensions;
    }
    public function has_type_extensions(string $name): bool
    {
        return isset($this->type_extension_services[$name]);
    }
    public function get_type_guesser(): ?Form_Type_Guesser_Interface
    {
        if (!$this->guesser_loaded) {
            $this->guesser_loaded = true;
            $guessers = [];
            foreach ($this->guesser_services as $service) {
                $guessers[] = $service;
            }
            if ($guessers) {
                $this->guesser = new Form_Type_Guesser_Chain($guessers);
            }
        }
        return $this->guesser;
    }
}