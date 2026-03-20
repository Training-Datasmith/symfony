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
namespace Symfony\Bundle\Framework_Bundle\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Translation\Command\Xliff_Lint_Command as BaseLintCommand;
/**
 * Validates XLIFF files syntax and outputs encountered errors.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Robin Chalas <robin.chalas@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @final
 */
#[As_Command(name: 'lint:xliff', description: 'Lint an XLIFF file and outputs encountered errors')]
class Xliff_Lint_Command extends Base_Lint_Command
{
    public function __construct()
    {
        $directory_iterator_provider = function ($directory, $default) {
            if (!is_dir($directory)) {
                $directory = $this->get_application()->get_kernel()->locate_resource($directory);
            }
            return $default($directory);
        };
        $is_readable_provider = static fn($file_or_directory, $default): bool => str_starts_with((string) $file_or_directory, '@') || $default($file_or_directory);
        parent::__construct(null, $directory_iterator_provider, $is_readable_provider);
    }
    protected function configure(): void
    {
        parent::configure();
        $this->set_help($this->get_help() . <<<'EOF'
        
        Or find all files in a bundle:
        
          <info>php %command.full_name% @AcmeDemoBundle</info>
        
        EOF);
    }
}