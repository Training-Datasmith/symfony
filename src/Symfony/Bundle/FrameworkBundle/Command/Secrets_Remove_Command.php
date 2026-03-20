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

use Symfony\Bundle\Framework_Bundle\Secrets\Abstract_Vault;
use Symfony\Component\Console\Attribute\As_Command;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\Completion_Input;
use Symfony\Component\Console\Completion\Completion_Suggestions;
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[As_Command(name: 'secrets:remove', description: 'Remove a secret from the vault')]
final class Secrets_Remove_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('name', Input_Argument::REQUIRED, 'The name of the secret')->add_option('local', 'l', Input_Option::VALUE_NONE, 'Update the local vault.')->set_help(<<<'EOF'
        The <info>%command.name%</info> command removes a secret from the vault.
        
            <info>%command.full_name% <name></info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output instanceof Console_Output_Interface ? $output->get_error_output() : $output);
        $vault = $input->get_option('local') ? $this->local_vault : $this->vault;
        if (null === $vault) {
            $io->error('The local vault is disabled.');
            return 1;
        }
        if ($vault->remove($name = $input->get_argument('name'))) {
            $io->success($vault->get_last_message() ?? 'Secret was removed from the vault.');
        } else {
            $io->comment($vault->get_last_message() ?? 'Secret was not found in the vault.');
        }
        if ($this->vault === $vault && null !== $this->local_vault->reveal($name)) {
            $io->comment('Note that this secret is overridden in the local vault.');
        }
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if (!$input->must_suggest_argument_values_for('name')) {
            return;
        }
        $vault_keys = array_keys($this->vault->list(false));
        if ($input->get_option('local')) {
            if (null === $this->local_vault) {
                return;
            }
            $vault_keys = array_intersect($vault_keys, array_keys($this->local_vault->list(false)));
        }
        $suggestions->suggest_values($vault_keys);
    }
}