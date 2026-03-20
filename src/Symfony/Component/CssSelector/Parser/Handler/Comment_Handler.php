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
namespace Symfony\Component\Css_Selector\Parser\Handler;

use Symfony\Component\Css_Selector\Parser\Reader;
use Symfony\Component\Css_Selector\Parser\Token_Stream;
/**
 * CSS selector comment handler.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Comment_Handler implements Handler_Interface
{
    public function handle(Reader $reader, Token_Stream $stream): bool
    {
        if ('/*' !== $reader->get_substring(2)) {
            return false;
        }
        $offset = $reader->get_offset('*/');
        if (false === $offset) {
            $reader->move_to_end();
        } else {
            $reader->move_forward($offset + 2);
        }
        return true;
    }
}