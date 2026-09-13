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

namespace Symfony\Component\Translation\Dumper;

use Symfony\Component\Translation\MessageCatalogue;

/**
 * PhpFileDumper generates PHP files from a message catalogue.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 */
class PhpFileDumper extends FileDumper
{
    public function formatCatalogue(MessageCatalogue $messages, string $domain, array $options = []): string
    {
        $exported = var_export($messages->all($domain), true);
        $exported = preg_replace('/^array \(/', '[', $exported);
        $exported = preg_replace('/\)$/', ']', (string) $exported);

        return "<?php\n\ndeclare(strict_types=1);\n\nreturn  ".$exported.";\n";
    }

    protected function getExtension(): string
    {
        return 'php';
    }
}
