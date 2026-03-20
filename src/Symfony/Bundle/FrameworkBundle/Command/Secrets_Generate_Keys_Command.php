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
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[As_Command(name: 'secrets:generate-keys', description: 'Generate new encryption keys')]
final class Secrets_Generate_Keys_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_option('local', 'l', Input_Option::VALUE_NONE, 'Update the local vault.')->add_option('rotate', 'r', Input_Option::VALUE_NONE, 'Re-encrypt existing secrets with the newly generated keys.')->set_help(<<<'EOF'
        The <info>%command.name%</info> command generates a new encryption key.
        
            <info>%command.full_name%</info>
        
        If encryption keys already exist, the command must be called with
        the <info>--rotate</info> option in order to override those keys and re-encrypt
        existing secrets.
        
            <info>%command.full_name% --rotate</info>
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
        if (!$input->get_option('rotate')) {
            if ($vault->generate_keys()) {
                $io->success($vault->get_last_message());
                if ($this->vault === $vault) {
                    $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
                }
                return 0;
            }
            $io->warning($vault->get_last_message());
            return 1;
        }
        $secrets = [];
        foreach ($vault->list(true) as $name => $value) {
            if (null === $value) {
                $io->error($vault->get_last_message());
                return 1;
            }
            $secrets[$name] = $value;
        }
        if (!$vault->generate_keys(true)) {
            $io->warning($vault->get_last_message());
            return 1;
        }
        $io->success($vault->get_last_message());
        if ($secrets) {
            foreach ($secrets as $name => $value) {
                $vault->seal($name, $value);
            }
            $io->comment('Existing secrets have been rotated to the new keys.');
        }
        if ($this->vault === $vault) {
            $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
        }
        return 0;
    }
}