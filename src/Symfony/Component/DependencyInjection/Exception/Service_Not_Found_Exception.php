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
namespace Symfony\Component\Dependency_Injection\Exception;

use Psr\Container\Not_Found_Exception_Interface;
/**
 * This exception is thrown when a non-existent service is requested.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Service_Not_Found_Exception extends InvalidArgumentException implements Not_Found_Exception_Interface
{
    public function __construct(private readonly string $id, private readonly ?string $source_id = null, ?\Throwable $previous = null, private readonly array $alternatives = [], ?string $msg = null)
    {
        if (null !== $msg) {
            // no-op
        } elseif (null === $source_id) {
            $msg = \sprintf('You have requested a non-existent service "%s".', $id);
        } else {
            $msg = \sprintf('The service "%s" has a dependency on a non-existent service "%s".', $source_id, $id);
        }
        if ($alternatives) {
            if (1 == \count($alternatives)) {
                $msg .= ' Did you mean this: "';
            } else {
                $msg .= ' Did you mean one of these: "';
            }
            $msg .= implode('", "', $alternatives) . '"?';
        }
        parent::__construct($msg, 0, $previous);
    }
    public function get_id(): string
    {
        return $this->id;
    }
    public function get_source_id(): ?string
    {
        return $this->source_id;
    }
    public function get_alternatives(): array
    {
        return $this->alternatives;
    }
}