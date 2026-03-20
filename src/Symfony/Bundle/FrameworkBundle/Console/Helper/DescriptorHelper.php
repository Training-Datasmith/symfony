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
namespace Symfony\Bundle\Framework_Bundle\Console\Helper;

use Symfony\Bundle\Framework_Bundle\Console\Descriptor\Json_Descriptor;
use Symfony\Bundle\Framework_Bundle\Console\Descriptor\Markdown_Descriptor;
use Symfony\Bundle\Framework_Bundle\Console\Descriptor\Text_Descriptor;
use Symfony\Bundle\Framework_Bundle\Console\Descriptor\Xml_Descriptor;
use Symfony\Component\Console\Helper\Descriptor_Helper as BaseDescriptorHelper;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Descriptor_Helper extends Base_Descriptor_Helper
{
    public function __construct(?File_Link_Formatter $file_link_formatter = null)
    {
        $this->register('txt', new Text_Descriptor($file_link_formatter))->register('xml', new Xml_Descriptor())->register('json', new Json_Descriptor())->register('md', new Markdown_Descriptor());
    }
}