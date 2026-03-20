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
namespace Symfony\Component\Html_Sanitizer;

use Symfony\Component\Html_Sanitizer\Reference\W3c_Reference;
use Symfony\Component\Html_Sanitizer\Visitor\Attribute_Sanitizer\Attribute_Sanitizer_Interface;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
class Html_Sanitizer_Config
{
    private Html_Sanitizer_Action $default_action = Html_Sanitizer_Action::Drop;
    /**
     * Elements that should be removed.
     *
     * @var array<string, true>
     */
    private array $dropped_elements = [];
    /**
     * Elements that should be removed but their children should be retained.
     *
     * @var array<string, true>
     */
    private array $blocked_elements = [];
    /**
     * Elements that should be retained, with their allowed attributes.
     *
     * @var array<string, array<string, true>>
     */
    private array $allowed_elements = [];
    /**
     * Attributes that should always be added to certain elements.
     *
     * @var array<string, array<string, string>>
     */
    private array $forced_attributes = [];
    /**
     * Links schemes that should be retained, other being dropped.
     *
     * @var list<string>
     */
    private array $allowed_link_schemes = ['http', 'https', 'mailto', 'tel'];
    /**
     * Links hosts that should be retained (by default, all hosts are allowed).
     *
     * @var list<string>|null
     */
    private ?array $allowed_link_hosts = null;
    /**
     * Should the sanitizer allow relative links (by default, they are dropped).
     */
    private bool $allow_relative_links = false;
    /**
     * Image/Audio/Video schemes that should be retained, other being dropped.
     *
     * @var list<string>
     */
    private array $allowed_media_schemes = ['http', 'https', 'data'];
    /**
     * Image/Audio/Video hosts that should be retained (by default, all hosts are allowed).
     *
     * @var list<string>|null
     */
    private ?array $allowed_media_hosts = null;
    /**
     * Should the sanitizer allow relative media URL (by default, they are dropped).
     */
    private bool $allow_relative_medias = false;
    /**
     * Should the URL in the sanitized document be transformed to HTTPS if they are using HTTP.
     */
    private bool $force_https_urls = false;
    /**
     * Sanitizers that should be applied to specific attributes in addition to standard sanitization.
     *
     * @var list<AttributeSanitizerInterface>
     */
    private array $attribute_sanitizers;
    private int $max_input_length = 20000;
    public function __construct()
    {
        $this->attribute_sanitizers = [new Visitor\Attribute_Sanitizer\Url_Attribute_Sanitizer()];
    }
    /**
     * Sets the default action for elements which are not otherwise specifically allowed or blocked.
     *
     * Note that a default action of Allow will allow all tags but they will not have any attributes.
     */
    public function default_action(Html_Sanitizer_Action $action): static
    {
        $clone = clone $this;
        $clone->default_action = $action;
        return $clone;
    }
    /**
     * Allows all static elements and attributes from the W3C Sanitizer API standard.
     *
     * All scripts will be removed but the output may still contain other dangerous
     * behaviors like CSS injection (click-jacking), CSS expressions, ...
     */
    public function allow_static_elements(): static
    {
        $elements = array_merge(array_keys(W3c_Reference::HEAD_ELEMENTS), array_keys(W3c_Reference::BODY_ELEMENTS));
        $clone = clone $this;
        foreach ($elements as $element) {
            $clone = $clone->allow_element($element, '*');
        }
        return $clone;
    }
    /**
     * Allows "safe" elements and attributes.
     *
     * All scripts will be removed, as well as other dangerous behaviors like CSS injection.
     */
    public function allow_safe_elements(): static
    {
        $attributes = [];
        foreach (W3c_Reference::ATTRIBUTES as $attribute => $is_safe) {
            if ($is_safe) {
                $attributes[] = $attribute;
            }
        }
        $clone = clone $this;
        foreach (W3c_Reference::HEAD_ELEMENTS as $element => $is_safe) {
            if ($is_safe) {
                $clone = $clone->allow_element($element, $attributes);
            }
        }
        foreach (W3c_Reference::BODY_ELEMENTS as $element => $is_safe) {
            if ($is_safe) {
                $clone = $clone->allow_element($element, $attributes);
            }
        }
        return $clone;
    }
    /**
     * Allows only a given list of schemes to be used in links href attributes.
     *
     * All other schemes will be dropped.
     *
     * @param list<string> $allowLinkSchemes
     */
    public function allow_link_schemes(array $allow_link_schemes): static
    {
        $clone = clone $this;
        $clone->allowed_link_schemes = $allow_link_schemes;
        return $clone;
    }
    /**
     * Allows only a given list of hosts to be used in links href attributes.
     *
     * All other hosts will be dropped. By default all hosts are allowed
     * ($allowedLinkHosts = null).
     *
     * @param list<string>|null $allowLinkHosts
     */
    public function allow_link_hosts(?array $allow_link_hosts): static
    {
        $clone = clone $this;
        $clone->allowed_link_hosts = $allow_link_hosts;
        return $clone;
    }
    /**
     * Allows relative URLs to be used in links href attributes.
     */
    public function allow_relative_links(bool $allow_relative_links = true): static
    {
        $clone = clone $this;
        $clone->allow_relative_links = $allow_relative_links;
        return $clone;
    }
    /**
     * Allows only a given list of schemes to be used in media source attributes (img, audio, video, ...).
     *
     * All other schemes will be dropped.
     *
     * @param list<string> $allowMediaSchemes
     */
    public function allow_media_schemes(array $allow_media_schemes): static
    {
        $clone = clone $this;
        $clone->allowed_media_schemes = $allow_media_schemes;
        return $clone;
    }
    /**
     * Allows only a given list of hosts to be used in media source attributes (img, audio, video, ...).
     *
     * All other hosts will be dropped. By default all hosts are allowed
     * ($allowMediaHosts = null).
     *
     * @param list<string>|null $allowMediaHosts
     */
    public function allow_media_hosts(?array $allow_media_hosts): static
    {
        $clone = clone $this;
        $clone->allowed_media_hosts = $allow_media_hosts;
        return $clone;
    }
    /**
     * Allows relative URLs to be used in media source attributes (img, audio, video, ...).
     */
    public function allow_relative_medias(bool $allow_relative_medias = true): static
    {
        $clone = clone $this;
        $clone->allow_relative_medias = $allow_relative_medias;
        return $clone;
    }
    /**
     * Transforms URLs using the HTTP scheme to use the HTTPS scheme instead.
     */
    public function force_https_urls(bool $force_https_urls = true): static
    {
        $clone = clone $this;
        $clone->force_https_urls = $force_https_urls;
        return $clone;
    }
    /**
     * Configures the given element as allowed.
     *
     * Allowed elements are elements the sanitizer should retain from the input.
     *
     * A list of allowed attributes for this element can be passed as a second argument.
     * Passing "*" will allow all standard attributes on this element. By default, no
     * attributes are allowed on the element.
     *
     * @param list<string>|string $allowedAttributes
     */
    public function allow_element(string $element, array|string $allowed_attributes = []): static
    {
        $clone = clone $this;
        // Unblock/undrop the element if necessary
        unset($clone->blocked_elements[$element], $clone->dropped_elements[$element]);
        $clone->allowed_elements[$element] = [];
        $attrs = '*' === $allowed_attributes ? array_keys(W3c_Reference::ATTRIBUTES) : (array) $allowed_attributes;
        foreach ($attrs as $allowed_attr) {
            $clone->allowed_elements[$element][$allowed_attr] = true;
        }
        return $clone;
    }
    /**
     * Configures the given element as blocked.
     *
     * Blocked elements are elements the sanitizer should remove from the input, but retain
     * their children.
     */
    public function block_element(string $element): static
    {
        $clone = clone $this;
        // Disallow/undrop the element if necessary
        unset($clone->allowed_elements[$element], $clone->dropped_elements[$element]);
        $clone->blocked_elements[$element] = true;
        return $clone;
    }
    /**
     * Configures the given element as dropped.
     *
     * Dropped elements are elements the sanitizer should remove from the input, including
     * their children.
     *
     * Note: when using an empty configuration, all unknown elements are dropped
     * automatically. This method let you drop elements that were allowed earlier
     * in the configuration, or explicitly drop some if you changed the default action.
     */
    public function drop_element(string $element): static
    {
        $clone = clone $this;
        unset($clone->allowed_elements[$element], $clone->blocked_elements[$element]);
        $clone->dropped_elements[$element] = true;
        return $clone;
    }
    /**
     * Configures the given attribute as allowed.
     *
     * Allowed attributes are attributes the sanitizer should retain from the input.
     *
     * A list of allowed elements for this attribute can be passed as a second argument.
     * Passing "*" will allow all currently allowed elements to use this attribute.
     *
     * @param list<string>|string $allowedElements
     */
    public function allow_attribute(string $attribute, array|string $allowed_elements): static
    {
        $clone = clone $this;
        $allowed_elements = '*' === $allowed_elements ? array_keys($clone->allowed_elements) : (array) $allowed_elements;
        // For each configured element ...
        foreach ($clone->allowed_elements as $element => $attrs) {
            if (\in_array($element, $allowed_elements, true)) {
                // ... if the attribute should be allowed, add it
                $clone->allowed_elements[$element][$attribute] = true;
            } else {
                // ... if the attribute should not be allowed, remove it
                unset($clone->allowed_elements[$element][$attribute]);
            }
        }
        return $clone;
    }
    /**
     * Configures the given attribute as dropped.
     *
     * Dropped attributes are attributes the sanitizer should remove from the input.
     *
     * A list of elements on which to drop this attribute can be passed as a second argument.
     * Passing "*" will drop this attribute from all currently allowed elements.
     *
     * Note: when using an empty configuration, all unknown attributes are dropped
     * automatically. This method let you drop attributes that were allowed earlier
     * in the configuration.
     *
     * @param list<string>|string $droppedElements
     */
    public function drop_attribute(string $attribute, array|string $dropped_elements): static
    {
        $clone = clone $this;
        $dropped_elements = '*' === $dropped_elements ? array_keys($clone->allowed_elements) : (array) $dropped_elements;
        foreach ($dropped_elements as $element) {
            if (isset($clone->allowed_elements[$element][$attribute])) {
                unset($clone->allowed_elements[$element][$attribute]);
            }
        }
        return $clone;
    }
    /**
     * Forcefully set the value of a given attribute on a given element.
     *
     * The attribute will be created on the nodes if it didn't exist.
     */
    public function force_attribute(string $element, string $attribute, string $value): static
    {
        $clone = clone $this;
        $clone->forced_attributes[$element][$attribute] = $value;
        return $clone;
    }
    /**
     * Registers a custom attribute sanitizer.
     */
    public function with_attribute_sanitizer(Attribute_Sanitizer_Interface $sanitizer): static
    {
        $clone = clone $this;
        $clone->attribute_sanitizers[] = $sanitizer;
        return $clone;
    }
    /**
     * Unregisters a custom attribute sanitizer.
     */
    public function without_attribute_sanitizer(Attribute_Sanitizer_Interface $sanitizer): static
    {
        $clone = clone $this;
        $clone->attribute_sanitizers = array_values(array_filter($this->attribute_sanitizers, static fn(\Symfony\Component\Html_Sanitizer\Visitor\Attribute_Sanitizer\Attribute_Sanitizer_Interface $current): bool => $current !== $sanitizer));
        return $clone;
    }
    /**
     * @param int $maxInputLength The maximum length of the input string in bytes
     *                            -1 means no limit
     */
    public function with_max_input_length(int $max_input_length): static
    {
        if ($max_input_length < -1) {
            throw new \InvalidArgumentException(\sprintf('The maximum input length must be greater than -1, "%d" given.', $max_input_length));
        }
        $clone = clone $this;
        $clone->max_input_length = $max_input_length;
        return $clone;
    }
    public function get_max_input_length(): int
    {
        return $this->max_input_length;
    }
    public function get_default_action(): Html_Sanitizer_Action
    {
        return $this->default_action;
    }
    /**
     * @return array<string, array<string, true>>
     */
    public function get_allowed_elements(): array
    {
        return $this->allowed_elements;
    }
    /**
     * @return array<string, true>
     */
    public function get_blocked_elements(): array
    {
        return $this->blocked_elements;
    }
    /**
     * @return array<string, true>
     */
    public function get_dropped_elements(): array
    {
        return $this->dropped_elements;
    }
    /**
     * @return array<string, array<string, string>>
     */
    public function get_forced_attributes(): array
    {
        return $this->forced_attributes;
    }
    /**
     * @return list<string>
     */
    public function get_allowed_link_schemes(): array
    {
        return $this->allowed_link_schemes;
    }
    /**
     * @return list<string>|null
     */
    public function get_allowed_link_hosts(): ?array
    {
        return $this->allowed_link_hosts;
    }
    public function get_allow_relative_links(): bool
    {
        return $this->allow_relative_links;
    }
    /**
     * @return list<string>
     */
    public function get_allowed_media_schemes(): array
    {
        return $this->allowed_media_schemes;
    }
    /**
     * @return list<string>|null
     */
    public function get_allowed_media_hosts(): ?array
    {
        return $this->allowed_media_hosts;
    }
    public function get_allow_relative_medias(): bool
    {
        return $this->allow_relative_medias;
    }
    public function get_force_https_urls(): bool
    {
        return $this->force_https_urls;
    }
    /**
     * @return list<AttributeSanitizerInterface>
     */
    public function get_attribute_sanitizers(): array
    {
        return $this->attribute_sanitizers;
    }
}