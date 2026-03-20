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
namespace Symfony\Component\Dependency_Injection\Exception;

use Psr\Container\Not_Found_Exception_Interface;
/**
 * This exception is thrown when a non-existent parameter is used.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Parameter_Not_Found_Exception extends InvalidArgumentException implements Not_Found_Exception_Interface
{
    /**
     * @param string          $key                  The requested parameter key
     * @param string|null     $sourceId             The service id that references the non-existent parameter
     * @param string|null     $sourceKey            The parameter key that references the non-existent parameter
     * @param \Throwable|null $previous             The previous exception
     * @param string[]        $alternatives         Some parameter name alternatives
     * @param string|null     $nonNestedAlternative The alternative parameter name when the user expected dot notation for nested parameters
     */
    public function __construct(private string $key, private ?string $source_id = null, private ?string $source_key = null, ?\Throwable $previous = null, private readonly array $alternatives = [], private readonly ?string $non_nested_alternative = null, private ?string $source_extension_name = null, private ?string $extra_message = null)
    {
        parent::__construct('', 0, $previous);
        $this->update_repr();
    }
    public function update_repr(): void
    {
        if (null !== $this->source_id) {
            $this->message = \sprintf('The service "%s" has a dependency on a non-existent parameter "%s".', $this->source_id, $this->key);
        } elseif (null !== $this->source_key) {
            $this->message = \sprintf('The parameter "%s" has a dependency on a non-existent parameter "%s".', $this->source_key, $this->key);
        } elseif (null !== $this->source_extension_name) {
            $this->message = \sprintf('You have requested a non-existent parameter "%s" while loading extension "%s".', $this->key, $this->source_extension_name);
        } elseif ('.' === ($this->key[0] ?? '')) {
            $this->message = \sprintf('Parameter "%s" not found. It was probably deleted during the compilation of the container.', $this->key);
        } else {
            $this->message = \sprintf('You have requested a non-existent parameter "%s".', $this->key);
        }
        if ($this->alternatives) {
            if (1 === \count($this->alternatives)) {
                $this->message .= ' Did you mean this: "';
            } else {
                $this->message .= ' Did you mean one of these: "';
            }
            $this->message .= implode('", "', $this->alternatives) . '"?';
        } elseif (null !== $this->non_nested_alternative) {
            $this->message .= ' You cannot access nested array items, do you want to inject "' . $this->non_nested_alternative . '" instead?';
        }
        if ($this->extra_message) {
            $this->message .= ' ' . $this->extra_message;
        }
    }
    public function get_key(): string
    {
        return $this->key;
    }
    public function get_source_id(): ?string
    {
        return $this->source_id;
    }
    public function get_source_key(): ?string
    {
        return $this->source_key;
    }
    public function set_source_id(?string $source_id): void
    {
        $this->source_id = $source_id;
        $this->update_repr();
    }
    public function set_source_key(?string $source_key): void
    {
        $this->source_key = $source_key;
        $this->update_repr();
    }
    public function set_source_extension_name(?string $source_extension_name): void
    {
        $this->source_extension_name = $source_extension_name;
        $this->update_repr();
    }
    public function get_extra_message(): ?string
    {
        return $this->extra_message;
    }
    public function set_extra_message(?string $extra_message): void
    {
        $this->extra_message = $extra_message;
        $this->update_repr();
    }
}