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
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Dumper\Preloader;
use Symfony\Component\Event_Dispatcher\Event_Dispatcher;
use Symfony\Component\Filesystem\Exception\Io_Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Http_Kernel\Cache_Clearer\Cache_Clearer_Interface;
use Symfony\Component\Http_Kernel\Rebootable_Interface;
/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
#[As_Command(name: 'cache:clear', description: 'Clear the cache')]
class Cache_Clear_Command extends Command
{
    public function __construct(private readonly Cache_Clearer_Interface $cache_clearer, private readonly ?Filesystem $filesystem = new Filesystem())
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Option('no-warmup', '', Input_Option::VALUE_NONE, 'Do not warm up the cache'), new Input_Option('no-optional-warmers', '', Input_Option::VALUE_NONE, 'Skip optional cache warmers (faster)')])->set_help(<<<'EOF'
        The <info>%command.name%</info> command clears and warms up the application cache for a given environment
        and debug mode:
        
          <info>php %command.full_name% --env=dev</info>
          <info>php %command.full_name% --env=prod --no-debug</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $fs = $this->filesystem;
        $io = new Symfony_Style($input, $output);
        $kernel = $this->get_application()->get_kernel();
        $real_cache_dir = $kernel->get_container()->get_parameter('kernel.cache_dir');
        $real_build_dir = $kernel->get_container()->has_parameter('kernel.build_dir') ? $kernel->get_container()->get_parameter('kernel.build_dir') : $real_cache_dir;
        // the old cache dir name must not be longer than the real one to avoid exceeding
        // the maximum length of a directory or file path within it (esp. Windows MAX_PATH)
        $old_cache_dir = substr((string) $real_cache_dir, 0, -1) . (str_ends_with((string) $real_cache_dir, '~') ? '+' : '~');
        $fs->remove($old_cache_dir);
        if (!is_writable($real_cache_dir)) {
            throw new RuntimeException(\sprintf('Unable to write in the "%s" directory.', $real_cache_dir));
        }
        $use_build_dir = $real_build_dir !== $real_cache_dir;
        $old_build_dir = substr((string) $real_build_dir, 0, -1) . (str_ends_with((string) $real_build_dir, '~') ? '+' : '~');
        if ($use_build_dir) {
            $fs->remove($old_build_dir);
            if (!is_writable($real_build_dir)) {
                throw new RuntimeException(\sprintf('Unable to write in the "%s" directory.', $real_build_dir));
            }
            if ($this->is_nfs($real_cache_dir)) {
                $fs->remove($real_cache_dir);
            } else {
                $fs->rename($real_cache_dir, $old_cache_dir);
            }
            $fs->mkdir($real_cache_dir);
        }
        $io->comment(\sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->get_environment(), var_export($kernel->is_debug(), true)));
        if ($use_build_dir) {
            $this->cache_clearer->clear($real_build_dir);
        }
        $this->cache_clearer->clear($real_cache_dir);
        // The current event dispatcher is stale, let's not use it anymore
        $this->get_application()->set_dispatcher(new Event_Dispatcher());
        $container_file = (new \Reflection_Object($kernel->get_container()))->get_file_name();
        $container_dir = basename(\dirname($container_file));
        // the warmup cache dir name must have the same length as the real one
        // to avoid the many problems in serialized resources files
        $warmup_dir = substr($real_build_dir, 0, -1) . (str_ends_with($real_build_dir, '_') ? '-' : '_');
        if ($output->is_verbose() && $fs->exists($warmup_dir)) {
            $io->comment('Clearing outdated warmup directory...');
        }
        $fs->remove($warmup_dir);
        if ($_SERVER['REQUEST_TIME'] <= filemtime($container_file) && filemtime($container_file) <= time()) {
            if ($output->is_verbose()) {
                $io->comment('Cache is fresh.');
            }
            if (!$input->get_option('no-warmup') && !$input->get_option('no-optional-warmers')) {
                if ($output->is_verbose()) {
                    $io->comment('Warming up optional cache...');
                }
                $this->warmup_optionals($real_cache_dir, $real_build_dir, $io);
            }
        } else {
            $fs->mkdir($warmup_dir);
            if (!$input->get_option('no-warmup')) {
                if ($output->is_verbose()) {
                    $io->comment('Warming up cache...');
                }
                $this->warmup($warmup_dir);
                if (!$input->get_option('no-optional-warmers')) {
                    if ($output->is_verbose()) {
                        $io->comment('Warming up optional cache...');
                    }
                    $this->warmup_optionals($use_build_dir ? $real_cache_dir : $warmup_dir, $warmup_dir, $io);
                }
                // fix references to cached files with the real cache directory name
                $search = [$warmup_dir, str_replace('/', '\/', $warmup_dir), str_replace('\\', '\\\\', $warmup_dir)];
                $replace = str_replace('\\', '/', $real_build_dir);
                foreach (Finder::create()->files()->in($warmup_dir) as $file) {
                    $content = str_replace($search, $replace, $this->filesystem->read_file($file), $count);
                    if ($count) {
                        file_put_contents($file, $content);
                    }
                }
            }
            if (!$fs->exists($warmup_dir . '/' . $container_dir)) {
                $fs->rename($real_build_dir . '/' . $container_dir, $warmup_dir . '/' . $container_dir);
                touch($warmup_dir . '/' . $container_dir . '.legacy');
            }
            if ($this->is_nfs($real_build_dir)) {
                $io->note('For better performance, you should move the cache and log directories to a non-shared folder of the VM.');
                $fs->remove($real_build_dir);
            } else {
                $fs->rename($real_build_dir, $old_build_dir);
            }
            $fs->rename($warmup_dir, $real_build_dir);
            if ($output->is_verbose()) {
                $io->comment('Removing old build and cache directory...');
            }
            if ($use_build_dir) {
                try {
                    $fs->remove($old_build_dir);
                } catch (Io_Exception $e) {
                    if ($output->is_verbose()) {
                        $io->warning($e->get_message());
                    }
                }
            }
            try {
                $fs->remove($old_cache_dir);
            } catch (Io_Exception $e) {
                if ($output->is_verbose()) {
                    $io->warning($e->get_message());
                }
            }
        }
        if ($output->is_verbose()) {
            $io->comment('Finished');
        }
        $io->success(\sprintf('Cache for the "%s" environment (debug=%s) was successfully cleared.', $kernel->get_environment(), var_export($kernel->is_debug(), true)));
        return 0;
    }
    private function is_nfs(string $dir): bool
    {
        static $mounts = null;
        if (null === $mounts) {
            $mounts = [];
            if ('/' === \DIRECTORY_SEPARATOR && @is_readable('/proc/mounts') && $files = @file('/proc/mounts')) {
                foreach ($files as $mount) {
                    $mount = \array_slice(explode(' ', $mount), 1, -3);
                    if (!\in_array(array_pop($mount), ['vboxsf', 'nfs'], true)) {
                        continue;
                    }
                    $mounts[] = implode(' ', $mount) . '/';
                }
            }
        }
        foreach ($mounts as $mount) {
            if (str_starts_with($dir, (string) $mount)) {
                return true;
            }
        }
        return false;
    }
    private function warmup(string $warmup_dir): void
    {
        // create a temporary kernel
        $kernel = $this->get_application()->get_kernel();
        if (!$kernel instanceof Rebootable_Interface) {
            throw new \LogicException('Calling "cache:clear" with a kernel that does not implement "Symfony\Component\HttpKernel\RebootableInterface" is not supported.');
        }
        $kernel->reboot($warmup_dir);
    }
    private function warmup_optionals(string $cache_dir, string $warmup_dir, Symfony_Style $io): void
    {
        $kernel = $this->get_application()->get_kernel();
        $warmer = $kernel->get_container()->get('cache_warmer');
        // non optional warmers already ran during container compilation
        $warmer->enable_only_optional_warmers();
        $preload = (array) $warmer->warm_up($cache_dir, $warmup_dir, $io);
        if ($preload && file_exists($preload_file = $warmup_dir . '/' . $kernel->get_container()->get_parameter('kernel.container_class') . '.preload.php')) {
            Preloader::append($preload_file, $preload);
        }
    }
}