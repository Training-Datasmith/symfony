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
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[As_Command(name: 'secrets:set', description: 'Set a secret in the vault')]
final class Secrets_Set_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('name', Input_Argument::REQUIRED, 'The name of the secret')->add_argument('file', Input_Argument::OPTIONAL, 'A file where to read the secret from or "-" for reading from STDIN')->add_option('local', 'l', Input_Option::VALUE_NONE, 'Update the local vault.')->add_option('random', 'r', Input_Option::VALUE_OPTIONAL, 'Generate a random value.', false)->set_help(<<<'EOF'
        The <info>%command.name%</info> command stores a secret in the vault.
        
            <info>%command.full_name% <name></info>
        
        To reference secrets in services.yaml or any other config
        files, use <info>"%env(<name>)%"</info>.
        
        By default, the secret value should be entered interactively.
        Alternatively, provide a file where to read the secret from:
        
            <info>php %command.full_name% <name> filename</info>
        
        Use "-" as a file name to read from STDIN:
        
            <info>cat filename | php %command.full_name% <name> -</info>
        
        Use <info>--local</info> to override secrets for local needs.
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $err_output = $output instanceof Console_Output_Interface ? $output->get_error_output() : $output;
        $io = new Symfony_Style($input, $err_output);
        $name = $input->get_argument('name');
        $vault = $input->get_option('local') ? $this->local_vault : $this->vault;
        if (null === $vault) {
            $io->error('The local vault is disabled.');
            return 1;
        }
        if ($this->local_vault === $vault && !\array_key_exists($name, $this->vault->list())) {
            $io->error(\sprintf('Secret "%s" does not exist in the vault, you cannot override it locally.', $name));
            return 1;
        }
        if (0 < $random = $input->get_option('random') ?? 16) {
            $value = strtr(substr(base64_encode(random_bytes($random)), 0, $random), '+/', '-_');
        } elseif (!$file = $input->get_argument('file')) {
            $value = $io->ask_hidden('Please type the secret value');
            if (null === $value) {
                $io->warning('No value provided: using empty string');
                $value = '';
            }
        } elseif ('-' === $file) {
            $value = file_get_contents('php://stdin');
        } elseif (is_file($file) && is_readable($file)) {
            $value = file_get_contents($file);
        } elseif (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('File not found: "%s".', $file));
        } elseif (!is_readable($file)) {
            throw new \InvalidArgumentException(\sprintf('File is not readable: "%s".', $file));
        }
        if ($vault->generate_keys()) {
            $io->success($vault->get_last_message());
            if ($this->vault === $vault) {
                $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
            }
        }
        $vault->seal($name, $value);
        $io->success($vault->get_last_message() ?? 'Secret was successfully stored in the vault.');
        if (0 < $random) {
            $err_output->write(' // The generated random value is: <comment>');
            $output->write($value);
            $err_output->writeln('</comment>');
            $io->new_line();
        }
        if ($this->vault === $vault && null !== $this->local_vault->reveal($name)) {
            $io->comment('Note that this secret is overridden in the local vault.');
        }
        return 0;
    }
    public function complete(Completion_Input $input, Completion_Suggestions $suggestions): void
    {
        if ($input->must_suggest_argument_values_for('name')) {
            $suggestions->suggest_values(array_keys($this->vault->list(false)));
        }
    }
}