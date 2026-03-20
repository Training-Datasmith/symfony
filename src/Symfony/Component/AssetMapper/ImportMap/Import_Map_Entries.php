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
namespace Symfony\Component\Asset_Mapper\Import_Map;

/**
 * Holds the collection of importmap entries defined in importmap.php.
 *
 * @template-implements \IteratorAggregate<ImportMapEntry>
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class Import_Map_Entries implements \IteratorAggregate
{
    private array $entries = [];
    /**
     * @param ImportMapEntry[] $entries
     */
    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }
    public function add(Import_Map_Entry $entry): void
    {
        $this->entries[$entry->import_name] = $entry;
    }
    public function has(string $import_name): bool
    {
        return isset($this->entries[$import_name]);
    }
    public function get(string $import_name): Import_Map_Entry
    {
        if (!$this->has($import_name)) {
            throw new \InvalidArgumentException(\sprintf('The importmap entry "%s" does not exist.', $import_name));
        }
        return $this->entries[$import_name];
    }
    /**
     * @return \Traversable<ImportMapEntry>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_values($this->entries));
    }
    public function remove(string $package_name): void
    {
        unset($this->entries[$package_name]);
    }
}