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
namespace Symfony\Component\Http_Client\Chunk;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class Informational_Chunk extends Data_Chunk
{
    private readonly array $status;
    public function __construct(int $status_code, array $headers)
    {
        $this->status = [$status_code, $headers];
        parent::__construct();
    }
    public function get_informational_status(): ?array
    {
        return $this->status;
    }
}