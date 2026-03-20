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

namespace Symfony\Component\VarDumper\Cloner;

use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Exception\ThrowingCasterException;

/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static array $defaultCasters = [
        '__PHP_Incomplete_Class' => [\Symfony\Component\VarDumper\Caster\Caster::class, 'castPhpIncompleteClass'],

        'AddressInfo' => [\Symfony\Component\VarDumper\Caster\AddressInfoCaster::class, 'castAddressInfo'],
        'Socket' => [\Symfony\Component\VarDumper\Caster\SocketCaster::class, 'castSocket'],

        \Symfony\Component\VarDumper\Caster\CutStub::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'castStub'],
        \Symfony\Component\VarDumper\Caster\CutArrayStub::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'castCutArray'],
        \Symfony\Component\VarDumper\Caster\ConstStub::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'castStub'],
        \Symfony\Component\VarDumper\Caster\EnumStub::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'castEnum'],
        \Symfony\Component\VarDumper\Caster\ScalarStub::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'castScalar'],

        'Fiber' => [\Symfony\Component\VarDumper\Caster\FiberCaster::class, 'castFiber'],

        'Closure' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castClosure'],
        'Generator' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castGenerator'],
        'ReflectionType' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castType'],
        'ReflectionAttribute' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castAttribute'],
        'ReflectionGenerator' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castReflectionGenerator'],
        'ReflectionClass' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castClass'],
        'ReflectionClassConstant' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castClassConstant'],
        'ReflectionFunctionAbstract' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castFunctionAbstract'],
        'ReflectionMethod' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castMethod'],
        'ReflectionParameter' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castParameter'],
        'ReflectionProperty' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castProperty'],
        'ReflectionReference' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castReference'],
        'ReflectionExtension' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castExtension'],
        'ReflectionZendExtension' => [\Symfony\Component\VarDumper\Caster\ReflectionCaster::class, 'castZendExtension'],

        'Doctrine\Common\Persistence\ObjectManager' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        'Doctrine\Common\Proxy\Proxy' => [\Symfony\Component\VarDumper\Caster\DoctrineCaster::class, 'castCommonProxy'],
        'Doctrine\ORM\Proxy\Proxy' => [\Symfony\Component\VarDumper\Caster\DoctrineCaster::class, 'castOrmProxy'],
        'Doctrine\ORM\PersistentCollection' => [\Symfony\Component\VarDumper\Caster\DoctrineCaster::class, 'castPersistentCollection'],
        'Doctrine\Persistence\ObjectManager' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],

        'DOMException' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castException'],
        'Dom\Exception' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castException'],
        'DOMStringList' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMNameList' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMImplementation' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castImplementation'],
        \Dom\Implementation::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castImplementation'],
        'DOMImplementationList' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMNode' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\Node::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMNameSpaceNode' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMDocument' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDocument'],
        \Dom\XMLDocument::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castXMLDocument'],
        \Dom\HTMLDocument::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castHTMLDocument'],
        'DOMNodeList' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\NodeList::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMNamedNodeMap' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\DTDNamedNodeMap::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        'DOMXPath' => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\XPath::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\HTMLCollection::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],
        \Dom\TokenList::class => [\Symfony\Component\VarDumper\Caster\DOMCaster::class, 'castDom'],

        'XMLReader' => [\Symfony\Component\VarDumper\Caster\XmlReaderCaster::class, 'castXmlReader'],

        'ErrorException' => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castErrorException'],
        'Exception' => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castException'],
        'Error' => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castError'],
        'Symfony\Bridge\Monolog\Logger' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        \Symfony\Component\DependencyInjection\ContainerInterface::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        \Symfony\Component\EventDispatcher\EventDispatcherInterface::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        \Symfony\Component\HttpClient\AmpHttpClient::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClient'],
        \Symfony\Component\HttpClient\CurlHttpClient::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClient'],
        \Symfony\Component\HttpClient\NativeHttpClient::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClient'],
        \Symfony\Component\HttpClient\Response\AmpResponse::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClientResponse'],
        'Symfony\Component\HttpClient\Response\AmpResponseV4' => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClientResponse'],
        'Symfony\Component\HttpClient\Response\AmpResponseV5' => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClientResponse'],
        \Symfony\Component\HttpClient\Response\CurlResponse::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClientResponse'],
        \Symfony\Component\HttpClient\Response\NativeResponse::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castHttpClientResponse'],
        \Symfony\Component\HttpFoundation\Request::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castRequest'],
        \Symfony\Component\Uid\Ulid::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castUlid'],
        \Symfony\Component\Uid\Uuid::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castUuid'],
        \Symfony\Component\VarExporter\Internal\LazyObjectState::class => [\Symfony\Component\VarDumper\Caster\SymfonyCaster::class, 'castLazyObjectState'],
        \Symfony\Component\VarDumper\Exception\ThrowingCasterException::class => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castThrowingCasterException'],
        \Symfony\Component\VarDumper\Caster\TraceStub::class => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castTraceStub'],
        \Symfony\Component\VarDumper\Caster\FrameStub::class => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castFrameStub'],
        \Symfony\Component\VarDumper\Cloner\AbstractCloner::class => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        \Symfony\Component\ErrorHandler\Exception\FlattenException::class => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castFlattenException'],
        \Symfony\Component\ErrorHandler\Exception\SilencedErrorContext::class => [\Symfony\Component\VarDumper\Caster\ExceptionCaster::class, 'castSilencedErrorContext'],

        'Imagine\Image\ImageInterface' => [\Symfony\Component\VarDumper\Caster\ImagineCaster::class, 'castImage'],

        'Ramsey\Uuid\UuidInterface' => [\Symfony\Component\VarDumper\Caster\UuidCaster::class, 'castRamseyUuid'],

        'ProxyManager\Proxy\ProxyInterface' => [\Symfony\Component\VarDumper\Caster\ProxyManagerCaster::class, 'castProxy'],
        'PHPUnit_Framework_MockObject_MockObject' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        'PHPUnit\Framework\MockObject\MockObject' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        'PHPUnit\Framework\MockObject\Stub' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        'Prophecy\Prophecy\ProphecySubjectInterface' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],
        'Mockery\MockInterface' => [\Symfony\Component\VarDumper\Caster\StubCaster::class, 'cutInternals'],

        'PDO' => [\Symfony\Component\VarDumper\Caster\PdoCaster::class, 'castPdo'],
        'PDOStatement' => [\Symfony\Component\VarDumper\Caster\PdoCaster::class, 'castPdoStatement'],

        'AMQPConnection' => [\Symfony\Component\VarDumper\Caster\AmqpCaster::class, 'castConnection'],
        'AMQPChannel' => [\Symfony\Component\VarDumper\Caster\AmqpCaster::class, 'castChannel'],
        'AMQPQueue' => [\Symfony\Component\VarDumper\Caster\AmqpCaster::class, 'castQueue'],
        'AMQPExchange' => [\Symfony\Component\VarDumper\Caster\AmqpCaster::class, 'castExchange'],
        'AMQPEnvelope' => [\Symfony\Component\VarDumper\Caster\AmqpCaster::class, 'castEnvelope'],

        'ArrayObject' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castArrayObject'],
        'ArrayIterator' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castArrayIterator'],
        'SplDoublyLinkedList' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castDoublyLinkedList'],
        'SplFileInfo' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castFileInfo'],
        'SplFileObject' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castFileObject'],
        'SplHeap' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castHeap'],
        'SplObjectStorage' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castObjectStorage'],
        'SplPriorityQueue' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castHeap'],
        'OuterIterator' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castOuterIterator'],
        'WeakMap' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castWeakMap'],
        'WeakReference' => [\Symfony\Component\VarDumper\Caster\SplCaster::class, 'castWeakReference'],

        'Redis' => [\Symfony\Component\VarDumper\Caster\RedisCaster::class, 'castRedis'],
        \Relay\Relay::class => [\Symfony\Component\VarDumper\Caster\RedisCaster::class, 'castRedis'],
        'RedisArray' => [\Symfony\Component\VarDumper\Caster\RedisCaster::class, 'castRedisArray'],
        'RedisCluster' => [\Symfony\Component\VarDumper\Caster\RedisCaster::class, 'castRedisCluster'],

        'DateTimeInterface' => [\Symfony\Component\VarDumper\Caster\DateCaster::class, 'castDateTime'],
        'DateInterval' => [\Symfony\Component\VarDumper\Caster\DateCaster::class, 'castInterval'],
        'DateTimeZone' => [\Symfony\Component\VarDumper\Caster\DateCaster::class, 'castTimeZone'],
        'DatePeriod' => [\Symfony\Component\VarDumper\Caster\DateCaster::class, 'castPeriod'],

        'GMP' => [\Symfony\Component\VarDumper\Caster\GmpCaster::class, 'castGmp'],

        'MessageFormatter' => [\Symfony\Component\VarDumper\Caster\IntlCaster::class, 'castMessageFormatter'],
        'NumberFormatter' => [\Symfony\Component\VarDumper\Caster\IntlCaster::class, 'castNumberFormatter'],
        'IntlTimeZone' => [\Symfony\Component\VarDumper\Caster\IntlCaster::class, 'castIntlTimeZone'],
        'IntlCalendar' => [\Symfony\Component\VarDumper\Caster\IntlCaster::class, 'castIntlCalendar'],
        'IntlDateFormatter' => [\Symfony\Component\VarDumper\Caster\IntlCaster::class, 'castIntlDateFormatter'],

        'Memcached' => [\Symfony\Component\VarDumper\Caster\MemcachedCaster::class, 'castMemcached'],

        \Ds\Collection::class => [\Symfony\Component\VarDumper\Caster\DsCaster::class, 'castCollection'],
        \Ds\Map::class => [\Symfony\Component\VarDumper\Caster\DsCaster::class, 'castMap'],
        \Ds\Pair::class => [\Symfony\Component\VarDumper\Caster\DsCaster::class, 'castPair'],
        \Symfony\Component\VarDumper\Caster\DsPairStub::class => [\Symfony\Component\VarDumper\Caster\DsCaster::class, 'castPairStub'],

        'mysqli_driver' => [\Symfony\Component\VarDumper\Caster\MysqliCaster::class, 'castMysqliDriver'],

        'CurlHandle' => [\Symfony\Component\VarDumper\Caster\CurlCaster::class, 'castCurl'],

        \Dba\Connection::class => [\Symfony\Component\VarDumper\Caster\ResourceCaster::class, 'castDba'],

        'GdImage' => [\Symfony\Component\VarDumper\Caster\GdCaster::class, 'castGd'],

        'SQLite3Result' => [\Symfony\Component\VarDumper\Caster\SqliteCaster::class, 'castSqlite3Result'],

        \PgSql\Lob::class => [\Symfony\Component\VarDumper\Caster\PgSqlCaster::class, 'castLargeObject'],
        \PgSql\Connection::class => [\Symfony\Component\VarDumper\Caster\PgSqlCaster::class, 'castLink'],
        \PgSql\Result::class => [\Symfony\Component\VarDumper\Caster\PgSqlCaster::class, 'castResult'],

        ':process' => [\Symfony\Component\VarDumper\Caster\ResourceCaster::class, 'castProcess'],
        ':stream' => [\Symfony\Component\VarDumper\Caster\ResourceCaster::class, 'castStream'],

        'OpenSSLAsymmetricKey' => [\Symfony\Component\VarDumper\Caster\OpenSSLCaster::class, 'castOpensslAsymmetricKey'],
        'OpenSSLCertificateSigningRequest' => [\Symfony\Component\VarDumper\Caster\OpenSSLCaster::class, 'castOpensslCsr'],
        'OpenSSLCertificate' => [\Symfony\Component\VarDumper\Caster\OpenSSLCaster::class, 'castOpensslX509'],

        ':persistent stream' => [\Symfony\Component\VarDumper\Caster\ResourceCaster::class, 'castStream'],
        ':stream-context' => [\Symfony\Component\VarDumper\Caster\ResourceCaster::class, 'castStreamContext'],

        'XmlParser' => [\Symfony\Component\VarDumper\Caster\XmlResourceCaster::class, 'castXml'],

        'RdKafka' => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castRdKafka'],
        \RdKafka\Conf::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castConf'],
        \RdKafka\KafkaConsumer::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castKafkaConsumer'],
        \RdKafka\Metadata\Broker::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castBrokerMetadata'],
        \RdKafka\Metadata\Collection::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castCollectionMetadata'],
        \RdKafka\Metadata\Partition::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castPartitionMetadata'],
        \RdKafka\Metadata\Topic::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castTopicMetadata'],
        \RdKafka\Message::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castMessage'],
        \RdKafka\Topic::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castTopic'],
        \RdKafka\TopicPartition::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castTopicPartition'],
        \RdKafka\TopicConf::class => [\Symfony\Component\VarDumper\Caster\RdKafkaCaster::class, 'castTopicConf'],

        \FFI\CData::class => [\Symfony\Component\VarDumper\Caster\FFICaster::class, 'castCTypeOrCData'],
        \FFI\CType::class => [\Symfony\Component\VarDumper\Caster\FFICaster::class, 'castCTypeOrCData'],
    ];

    protected int $maxItems = 2500;
    protected int $maxString = -1;
    protected int $minDepth = 1;

    /**
     * @var array<string, list<callable>>
     */
    private array $casters = [];

    /**
     * @var callable|null
     */
    private $prevErrorHandler;

    private array $classInfo = [];
    private int $filter = 0;

    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(?array $casters = null)
    {
        $this->addCasters($casters ?? static::$defaultCasters);
    }

    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public function addCasters(array $casters): void
    {
        foreach ($casters as $type => $callback) {
            $this->casters[$type][] = $callback;
        }
    }

    /**
     * Adds default casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public static function addDefaultCasters(array $casters): void
    {
        self::$defaultCasters = [...self::$defaultCasters, ...$casters];
    }

    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
     */
    public function setMaxItems(int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }

    /**
     * Sets the maximum cloned length for strings.
     */
    public function setMaxString(int $maxString): void
    {
        $this->maxString = $maxString;
    }

    /**
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     */
    public function setMinDepth(int $minDepth): void
    {
        $this->minDepth = $minDepth;
    }

    /**
     * Clones a PHP variable.
     *
     * @param int $filter A bit field of Caster::EXCLUDE_* constants
     */
    public function cloneVar(mixed $var, int $filter = 0): Data
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) {
            if (\E_RECOVERABLE_ERROR === $type || \E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }

            if ($this->prevErrorHandler) {
                return ($this->prevErrorHandler)($type, $msg, $file, $line, $context);
            }

            return false;
        });
        $this->filter = $filter;

        if ($gc = gc_enabled()) {
            gc_disable();
        }
        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }
            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }

    /**
     * Effectively clones the PHP variable.
     */
    abstract protected function doClone(mixed $var): array;

    /**
     * Casts an object to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castObject(Stub $stub, bool $isNested): array
    {
        $obj = $stub->value;
        $class = $stub->class;

        if (str_contains((string) $class, "@anonymous\0")) {
            $stub->class = get_debug_type($obj);
        }
        if (isset($this->classInfo[$class])) {
            [$i, $parents, $hasDebugInfo, $fileInfo] = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = [$class];
            $hasDebugInfo = method_exists($class, '__debugInfo');

            foreach (class_parents($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            foreach (class_implements($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            $parents[] = '*';

            $r = new \ReflectionClass($class);
            $fileInfo = $r->isInternal() || $r->isSubclassOf(Stub::class) ? [] : [
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            ];

            $this->classInfo[$class] = [$i, $parents, $hasDebugInfo, $fileInfo];
        }

        $stub->attr += $fileInfo;
        $a = Caster::castObject($obj, $class, $hasDebugInfo, $stub->class);

        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)] + $a;
        }

        return $a;
    }

    /**
     * Casts a resource to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castResource(Stub $stub, bool $isNested): array
    {
        $a = [];
        $res = $stub->value;
        $type = $stub->class;

        try {
            if (!empty($this->casters[':'.$type])) {
                foreach ($this->casters[':'.$type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '').'⚠' => new ThrowingCasterException($e)] + $a;
        }

        return $a;
    }
}
