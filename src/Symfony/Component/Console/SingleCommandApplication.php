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
namespace Symfony\Component\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\Input_Interface;
use Symfony\Component\Console\Output\Output_Interface;
/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class Single_Command_Application extends Command
{
    private string $version = 'UNKNOWN';
    private bool $auto_exit = true;
    private bool $running = false;
    /**
     * @return $this
     */
    public function set_version(string $version): static
    {
        $this->version = $version;
        return $this;
    }
    /**
     * @final
     *
     * @return $this
     */
    public function set_auto_exit(bool $auto_exit): static
    {
        $this->auto_exit = $auto_exit;
        return $this;
    }
    public function run(?Input_Interface $input = null, ?Output_Interface $output = null): int
    {
        if ($this->running) {
            return parent::run($input, $output);
        }
        // We use the command name as the application name
        $application = new Application($this->get_name() ?: 'UNKNOWN', $this->version);
        $application->set_auto_exit($this->auto_exit);
        // Fix the usage of the command displayed with "--help"
        $this->set_name($_SERVER['argv'][0]);
        $application->add_command($this);
        $application->set_default_command($this->get_name(), true);
        $this->running = true;
        try {
            $ret = $application->run($input, $output);
        } finally {
            $this->running = false;
        }
        return $ret;
    }
}