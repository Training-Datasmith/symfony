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
namespace Symfony\Component\Console\Completion\Output;

use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * Transforms the {@see CompletionSuggestions} object into output readable by the shell completion.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
interface Completion_Output_Interface
{
    public function write(Completion_Suggestions $suggestions, Output_Interface $output): void;
}