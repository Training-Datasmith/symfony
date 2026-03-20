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
namespace Symfony\Bundle\Framework_Bundle\Secrets;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Dotenv_Vault extends Abstract_Vault
{
    public function __construct(private string $dotenv_file)
    {
        $this->dotenv_file = strtr($dotenv_file, '/', \DIRECTORY_SEPARATOR);
    }
    public function generate_keys(bool $override = false): bool
    {
        $this->last_message = 'The dotenv vault doesn\'t encrypt secrets thus doesn\'t need keys.';
        return false;
    }
    public function seal(string $name, string $value): void
    {
        $this->last_message = null;
        $this->validate_name($name);
        $v = str_replace("'", "'\\''", $value);
        $content = is_file($this->dotenv_file) ? file_get_contents($this->dotenv_file) : '';
        $content = preg_replace("/^{$name}=((\\\\'|'[^']++')++|.*)/m", "{$name}='{$v}'", $content, -1, $count);
        if (!$count) {
            $content .= "{$name}='{$v}'\n";
        }
        file_put_contents($this->dotenv_file, $content);
        $this->last_message = \sprintf('Secret "%s" %s in "%s".', $name, $count ? 'added' : 'updated', $this->get_pretty_path($this->dotenv_file));
    }
    public function reveal(string $name): ?string
    {
        $this->last_message = null;
        $this->validate_name($name);
        $v = $_ENV[$name] ?? (str_starts_with($name, 'HTTP_') ? null : $_SERVER[$name] ?? null);
        if ('' === ($v ?? '')) {
            $this->last_message = \sprintf('Secret "%s" not found in "%s".', $name, $this->get_pretty_path($this->dotenv_file));
            return null;
        }
        return $v;
    }
    public function remove(string $name): bool
    {
        $this->last_message = null;
        $this->validate_name($name);
        $content = is_file($this->dotenv_file) ? file_get_contents($this->dotenv_file) : '';
        $content = preg_replace("/^{$name}=((\\\\'|'[^']++')++|.*)\n?/m", '', $content, -1, $count);
        if ($count) {
            file_put_contents($this->dotenv_file, $content);
            $this->last_message = \sprintf('Secret "%s" removed from file "%s".', $name, $this->get_pretty_path($this->dotenv_file));
            return true;
        }
        $this->last_message = \sprintf('Secret "%s" not found in "%s".', $name, $this->get_pretty_path($this->dotenv_file));
        return false;
    }
    public function list(bool $reveal = false): array
    {
        $this->last_message = null;
        $secrets = [];
        foreach ($_ENV as $k => $v) {
            if ('' !== ($v ?? '') && preg_match('/^\w+$/D', (string) $k)) {
                $secrets[$k] = \is_string($v) && $reveal ? $v : null;
            }
        }
        foreach ($_SERVER as $k => $v) {
            if ('' !== ($v ?? '') && preg_match('/^\w+$/D', (string) $k)) {
                $secrets[$k] = \is_string($v) && $reveal ? $v : null;
            }
        }
        return $secrets;
    }
}