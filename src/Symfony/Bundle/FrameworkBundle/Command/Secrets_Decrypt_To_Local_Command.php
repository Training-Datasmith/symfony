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
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Input\Input_Option;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[As_Command(name: 'secrets:decrypt-to-local', description: 'Decrypt all secrets and stores them in the local vault')]
final class Secrets_Decrypt_To_Local_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_option('force', 'f', Input_Option::VALUE_NONE, 'Force overriding of secrets that already exist in the local vault')->set_help(<<<'EOF'
        The <info>%command.name%</info> command decrypts all secrets and copies them in the local vault.
        
            <info>%command.full_name%</info>
        
        When the <info>--force</info> option is provided, secrets that already exist in the local vault are overridden.
        
            <info>%command.full_name% --force</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output instanceof Console_Output_Interface ? $output->get_error_output() : $output);
        if (null === $this->local_vault) {
            $io->error('The local vault is disabled.');
            return 1;
        }
        $secrets = $this->vault->list(true);
        $io->comment(\sprintf('%d secret%s found in the vault.', \count($secrets), 1 !== \count($secrets) ? 's' : ''));
        $skipped = 0;
        if (!$input->get_option('force')) {
            foreach ($this->local_vault->list() as $k => $v) {
                if (isset($secrets[$k])) {
                    ++$skipped;
                    unset($secrets[$k]);
                }
            }
        }
        if ($skipped > 0) {
            $io->warning([\sprintf('%d secret%s already overridden in the local vault and will be skipped.', $skipped, 1 !== $skipped ? 's are' : ' is'), 'Use the --force flag to override these.']);
        }
        $had_errors = false;
        foreach ($secrets as $k => $v) {
            if (null === $v) {
                $io->error($this->vault->get_last_message() ?? \sprintf('Secret "%s" has been skipped as there was an error reading it.', $k));
                $had_errors = true;
                continue;
            }
            $this->local_vault->seal($k, $v);
            $io->note($this->local_vault->get_last_message());
        }
        if ($had_errors) {
            return 1;
        }
        return 0;
    }
}