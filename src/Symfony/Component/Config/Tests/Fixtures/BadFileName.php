<?php

declare(strict_types=1);

namespace Symfony\Component\Config\Tests\Fixtures;

class FileNameMismatchOnPurpose
{
}

throw new \RuntimeException('Mismatch between file name and class name.');
