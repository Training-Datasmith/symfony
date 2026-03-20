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
namespace Symfony\Bridge\Twig\Translation;

use Symfony\Bridge\Twig\Extension\Translation_Extension;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\Abstract_File_Extractor;
use Symfony\Component\Translation\Extractor\Extractor_Interface;
use Symfony\Component\Translation\Message_Catalogue;
use Twig\Environment;
use Twig\Error\Error;
use Twig\Source;
/**
 * TwigExtractor extracts translation messages from a twig template.
 *
 * @author Michel Salib <michelsalib@hotmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Extractor extends Abstract_File_Extractor implements Extractor_Interface
{
    /**
     * Default domain for found messages.
     */
    private string $default_domain = 'messages';
    /**
     * Prefix for found message.
     */
    private string $prefix = '';
    public function __construct(private readonly Environment $twig)
    {
    }
    public function extract($resource, Message_Catalogue $catalogue): void
    {
        foreach ($this->extract_files($resource) as $file) {
            try {
                $this->extract_template(file_get_contents($file->get_pathname()), $catalogue);
            } catch (Error) {
                // ignore errors, these should be fixed by using the linter
            }
        }
    }
    public function set_prefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }
    protected function extract_template(string $template, Message_Catalogue $catalogue): void
    {
        $visitor = $this->twig->get_extension(Translation_Extension::class)->get_translation_node_visitor();
        $visitor->enable();
        $this->twig->parse($this->twig->tokenize(new Source($template, '')));
        foreach ($visitor->get_messages() as $message) {
            $catalogue->set(trim((string) $message[0]), $this->prefix . trim((string) $message[0]), $message[1] ?: $this->default_domain);
        }
        $visitor->disable();
    }
    protected function can_be_extracted(string $file): bool
    {
        return $this->is_file($file) && 'twig' === pathinfo($file, \PATHINFO_EXTENSION);
    }
    protected function extract_from_directory($directory): iterable
    {
        $finder = new Finder();
        return $finder->files()->name('*.twig')->in($directory);
    }
}