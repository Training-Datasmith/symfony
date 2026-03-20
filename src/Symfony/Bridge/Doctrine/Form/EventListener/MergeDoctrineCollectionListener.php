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
namespace Symfony\Bridge\Doctrine\Form\Event_Listener;

use Doctrine\Common\Collections\Collection;
use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Form\Form_Event;
use Symfony\Component\Form\Form_Events;
/**
 * Merge changes from the request to a Doctrine\Common\Collections\Collection instance.
 *
 * This works with ORM, MongoDB and CouchDB instances of the collection interface.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @see Collection
 */
class Merge_Doctrine_Collection_Listener implements Event_Subscriber_Interface
{
    public static function get_subscribed_events(): array
    {
        // Higher priority than core MergeCollectionListener so that this one
        // is called before
        return [Form_Events::SUBMIT => [['onSubmit', 5]]];
    }
    public function on_submit(Form_Event $event): void
    {
        $collection = $event->get_form()->get_data();
        $data = $event->get_data();
        // If all items were removed, call clear which has a higher
        // performance on persistent collections
        if ($collection instanceof Collection && 0 === \count($data)) {
            $collection->clear();
        }
    }
}