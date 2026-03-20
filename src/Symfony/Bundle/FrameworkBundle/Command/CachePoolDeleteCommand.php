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
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Http_Kernel\Cache_Clearer\Psr6cache_Clearer;
/**
 * Delete an item from a cache pool.
 *
 * @author Pierre du Plessis <pdples@gmail.com>
 */
#[As_Command(name: 'cache:pool:delete', description: 'Delete an item from a cache pool')]
final class Cache_Pool_Delete_Command extends Command
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
        $this->set_definition([new Input_Argument('pool', Input_Argument::REQUIRED, 'The cache pool from which to delete an item'), new Input_Argument('key', Input_Argument::REQUIRED, 'The cache key to delete from the pool')])->set_help(<<<'EOF'
        The <info>%command.name%</info> deletes an item from a given cache pool.
        
            %command.full_name% <pool> <key>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $pool = $input->get_argument('pool');
        $key = $input->get_argument('key');
        $cache_pool = $this->pool_clearer->get_pool($pool);
        if (!$cache_pool->has_item($key)) {
            $io->note(\sprintf('Cache item "%s" does not exist in cache pool "%s".', $key, $pool));
            return 0;
        }
        if (!$cache_pool->delete_item($key)) {
            throw new \Exception(\sprintf('Cache item "%s" could not be deleted.', $key));
        }
        $io->success(\sprintf('Cache item "%s" was successfully deleted.', $key));
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if (\is_array($this->pool_names) && $input->must_suggest_argument_values_for('pool')) {
            $suggestions->suggest_values($this->pool_names);
        }
    }
}