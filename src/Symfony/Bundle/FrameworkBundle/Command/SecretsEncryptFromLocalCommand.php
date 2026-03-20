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
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[As_Command(name: 'secrets:encrypt-from-local', description: 'Encrypt all local secrets to the vault')]
final class Secrets_Encrypt_From_Local_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->set_help(<<<'EOF'
        The <info>%command.name%</info> command encrypts all locally overridden secrets to the vault.
        
            <info>%command.full_name%</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output instanceof Console_Output_Interface ? $output->get_error_output() : $output);
        if (null === $this->local_vault) {
            $io->error('The local vault is disabled.');
            return 1;
        }
        foreach ($this->vault->list(true) as $name => $value) {
            if (null === $local_value = $this->local_vault->reveal($name)) {
                continue;
            }
            if ($value !== $local_value) {
                $this->vault->seal($name, $local_value);
                $io->note($this->vault->get_last_message());
            }
        }
        return 0;
    }
}