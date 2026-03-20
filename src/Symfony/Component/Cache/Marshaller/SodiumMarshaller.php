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
namespace Symfony\Component\Cache\Marshaller;

use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
/**
 * Encrypt/decrypt values using Libsodium.
 *
 * @author Ahmed TAILOULOUTE <ahmed.tailouloute@gmail.com>
 */
class Sodium_Marshaller implements Marshaller_Interface
{
    /**
     * @param string[] $decryptionKeys The key at index "0" is required and is used to decrypt and encrypt values;
     *                                 more rotating keys can be provided to decrypt values;
     *                                 each key must be generated using sodium_crypto_box_keypair()
     */
    public function __construct(private array $decryption_keys, private readonly ?Marshaller_Interface $marshaller = new Default_Marshaller())
    {
        if (!self::is_supported()) {
            throw new Cache_Exception('The "sodium" PHP extension is not loaded.');
        }
        if (!isset($decryption_keys[0])) {
            throw new InvalidArgumentException('At least one decryption key must be provided at index "0".');
        }
    }
    public static function is_supported(): bool
    {
        return \function_exists('sodium_crypto_box_seal');
    }
    public function marshall(array $values, ?array &$failed): array
    {
        $encryption_key = sodium_crypto_box_publickey($this->decryption_keys[0]);
        $encrypted_values = [];
        foreach ($this->marshaller->marshall($values, $failed) as $k => $v) {
            $encrypted_values[$k] = sodium_crypto_box_seal((string) $v, $encryption_key);
        }
        return $encrypted_values;
    }
    public function unmarshall(string $value): mixed
    {
        foreach ($this->decryption_keys as $k) {
            if (false !== $decrypted_value = @sodium_crypto_box_seal_open($value, $k)) {
                $value = $decrypted_value;
                break;
            }
        }
        return $this->marshaller->unmarshall($value);
    }
}