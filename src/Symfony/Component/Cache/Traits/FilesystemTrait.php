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
namespace Symfony\Component\Cache\Traits;

use Symfony\Component\Cache\Exception\Cache_Exception;
use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Rob Frawley 2nd <rmf@src.run>
 *
 * @internal
 */
trait Filesystem_Trait
{
    use Filesystem_Common_Trait;
    private Marshaller_Interface $marshaller;
    public function prune(): bool
    {
        $time = time();
        $pruned = true;
        foreach ($this->scan_hash_dir($this->directory) as $file) {
            if (!$h = @fopen($file, 'r')) {
                continue;
            }
            if (($expires_at = (int) fgets($h)) && $time >= $expires_at) {
                fclose($h);
                $pruned = (@unlink($file) || !file_exists($file)) && $pruned;
            } else {
                fclose($h);
            }
        }
        return $pruned;
    }
    protected function do_fetch(array $ids): iterable
    {
        $values = [];
        $now = time();
        foreach ($ids as $id) {
            $file = $this->get_file($id);
            if (!is_file($file)) {
                continue;
            }
            if (!$h = @fopen($file, 'r')) {
                continue;
            }
            if (($expires_at = (int) fgets($h)) && $now >= $expires_at) {
                fclose($h);
                @unlink($file);
            } else {
                $i = rawurldecode(rtrim(fgets($h)));
                $value = stream_get_contents($h);
                fclose($h);
                if ($i === $id) {
                    $values[$id] = $this->marshaller->unmarshall($value);
                }
            }
        }
        return $values;
    }
    protected function do_have(string $id): bool
    {
        $file = $this->get_file($id);
        return is_file($file) && (@filemtime($file) > time() || $this->do_fetch([$id]));
    }
    protected function do_save(array $values, int $lifetime): array|bool
    {
        $expires_at = $lifetime ? time() + $lifetime : 0;
        $values = $this->marshaller->marshall($values, $failed);
        foreach ($values as $id => $value) {
            if (!$this->write($this->get_file($id, true), $expires_at . "\n" . rawurlencode((string) $id) . "\n" . $value, $expires_at)) {
                $failed[] = $id;
            }
        }
        if ($failed && !is_writable($this->directory)) {
            throw new Cache_Exception(\sprintf('Cache directory is not writable (%s).', $this->directory));
        }
        return $failed;
    }
    private function get_file_key(string $file): string
    {
        if (!$h = @fopen($file, 'r')) {
            return '';
        }
        fgets($h);
        // expiry
        $encoded_key = fgets($h);
        fclose($h);
        return rawurldecode(rtrim($encoded_key));
    }
}