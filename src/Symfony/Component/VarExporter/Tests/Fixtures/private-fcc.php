<?php

declare(strict_types=1);

return new \ReflectionMethod(\Symfony\Component\VarExporter\Tests\Fixtures\PrivateFCC::class, 'testMethod')->getClosure();
