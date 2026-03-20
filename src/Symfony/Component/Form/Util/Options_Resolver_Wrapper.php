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
namespace Symfony\Component\Form\Util;

use Symfony\Component\Options_Resolver\Exception\Access_Exception;
use Symfony\Component\Options_Resolver\Exception\Undefined_Options_Exception;
use Symfony\Component\Options_Resolver\Options_Resolver;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Options_Resolver_Wrapper extends Options_Resolver
{
    private array $undefined = [];
    /**
     * @return $this
     */
    public function set_normalizer(string $option, \Closure $normalizer): static
    {
        try {
            parent::set_normalizer($option, $normalizer);
        } catch (Undefined_Options_Exception) {
            $this->undefined[$option] = true;
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function set_allowed_values(string $option, mixed $allowed_values): static
    {
        try {
            parent::set_allowed_values($option, $allowed_values);
        } catch (Undefined_Options_Exception) {
            $this->undefined[$option] = true;
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function add_allowed_values(string $option, mixed $allowed_values): static
    {
        try {
            parent::add_allowed_values($option, $allowed_values);
        } catch (Undefined_Options_Exception) {
            $this->undefined[$option] = true;
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function set_allowed_types(string $option, string|array $allowed_types): static
    {
        try {
            parent::set_allowed_types($option, $allowed_types);
        } catch (Undefined_Options_Exception) {
            $this->undefined[$option] = true;
        }
        return $this;
    }
    /**
     * @return $this
     */
    public function add_allowed_types(string $option, string|array $allowed_types): static
    {
        try {
            parent::add_allowed_types($option, $allowed_types);
        } catch (Undefined_Options_Exception) {
            $this->undefined[$option] = true;
        }
        return $this;
    }
    public function resolve(array $options = []): array
    {
        throw new Access_Exception('Resolve options is not supported.');
    }
    public function get_undefined_options(): array
    {
        return array_keys($this->undefined);
    }
}