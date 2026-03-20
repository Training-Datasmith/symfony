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
namespace Symfony\Component\Cache\Adapter;

use Symfony\Component\Cache\Marshaller\Marshaller_Interface;
use Symfony\Component\Cache\Marshaller\Tag_Aware_Marshaller;
use Symfony\Component\Cache\Pruneable_Interface;
use Symfony\Component\Cache\Traits\Filesystem_Trait;
/**
 * Stores tag id <> cache id relationship as a symlink, and lookup on invalidation calls.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author André Rømcke <andre.romcke+symfony@gmail.com>
 */
class Filesystem_Tag_Aware_Adapter extends Abstract_Tag_Aware_Adapter implements Pruneable_Interface
{
    use Filesystem_Trait {
        prune as private doPrune;
        doClear as private doClearCache;
        doSave as private doSaveCache;
    }
    /**
     * Folder used for tag symlinks.
     */
    private const TAG_FOLDER = 'tags';
    public function __construct(string $namespace = '', int $default_lifetime = 0, ?string $directory = null, ?Marshaller_Interface $marshaller = null)
    {
        $this->marshaller = new Tag_Aware_Marshaller($marshaller);
        parent::__construct('', $default_lifetime);
        $this->init($namespace, $directory);
    }
    public function prune(): bool
    {
        $ok = $this->do_prune();
        set_error_handler(static function (): void {
        });
        $chars = '+-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        try {
            foreach ($this->scan_hash_dir($this->directory . self::TAG_FOLDER . \DIRECTORY_SEPARATOR) as $dir) {
                $dir .= \DIRECTORY_SEPARATOR;
                $keep_dir = false;
                for ($i = 0; $i < 38; ++$i) {
                    if (!is_dir($dir . $chars[$i])) {
                        continue;
                    }
                    for ($j = 0; $j < 38; ++$j) {
                        if (!is_dir($d = $dir . $chars[$i] . \DIRECTORY_SEPARATOR . $chars[$j])) {
                            continue;
                        }
                        foreach (scandir($d, \SCANDIR_SORT_NONE) ?: [] as $link) {
                            if ('.' === $link) {
                                continue;
                            }
                            if ('..' === $link) {
                                continue;
                            }
                            if ('_' !== $dir[-2] && realpath($d . \DIRECTORY_SEPARATOR . $link)) {
                                $keep_dir = true;
                            } else {
                                unlink($d . \DIRECTORY_SEPARATOR . $link);
                            }
                        }
                        $keep_dir ?: rmdir($d);
                    }
                    $keep_dir ?: rmdir($dir . $chars[$i]);
                }
                $keep_dir ?: rmdir($dir);
            }
        } finally {
            restore_error_handler();
        }
        return $ok;
    }
    protected function do_clear(string $namespace): bool
    {
        $ok = $this->do_clear_cache($namespace);
        if ('' !== $namespace) {
            return $ok;
        }
        set_error_handler(static function (): void {
        });
        $chars = '+-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $this->tmp_suffix ??= str_replace('/', '-', base64_encode(random_bytes(6)));
        try {
            foreach ($this->scan_hash_dir($this->directory . self::TAG_FOLDER . \DIRECTORY_SEPARATOR) as $dir) {
                if (rename($dir, $renamed = substr_replace($dir, $this->tmp_suffix . '_', -9))) {
                    $dir = $renamed . \DIRECTORY_SEPARATOR;
                } else {
                    $dir .= \DIRECTORY_SEPARATOR;
                    $renamed = null;
                }
                for ($i = 0; $i < 38; ++$i) {
                    if (!is_dir($dir . $chars[$i])) {
                        continue;
                    }
                    for ($j = 0; $j < 38; ++$j) {
                        if (!is_dir($d = $dir . $chars[$i] . \DIRECTORY_SEPARATOR . $chars[$j])) {
                            continue;
                        }
                        foreach (scandir($d, \SCANDIR_SORT_NONE) ?: [] as $link) {
                            if ('.' !== $link && '..' !== $link && (null !== $renamed || !realpath($d . \DIRECTORY_SEPARATOR . $link))) {
                                unlink($d . \DIRECTORY_SEPARATOR . $link);
                            }
                        }
                        null === $renamed ?: rmdir($d);
                    }
                    null === $renamed ?: rmdir($dir . $chars[$i]);
                }
                null === $renamed ?: rmdir($renamed);
            }
        } finally {
            restore_error_handler();
        }
        return $ok;
    }
    protected function do_save(array $values, int $lifetime, array $add_tag_data = [], array $remove_tag_data = []): array
    {
        $failed = $this->do_save_cache($values, $lifetime);
        // Add Tags as symlinks
        foreach ($add_tag_data as $tag_id => $ids) {
            $tag_folder = $this->get_tag_folder($tag_id);
            foreach ($ids as $id) {
                if ($failed && \in_array($id, $failed, true)) {
                    continue;
                }
                $file = $this->get_file($id);
                if (!@symlink($file, $tag_link = $this->get_file($id, true, $tag_folder)) && !is_link($tag_link)) {
                    @unlink($file);
                    $failed[] = $id;
                }
            }
        }
        // Unlink removed Tags
        foreach ($remove_tag_data as $tag_id => $ids) {
            $tag_folder = $this->get_tag_folder($tag_id);
            foreach ($ids as $id) {
                if ($failed && \in_array($id, $failed, true)) {
                    continue;
                }
                @unlink($this->get_file($id, false, $tag_folder));
            }
        }
        return $failed;
    }
    protected function do_delete_yield_tags(array $ids): iterable
    {
        foreach ($ids as $id) {
            $file = $this->get_file($id);
            if (!is_file($file)) {
                continue;
            }
            if (!$h = @fopen($file, 'r')) {
                continue;
            }
            if (!@unlink($file)) {
                fclose($h);
                continue;
            }
            $meta = explode("\n", fread($h, 4096), 3)[2] ?? '';
            // detect the compact format used in marshall() using magic numbers in the form 9D-..-..-..-..-00-..-..-..-5F
            if (13 < \strlen($meta) && "\x9d" === $meta[0] && "\x00" === $meta[5] && "_" === $meta[9]) {
                $meta[9] = "\x00";
                $tag_len = unpack('Nlen', $meta, 9)['len'];
                $meta = substr($meta, 13, $tag_len);
                if (0 < $tag_len -= \strlen($meta)) {
                    $meta .= fread($h, $tag_len);
                }
                try {
                    yield $id => '' === $meta ? [] : $this->marshaller->unmarshall($meta);
                } catch (\Exception) {
                    yield $id => [];
                }
            }
            fclose($h);
        }
    }
    protected function do_delete_tag_relations(array $tag_data): bool
    {
        foreach ($tag_data as $tag_id => $id_list) {
            $tag_folder = $this->get_tag_folder($tag_id);
            foreach ($id_list as $id) {
                @unlink($this->get_file($id, false, $tag_folder));
            }
        }
        return true;
    }
    protected function do_invalidate(array $tag_ids): bool
    {
        foreach ($tag_ids as $tag_id) {
            if (!is_dir($tag_folder = $this->get_tag_folder($tag_id))) {
                continue;
            }
            $this->tmp_suffix ??= str_replace('/', '-', base64_encode(random_bytes(6)));
            set_error_handler(static function (): void {
            });
            try {
                if (rename($tag_folder, $renamed = substr_replace($tag_folder, $this->tmp_suffix . '_', -10))) {
                    $tag_folder = $renamed . \DIRECTORY_SEPARATOR;
                } else {
                    $renamed = null;
                }
                foreach ($this->scan_hash_dir($tag_folder) as $item_link) {
                    unlink(realpath($item_link) ?: $item_link);
                    unlink($item_link);
                }
                if (null === $renamed) {
                    continue;
                }
                $chars = '+-ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                for ($i = 0; $i < 38; ++$i) {
                    for ($j = 0; $j < 38; ++$j) {
                        rmdir($tag_folder . $chars[$i] . \DIRECTORY_SEPARATOR . $chars[$j]);
                    }
                    rmdir($tag_folder . $chars[$i]);
                }
                rmdir($renamed);
            } finally {
                restore_error_handler();
            }
        }
        return true;
    }
    private function get_tag_folder(string $tag_id): string
    {
        return $this->get_file($tag_id, false, $this->directory . self::TAG_FOLDER . \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
    }
}