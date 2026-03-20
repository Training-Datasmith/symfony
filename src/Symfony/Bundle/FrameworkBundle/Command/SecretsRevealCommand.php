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
use Symfony\Component\Console\Input\Input_Argument;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Console_Output_Interface;
use Symfony\Component\Console\Output\Output_Interface;
use Symfony\Component\Console\Style\Symfony_Style;
/**
 * @internal
 */
#[As_Command(name: 'secrets:reveal', description: 'Reveal the value of a secret')]
final class Secrets_Reveal_Command extends Command
{
    public function __construct(private readonly Abstract_Vault $vault, private readonly ?Abstract_Vault $local_vault = null)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->add_argument('name', Input_Argument::REQUIRED, 'The name of the secret to reveal', null, fn(): array => array_keys($this->vault->list()))->set_help(<<<'EOF'
        The <info>%command.name%</info> command reveals a stored secret.
        
            <info>%command.full_name%</info>
        EOF);
    }
    protected function execute(Input_Interface $input, Output_Interface $output): int
    {
        $io = new Symfony_Style($input, $output instanceof Console_Output_Interface ? $output->get_error_output() : $output);
        $secrets = $this->vault->list(true);
        $local_secrets = $this->local_vault?->list(true);
        $name = (string) $input->get_argument('name');
        if (null !== $local_secrets && \array_key_exists($name, $local_secrets)) {
            $io->writeln($local_secrets[$name]);
        } else {
            if (!\array_key_exists($name, $secrets)) {
                $io->error(\sprintf('The secret "%s" does not exist.', $name));
                return self::INVALID;
            }
            if (null === $secrets[$name]) {
                $io->error(\sprintf('The secret "%s" could not be decrypted.', $name));
                return self::INVALID;
            }
            $io->writeln($secrets[$name]);
        }
        return self::SUCCESS;
    }
}