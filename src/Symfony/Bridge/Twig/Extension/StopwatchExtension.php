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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\Token_Parser\Stopwatch_Token_Parser;
use Symfony\Component\Stopwatch\Stopwatch;
use Twig\Extension\Abstract_Extension;
use Twig\Token_Parser\Token_Parser_Interface;
/**
 * Twig extension for the stopwatch helper.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
final class Stopwatch_Extension extends Abstract_Extension
{
    public function __construct(private readonly ?Stopwatch $stopwatch = null, private readonly bool $enabled = true)
    {
    }
    public function get_stopwatch(): Stopwatch
    {
        return $this->stopwatch;
    }
    /**
     * @return TokenParserInterface[]
     */
    public function get_token_parsers(): array
    {
        return [
            /*
             * {% stopwatch foo %}
             * Some stuff which will be recorded on the timeline
             * {% endstopwatch %}
             */
            new Stopwatch_Token_Parser(null !== $this->stopwatch && $this->enabled),
        ];
    }
}