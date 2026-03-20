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
namespace Symfony\Bridge\Doctrine\Form\Choice_List;

/**
 * Custom loader for entities in the choice list.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
interface Entity_Loader_Interface
{
    /**
     * Returns an array of entities that are valid choices in the corresponding choice list.
     */
    public function get_entities(): array;
    /**
     * Returns an array of entities matching the given identifiers.
     */
    public function get_entities_by_ids(string $identifier, array $values): array;
}