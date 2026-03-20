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

use Symfony\Component\Dependency_Injection\Env_Var_Loader_Interface;
use Symfony\Component\String\Lazy_String;
use Symfony\Component\Var_Exporter\Var_Exporter;
/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Sodium_Vault extends Abstract_Vault implements Env_Var_Loader_Interface
{
    private ?string $encryption_key = null;
    private readonly string $path_prefix;
    private ?string $secrets_dir;
    /**
     * @param $decryptionKey A string or a stringable object that defines the private key to use to decrypt the vault
     *                       or null to store generated keys in the provided $secretsDir
     */
    public function __construct(
        string $secrets_dir,
        #[\Sensitive_Parameter]
        private string|\Stringable|null $decryption_key = null,
        private readonly ?string $derived_secret_env_var = null
    )
    {
        $this->path_prefix = rtrim(strtr($secrets_dir, '/', \DIRECTORY_SEPARATOR), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . basename($secrets_dir) . '.';
        $this->secrets_dir = $secrets_dir;
    }
    public function generate_keys(bool $override = false): bool
    {
        $this->last_message = null;
        if (null === $this->encryption_key && '' !== $this->decryption_key = (string) $this->decryption_key) {
            $this->last_message = 'Cannot generate keys when a decryption key has been provided while instantiating the vault.';
            return false;
        }
        try {
            $this->load_keys();
        } catch (\RuntimeException) {
            // ignore failures to load keys
        }
        if ('' !== $this->decryption_key && !is_file($this->path_prefix . 'encrypt.public.php')) {
            $this->export('encrypt.public', $this->encryption_key);
        }
        if (!$override && null !== $this->encryption_key) {
            $this->last_message = \sprintf('Sodium keys already exist at "%s*.{public,private}" and won\'t be overridden.', $this->get_pretty_path($this->path_prefix));
            return false;
        }
        $this->decryption_key = sodium_crypto_box_keypair();
        $this->encryption_key = sodium_crypto_box_publickey($this->decryption_key);
        $this->export('encrypt.public', $this->encryption_key);
        $this->export('decrypt.private', $this->decryption_key);
        $this->last_message = \sprintf('Sodium keys have been generated at "%s*.public/private.php".', $this->get_pretty_path($this->path_prefix));
        return true;
    }
    public function seal(string $name, string $value): void
    {
        $this->last_message = null;
        $this->validate_name($name);
        $this->load_keys();
        $filename = $this->get_filename($name);
        $this->export($filename, sodium_crypto_box_seal($value, $this->encryption_key ?? sodium_crypto_box_publickey($this->decryption_key)));
        $list = $this->list();
        $list[$name] = null;
        uksort($list, strnatcmp(...));
        file_put_contents($this->path_prefix . 'list.php', \sprintf("<?php\n\nreturn %s;\n", Var_Exporter::export($list)), \LOCK_EX);
        $this->last_message = \sprintf('Secret "%s" encrypted in "%s"; you can commit it.', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
    }
    public function reveal(string $name): ?string
    {
        $this->last_message = null;
        $this->validate_name($name);
        $filename = $this->get_filename($name);
        if (!is_file($file = $this->path_prefix . $filename . '.php')) {
            $this->last_message = \sprintf('Secret "%s" not found in "%s".', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
            return null;
        }
        if (!\function_exists('sodium_crypto_box_seal')) {
            $this->last_message = \sprintf('Secret "%s" cannot be revealed as the "sodium" PHP extension missing. Try running "composer require paragonie/sodium_compat" if you cannot enable the extension."', $name);
            return null;
        }
        $this->load_keys();
        if ('' === $this->decryption_key = (string) $this->decryption_key) {
            $this->last_message = \sprintf('Secret "%s" cannot be revealed as no decryption key was found in "%s".', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
            return null;
        }
        if (false === $value = sodium_crypto_box_seal_open(include $file, $this->decryption_key)) {
            $this->last_message = \sprintf('Secret "%s" cannot be revealed as the wrong decryption key was provided for "%s".', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
            return null;
        }
        return $value;
    }
    public function remove(string $name): bool
    {
        $this->last_message = null;
        $this->validate_name($name);
        $filename = $this->get_filename($name);
        if (!is_file($file = $this->path_prefix . $filename . '.php')) {
            $this->last_message = \sprintf('Secret "%s" not found in "%s".', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
            return false;
        }
        $list = $this->list();
        unset($list[$name]);
        file_put_contents($this->path_prefix . 'list.php', \sprintf("<?php\n\nreturn %s;\n", Var_Exporter::export($list)), \LOCK_EX);
        $this->last_message = \sprintf('Secret "%s" removed from "%s".', $name, $this->get_pretty_path(\dirname($this->path_prefix) . \DIRECTORY_SEPARATOR));
        return @unlink($file) || !file_exists($file);
    }
    public function list(bool $reveal = false): array
    {
        $this->last_message = null;
        if (!is_file($file = $this->path_prefix . 'list.php')) {
            return [];
        }
        $secrets = include $file;
        if (!$reveal) {
            return $secrets;
        }
        foreach ($secrets as $name => $value) {
            $secrets[$name] = $this->reveal($name);
        }
        return $secrets;
    }
    public function load_env_vars(): array
    {
        $envs = [];
        $reveal = $this->reveal(...);
        foreach ($this->list() as $name => $value) {
            $envs[$name] = Lazy_String::from_callable($reveal, $name);
        }
        if ($this->derived_secret_env_var && !\array_key_exists($this->derived_secret_env_var, $envs)) {
            $k = $this->decryption_key;
            $envs[$this->derived_secret_env_var] = Lazy_String::from_callable(static fn(): string => '' !== ($k = (string) $k) ? base64_encode(hash('sha256', $k, true)) : '');
        }
        return $envs;
    }
    private function load_keys(): void
    {
        if (!\function_exists('sodium_crypto_box_seal')) {
            throw new \LogicException('The "sodium" PHP extension is required to deal with secrets. Alternatively, try running "composer require paragonie/sodium_compat" if you cannot enable the extension.".');
        }
        if (null !== $this->encryption_key || '' !== $this->decryption_key = (string) $this->decryption_key) {
            return;
        }
        if (is_file($this->path_prefix . 'decrypt.private.php')) {
            $this->decryption_key = (string) include $this->path_prefix . 'decrypt.private.php';
        }
        if (is_file($this->path_prefix . 'encrypt.public.php')) {
            $this->encryption_key = (string) include $this->path_prefix . 'encrypt.public.php';
        } elseif ('' !== $this->decryption_key) {
            $this->encryption_key = sodium_crypto_box_publickey($this->decryption_key);
        } else {
            throw new \RuntimeException(\sprintf('Encryption key not found in "%s".', \dirname($this->path_prefix)));
        }
    }
    private function export(string $filename, string $data): void
    {
        $b64 = 'decrypt.private' === $filename ? '// SYMFONY_DECRYPTION_SECRET=' . base64_encode($data) . "\n" : '';
        $name = basename($this->path_prefix . $filename);
        $data = str_replace('%', '\x', rawurlencode($data));
        $data = \sprintf("<?php // %s on %s\n\n%sreturn \"%s\";\n", $name, date('r'), $b64, $data);
        $this->create_secrets_dir();
        if (false === file_put_contents($this->path_prefix . $filename . '.php', $data, \LOCK_EX)) {
            $e = error_get_last();
            throw new \ErrorException($e['message'] ?? 'Failed to write secrets data.', 0, $e['type'] ?? \E_USER_WARNING);
        }
    }
    private function create_secrets_dir(): void
    {
        if ($this->secrets_dir && !is_dir($this->secrets_dir) && !@mkdir($this->secrets_dir, 0777, true) && !is_dir($this->secrets_dir)) {
            throw new \RuntimeException(\sprintf('Unable to create the secrets directory (%s).', $this->secrets_dir));
        }
        $this->secrets_dir = null;
    }
    private function get_filename(string $name): string
    {
        // The MD5 hash allows making secrets case-sensitive. The filename is not enough on Windows.
        return $name . '.' . substr(md5($name), 0, 6);
    }
}