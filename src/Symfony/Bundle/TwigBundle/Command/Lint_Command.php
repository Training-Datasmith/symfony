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
namespace Symfony\Bundle\Twig_Bundle\Command;

use Symfony\Bridge\Twig\Command\Lint_Command as BaseLintCommand;
use Symfony\Component\Console\Attribute\As_Command;
/**
 * Command that will validate your template syntax and output encountered errors.
 *
 * @author Marc Weistroff <marc.weistroff@sensiolabs.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
#[As_Command(name: 'lint:twig', description: 'Lint a Twig template and outputs encountered errors')]
final class Lint_Command extends Base_Lint_Command
{
    protected function configure(): void
    {
        parent::configure();
        $this->set_help($this->get_help() . <<<'EOF'
        
        Or all template files in a bundle:
        
          <info>php %command.full_name% @AcmeDemoBundle</info>
        
        EOF);
    }
    protected function find_files(string $filename): iterable
    {
        if (str_starts_with($filename, '@')) {
            $filename = $this->get_application()->get_kernel()->locate_resource($filename);
        }
        return parent::find_files($filename);
    }
}