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
namespace Symfony\Component\Asset_Mapper\Command;

use Symfony\Component\Asset_Mapper\Compressor\Compressor_Interface;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * Pre-compresses files to serve through a web server.
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[As_Command(name: 'assets:compress', description: 'Pre-compresses files to serve through a web server')]
final class Compress_Assets_Command extends Command
{
    public function __construct(private readonly Compressor_Interface $compressor)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('paths', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'The files to compress')->set_help(<<<'EOT'
        The <info>%command.name%</info> command compresses the given file in Brotli, Zstandard and gzip formats.
        This is especially useful to serve pre-compressed files through a web server.
        
        The existing file will be kept. The compressed files will be created in the same directory.
        The extension of the compression format will be appended to the original file name.
        EOT);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $paths = $input->get_argument('paths');
        foreach ($paths as $path) {
            $this->compressor->compress($path);
        }
        $io->success(\sprintf('File%s compressed successfully.', \count($paths) > 1 ? 's' : ''));
        return Command::SUCCESS;
    }
}