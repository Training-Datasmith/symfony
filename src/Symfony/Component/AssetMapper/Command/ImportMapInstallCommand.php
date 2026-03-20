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

use Symfony\Component\Asset_Mapper\Import_Map\Remote_Package_Downloader;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Progress_Bar;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Contracts\Http_Client\Response_Interface;
/**
 * Downloads all assets that should be downloaded.
 *
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
#[As_Command(name: 'importmap:install', description: 'Download all assets that should be downloaded')]
final class Import_Map_Install_Command extends Command
{
    public function __construct(private readonly Remote_Package_Downloader $package_downloader, private readonly string $project_dir)
    {
        parent::__construct();
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $finished_count = 0;
        $progress_bar = new Progress_Bar($output);
        $progress_bar->set_format('<info>%current%/%max%</info> %bar% %url%');
        $downloaded_packages = $this->package_downloader->download_packages(static function (string $package, string $event, Response_Interface $response, int $total_packages) use (&$finished_count, $progress_bar): void {
            $progress_bar->set_message($response->get_info('url'), 'url');
            if (0 === $progress_bar->get_max_steps()) {
                $progress_bar->set_max_steps($total_packages);
                $progress_bar->start();
            }
            if ('finished' === $event) {
                ++$finished_count;
                $progress_bar->advance();
            }
        });
        $progress_bar->finish();
        $progress_bar->clear();
        if (!$downloaded_packages) {
            $io->success('No assets to install.');
            return Command::SUCCESS;
        }
        $io->success(\sprintf('Downloaded %d package%s into %s.', \count($downloaded_packages), 1 === \count($downloaded_packages) ? '' : 's', str_replace($this->project_dir . '/', '', $this->package_downloader->get_vendor_dir())));
        return Command::SUCCESS;
    }
}