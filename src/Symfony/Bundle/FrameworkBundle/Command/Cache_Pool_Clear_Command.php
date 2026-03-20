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

use Psr\Cache\Cache_Item_Pool_Interface;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Http_Kernel\Cache_Clearer\Psr6cache_Clearer;
/**
 * Clear cache pools.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[As_Command(name: 'cache:pool:clear', description: 'Clear cache pools')]
final class Cache_Pool_Clear_Command extends Command
{
    /**
     * @param string[]|null $poolNames
     */
    public function __construct(private readonly Psr6cache_Clearer $pool_clearer, private readonly ?array $pool_names = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_definition([new Input_Argument('pools', Input_Argument::IS_ARRAY | Input_Argument::OPTIONAL, 'A list of cache pools or cache pool clearers')])->add_option('all', null, Input_Option::VALUE_NONE, 'Clear all cache pools')->add_option('exclude', null, Input_Option::VALUE_IS_ARRAY | Input_Option::VALUE_REQUIRED, 'A list of cache pools or cache pool clearers to exclude')->set_help(<<<'EOF'
        The <info>%command.name%</info> command clears the given cache pools or cache pool clearers.
        
            %command.full_name% <cache pool or clearer 1> [...<cache pool or clearer N>]
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $kernel = $this->get_application()->get_kernel();
        $pools = [];
        $clearers = [];
        $pool_names = $input->get_argument('pools');
        $excluded_pool_names = $input->get_option('exclude');
        if ($clear_all = $input->get_option('all')) {
            if (!$this->pool_names) {
                throw new InvalidArgumentException('Could not clear all cache pools, try specifying a specific pool or cache clearer.');
            }
            if (!$excluded_pool_names) {
                $io->comment('Clearing all cache pools...');
            }
            $pool_names = $this->pool_names;
        } elseif (!$pool_names) {
            throw new InvalidArgumentException('Either specify at least one pool name, or provide the --all option to clear all pools.');
        }
        $pool_names = array_diff($pool_names, $excluded_pool_names);
        foreach ($pool_names as $id) {
            if ($this->pool_clearer->has_pool($id)) {
                $pools[$id] = $id;
            } elseif (!$clear_all || $kernel->get_container()->has($id)) {
                $pool = $kernel->get_container()->get($id);
                if ($pool instanceof Cache_Item_Pool_Interface) {
                    $pools[$id] = $pool;
                } elseif ($pool instanceof Psr6cache_Clearer) {
                    $clearers[$id] = $pool;
                } else {
                    throw new InvalidArgumentException(\sprintf('"%s" is not a cache pool nor a cache clearer.', $id));
                }
            }
        }
        foreach ($clearers as $id => $clearer) {
            $io->comment(\sprintf('Calling cache clearer: <info>%s</info>', $id));
            $clearer->clear($kernel->get_container()->get_parameter('kernel.cache_dir'));
        }
        $failure = false;
        foreach ($pools as $id => $pool) {
            $io->comment(\sprintf('Clearing cache pool: <info>%s</info>', $id));
            if ($pool instanceof Cache_Item_Pool_Interface) {
                if (!$pool->clear()) {
                    $io->warning(\sprintf('Cache pool "%s" could not be cleared.', $pool));
                    $failure = true;
                }
            } else if (false === $this->pool_clearer->clear_pool($id)) {
                $io->warning(\sprintf('Cache pool "%s" could not be cleared.', $pool));
                $failure = true;
            }
        }
        if ($failure) {
            return 1;
        }
        $io->success('Cache was successfully cleared.');
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if (\is_array($this->pool_names) && $input->must_suggest_argument_values_for('pools')) {
            $suggestions->suggest_values($this->pool_names);
        }
    }
}