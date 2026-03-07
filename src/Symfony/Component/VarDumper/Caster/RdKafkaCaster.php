<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use RdKafka\Conf;
use RdKafka\Exception as RdKafkaException;
use RdKafka\KafkaConsumer;
use RdKafka\Message;
use RdKafka\Metadata\Broker as BrokerMetadata;
use RdKafka\Metadata\Collection as CollectionMetadata;
use RdKafka\Metadata\Partition as PartitionMetadata;
use RdKafka\Metadata\Topic as TopicMetadata;
use RdKafka\Topic;
use RdKafka\TopicConf;
use RdKafka\TopicPartition;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Casts RdKafka related classes to array representation.
 *
 * @author Romain Neutron <imprec@gmail.com>
 *
 * @internal
 */
class RdKafkaCaster
{
    public static function castKafkaConsumer(KafkaConsumer $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        try {
            $assignment = $c->getAssignment();
        } catch (RdKafkaException) {
            $assignment = [];
        }

        $a += [
            $prefix.'subscription' => $c->getSubscription(),
            $prefix.'assignment' => $assignment,
        ];

        return $a + self::extractMetadata($c);
    }

    public static function castTopic(Topic $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'name' => $c->getName(),
        ];
    }

    public static function castTopicPartition(TopicPartition $c, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'offset' => $c->getOffset(),
            $prefix.'partition' => $c->getPartition(),
            $prefix.'topic' => $c->getTopic(),
        ];
    }

    public static function castMessage(Message $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'errstr' => $c->errstr(),
        ];
    }

    public static function castConf(Conf $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        foreach ($c->dump() as $key => $value) {
            $a[$prefix.$key] = $value;
        }

        return $a;
    }

    public static function castTopicConf(TopicConf $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        foreach ($c->dump() as $key => $value) {
            $a[$prefix.$key] = $value;
        }

        return $a;
    }

    public static function castRdKafka(\RdKafka $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a += [
            $prefix.'out_q_len' => $c->getOutQLen(),
        ];

        return $a + self::extractMetadata($c);
    }

    public static function castCollectionMetadata(CollectionMetadata $c, array $a, Stub $stub, bool $isNested): array
    {
        return $a + iterator_to_array($c);
    }

    public static function castTopicMetadata(TopicMetadata $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'name' => $c->getTopic(),
            $prefix.'partitions' => $c->getPartitions(),
        ];
    }

    public static function castPartitionMetadata(PartitionMetadata $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'id' => $c->getId(),
            $prefix.'err' => $c->getErr(),
            $prefix.'leader' => $c->getLeader(),
        ];
    }

    public static function castBrokerMetadata(BrokerMetadata $c, array $a, Stub $stub, bool $isNested): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        return $a + [
            $prefix.'id' => $c->getId(),
            $prefix.'host' => $c->getHost(),
            $prefix.'port' => $c->getPort(),
        ];
    }

    private static function extractMetadata(KafkaConsumer|\RdKafka $c): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        try {
            $m = $c->getMetadata(true, null, 500);
        } catch (RdKafkaException) {
            return [];
        }

        return [
            $prefix.'orig_broker_id' => $m->getOrigBrokerId(),
            $prefix.'orig_broker_name' => $m->getOrigBrokerName(),
            $prefix.'brokers' => $m->getBrokers(),
            $prefix.'topics' => $m->getTopics(),
        ];
    }
}
