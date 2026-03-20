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
use Symfony\Component\Console\Helper\Dumper;
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
#[As_Command(name: 'secrets:list', description: 'List all secrets')]
final class Secrets_List_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_option('reveal', 'r', Input_Option::VALUE_NONE, 'Display decrypted values alongside names')->set_help(<<<'EOF'
        The <info>%command.name%</info> command list all stored secrets.
        
            <info>%command.full_name%</info>
        
        When the option <info>--reveal</info> is provided, the decrypted secrets are also displayed.
        
            <info>%command.full_name% --reveal</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output instanceof Console_Output_Interface ? $output->get_error_output() : $output);
        $io->comment('Use <info>"%env(<name>)%"</info> to reference a secret in a config file.');
        if (!$reveal = $input->get_option('reveal')) {
            $io->comment(\sprintf('To reveal the secrets run <info>php %s %s --reveal</info>', $_SERVER['PHP_SELF'], $this->get_name()));
        }
        $secrets = $this->vault->list($reveal);
        $local_secrets = $this->local_vault?->list($reveal);
        $rows = [];
        $dump = new Dumper($output);
        $dump = static fn($v): string => null === $v ? '******' : $dump($v);
        foreach ($secrets as $name => $value) {
            $rows[$name] = [$name, $dump($value)];
        }
        if (null !== $message = $this->vault->get_last_message()) {
            $io->comment($message);
        }
        foreach ($local_secrets ?? [] as $name => $value) {
            if (isset($rows[$name])) {
                $rows[$name][] = $dump($value);
            }
        }
        if (null !== $this->local_vault && null !== $message = $this->local_vault->get_last_message()) {
            $io->comment($message);
        }
        (new Symfony_Style($input, $output))->table(['Secret', 'Value'] + (null !== $local_secrets ? [2 => 'Local Value'] : []), $rows);
        $io->comment("Local values override secret values.\nUse <info>secrets:set --local</info> to define them.");
        return 0;
    }
}