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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Dumper\Preloader;
use Symfony\Component\Http_Kernel\Cache_Warmer\Cache_Warmer_Aggregate;
use Symfony\Component\Http_Kernel\Cache_Warmer\Warmable_Interface;
/**
 * Warmup the cache.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
#[As_Command(name: 'cache:warmup', description: 'Warm up an empty cache')]
class Cache_Warmup_Command extends Command
{
    public function __construct(private readonly Cache_Warmer_Aggregate $cache_warmer)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Option('no-optional-warmers', '', Input_Option::VALUE_NONE, 'Skip optional cache warmers (faster)')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command warms up the cache.
        
        Before running this command, the cache must be empty.
        
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $kernel = $this->get_application()->get_kernel();
        $io->comment(\sprintf('Warming up the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->get_environment(), var_export($kernel->is_debug(), true)));
        if (!$input->get_option('no-optional-warmers')) {
            $this->cache_warmer->enable_optional_warmers();
        }
        $cache_dir = $kernel->get_container()->get_parameter('kernel.cache_dir');
        if ($kernel instanceof Warmable_Interface) {
            $kernel->warm_up($cache_dir);
        }
        $build_dir = $kernel->get_container()->get_parameter('kernel.build_dir');
        $preload = $this->cache_warmer->warm_up($cache_dir, $build_dir);
        if ($preload && $cache_dir === $build_dir && file_exists($preload_file = $build_dir . '/' . $kernel->get_container()->get_parameter('kernel.container_class') . '.preload.php')) {
            Preloader::append($preload_file, $preload);
        }
        $io->success(\sprintf('Cache for the "%s" environment (debug=%s) was successfully warmed.', $kernel->get_environment(), var_export($kernel->is_debug(), true)));
        return 0;
    }
}