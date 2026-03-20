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
namespace Symfony\Component\Html_Sanitizer\Visitor\Attribute_Sanitizer;

use Symfony\Component\Html_Sanitizer\Html_Sanitizer_Config;
/**
 * Implements attribute-specific sanitization logic.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface Attribute_Sanitizer_Interface
{
    /**
     * Returns the list of element names supported, or null to support all elements.
     *
     * @return list<string>|null
     */
    public function get_supported_elements(): ?array;
    /**
     * Returns the list of attributes names supported, or null to support all attributes.
     *
     * @return list<string>|null
     */
    public function get_supported_attributes(): ?array;
    /**
     * Returns the sanitized value of a given attribute for the given element.
     */
    public function sanitize_attribute(string $element, string $attribute, string $value, Html_Sanitizer_Config $config): ?string;
}