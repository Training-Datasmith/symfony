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
use Symfony\Component\Html_Sanitizer\Text_Sanitizer\Url_Sanitizer;
/**
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class Url_Attribute_Sanitizer implements Attribute_Sanitizer_Interface
{
    public function get_supported_elements(): ?array
    {
        // Check all elements for URL attributes
        return null;
    }
    public function get_supported_attributes(): array
    {
        return ['src', 'href', 'lowsrc', 'background', 'ping'];
    }
    public function sanitize_attribute(string $element, string $attribute, string $value, Html_Sanitizer_Config $config): ?string
    {
        if ('a' === $element) {
            return Url_Sanitizer::sanitize($value, $config->get_allowed_link_schemes(), $config->get_force_https_urls(), $config->get_allowed_link_hosts(), $config->get_allow_relative_links());
        }
        return Url_Sanitizer::sanitize($value, $config->get_allowed_media_schemes(), $config->get_force_https_urls(), $config->get_allowed_media_hosts(), $config->get_allow_relative_medias());
    }
}