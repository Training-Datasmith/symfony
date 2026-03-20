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
namespace Symfony\Component\Form\Console\Helper;

use Symfony\Component\Console\Helper\Descriptor_Helper as BaseDescriptorHelper;
use Symfony\Component\Error_Handler\Error_Renderer\File_Link_Formatter;
use Symfony\Component\Form\Console\Descriptor\Json_Descriptor;
use Symfony\Component\Form\Console\Descriptor\Text_Descriptor;
/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 *
 * @internal
 */
class Descriptor_Helper extends Base_Descriptor_Helper
{
    public function __construct(?File_Link_Formatter $file_link_formatter = null)
    {
        $this->register('txt', new Text_Descriptor($file_link_formatter))->register('json', new Json_Descriptor());
    }
}