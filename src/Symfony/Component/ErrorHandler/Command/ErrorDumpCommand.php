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
namespace Symfony\Component\Error_Handler\Command;

use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Error_Handler\Error_Renderer\Error_Renderer_Interface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Http_Foundation\Response;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Webpack_Encore_Bundle\Asset\Entrypoint_Lookup_Interface;
/**
 * Dump error pages to plain HTML files that can be directly served by a web server.
 *
 * @author Loïck Piera <pyrech@gmail.com>
 */
#[As_Command(name: 'error:dump', description: 'Dump error pages to plain HTML files that can be directly served by a web server')]
final class Error_Dump_Command extends Command
{
    public function __construct(private readonly Filesystem $filesystem, private readonly Error_Renderer_Interface $error_renderer, private readonly ?Entrypoint_Lookup_Interface $entrypoint_lookup = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('path', Input_Argument::REQUIRED, 'Path where to dump the error pages in')->add_argument('status-codes', Input_Argument::IS_ARRAY, 'Status codes to dump error pages for, all of them by default')->add_option('force', 'f', Input_Option::VALUE_NONE, 'Force directory removal before dumping new error pages');
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $path = $input->get_argument('path');
        $io = new Symfony_Style($input, $output);
        $io->title('Dumping error pages');
        $this->dump($io, $path, $input->get_argument('status-codes'), (bool) $input->get_option('force'));
        $io->success(\sprintf('Error pages have been dumped in "%s".', $path));
        return Command::SUCCESS;
    }
    private function dump(Symfony_Style $io, string $path, array $status_codes, bool $force = false): void
    {
        if (!$status_codes) {
            $status_codes = array_filter(array_keys(Response::$status_texts), static fn(int $status_code): bool => $status_code >= 400);
        }
        if ($force || $this->filesystem->exists($path) && $io->confirm(\sprintf('The "%s" directory already exists. Do you want to remove it before dumping the error pages?', $path), false)) {
            $this->filesystem->remove($path);
        }
        foreach ($status_codes as $status_code) {
            // Avoid assets to be included only on the first dumped page
            $this->entrypoint_lookup?->reset();
            $this->filesystem->dump_file($path . \DIRECTORY_SEPARATOR . $status_code . '.html', $this->error_renderer->render(new Http_Exception((int) $status_code))->get_as_string());
        }
    }
}