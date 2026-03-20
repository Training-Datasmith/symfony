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
namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Descriptor\Descriptor_Interface;
use Symfony\Component\Console\Descriptor\Json_Descriptor;
use Symfony\Component\Console\Descriptor\Markdown_Descriptor;
use Symfony\Component\Console\Descriptor\Re_Structured_Text_Descriptor;
use Symfony\Component\Console\Descriptor\Text_Descriptor;
use Symfony\Component\Console\Descriptor\Xml_Descriptor;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * This class adds helper method to describe objects in various formats.
 *
 * @author Jean-François Simon <contact@jfsimon.fr>
 */
class Descriptor_Helper extends Helper
{
    /**
     * @var DescriptorInterface[]
     */
    private array $descriptors = [];
    public function __construct()
    {
        $this->register('txt', new Text_Descriptor())->register('xml', new Xml_Descriptor())->register('json', new Json_Descriptor())->register('md', new Markdown_Descriptor())->register('rst', new Re_Structured_Text_Descriptor());
    }
    /**
     * Describes an object if supported.
     *
     * Available options are:
     * * format: string, the output format name
     * * raw_text: boolean, sets output type as raw
     *
     * @throws InvalidArgumentException when the given format is not supported
     */
    public function describe(Output_Interface $output, ?object $object, array $options = []): void
    {
        $options = array_merge(['raw_text' => false, 'format' => 'txt'], $options);
        if (!isset($this->descriptors[$options['format']])) {
            throw new InvalidArgumentException(\sprintf('Unsupported format "%s".', $options['format']));
        }
        $descriptor = $this->descriptors[$options['format']];
        $descriptor->describe($output, $object, $options);
    }
    /**
     * Registers a descriptor.
     *
     * @return $this
     */
    public function register(string $format, Descriptor_Interface $descriptor): static
    {
        $this->descriptors[$format] = $descriptor;
        return $this;
    }
    public function get_name(): string
    {
        return 'descriptor';
    }
    public function get_formats(): array
    {
        return array_keys($this->descriptors);
    }
}