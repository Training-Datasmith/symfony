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
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
use Symfony\Component\Dependency_Injection\Exception\Service_Not_Found_Exception;
use Symfony\Contracts\Cache\Tag_Aware_Cache_Interface;
use Symfony\Contracts\Service\Service_Provider_Interface;
/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
#[As_Command(name: 'cache:pool:invalidate-tags', description: 'Invalidate cache tags for all or a specific pool')]
final class Cache_Pool_Invalidate_Tags_Command extends Command
{
    private readonly array $pool_names;
    public function __construct(private readonly Service_Provider_Interface $pools)
    {
        parent::__construct();
        $this->pool_names = array_keys($pools->get_provided_services());
    }
    protected function configure(): void
    {
        $this->add_argument('tags', Input_Argument::IS_ARRAY | Input_Argument::REQUIRED, 'The tags to invalidate')->add_option('pool', 'p', Input_Option::VALUE_REQUIRED | Input_Option::VALUE_IS_ARRAY, 'The pools to invalidate on')->set_help(<<<'EOF'
        The <info>%command.name%</info> command invalidates tags from taggable pools. By default, all pools
        have the passed tags invalidated. Pass <info>--pool=my_pool</info> to invalidate tags on a specific pool.
        
          php %command.full_name% tag1 tag2
          php %command.full_name% tag1 tag2 --pool=cache2 --pool=cache1
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output);
        $pools = $input->get_option('pool') ?: $this->pool_names;
        $tags = $input->get_argument('tags');
        $tag_list = implode(', ', $tags);
        $errors = false;
        foreach ($pools as $name) {
            $io->comment(\sprintf('Invalidating tag(s): <info>%s</info> from pool <comment>%s</comment>.', $tag_list, $name));
            try {
                $pool = $this->pools->get($name);
            } catch (Service_Not_Found_Exception) {
                $io->error(\sprintf('Pool "%s" not found.', $name));
                $errors = true;
                continue;
            }
            if (!$pool instanceof Tag_Aware_Cache_Interface) {
                $io->error(\sprintf('Pool "%s" is not taggable.', $name));
                $errors = true;
                continue;
            }
            if (!$pool->invalidate_tags($tags)) {
                $io->error(\sprintf('Cache tag(s) "%s" could not be invalidated for pool "%s".', $tag_list, $name));
                $errors = true;
            }
        }
        if ($errors) {
            $io->error('Done but with errors.');
            return 1;
        }
        $io->success('Successfully invalidated cache tags.');
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_option_values_for('pool')) {
            $suggestions->suggest_values($this->pool_names);
        }
    }
}