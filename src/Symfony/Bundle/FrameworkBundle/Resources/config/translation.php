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
namespace Symfony\Component\Dependency_Injection\Loader\Configurator;

use Psr\Container\Container_Interface;
use Symfony\Bundle\Framework_Bundle\Cache_Warmer\Translations_Cache_Warmer;
use Symfony\Bundle\Framework_Bundle\Translation\Translator;
use Symfony\Component\Translation\Dumper\Csv_File_Dumper;
use Symfony\Component\Translation\Dumper\Icu_Res_File_Dumper;
use Symfony\Component\Translation\Dumper\Ini_File_Dumper;
use Symfony\Component\Translation\Dumper\Json_File_Dumper;
use Symfony\Component\Translation\Dumper\Mo_File_Dumper;
use Symfony\Component\Translation\Dumper\Php_File_Dumper;
use Symfony\Component\Translation\Dumper\Po_File_Dumper;
use Symfony\Component\Translation\Dumper\Qt_File_Dumper;
use Symfony\Component\Translation\Dumper\Xliff_File_Dumper;
use Symfony\Component\Translation\Dumper\Yaml_File_Dumper;
use Symfony\Component\Translation\Extractor\Chain_Extractor;
use Symfony\Component\Translation\Extractor\Extractor_Interface;
use Symfony\Component\Translation\Extractor\Php_Ast_Extractor;
use Symfony\Component\Translation\Extractor\Visitor\Constraint_Visitor;
use Symfony\Component\Translation\Extractor\Visitor\Translatable_Message_Visitor;
use Symfony\Component\Translation\Extractor\Visitor\Trans_Method_Visitor;
use Symfony\Component\Translation\Formatter\Message_Formatter;
use Symfony\Component\Translation\Loader\Csv_File_Loader;
use Symfony\Component\Translation\Loader\Icu_Dat_File_Loader;
use Symfony\Component\Translation\Loader\Icu_Res_File_Loader;
use Symfony\Component\Translation\Loader\Ini_File_Loader;
use Symfony\Component\Translation\Loader\Json_File_Loader;
use Symfony\Component\Translation\Loader\Mo_File_Loader;
use Symfony\Component\Translation\Loader\Php_File_Loader;
use Symfony\Component\Translation\Loader\Po_File_Loader;
use Symfony\Component\Translation\Loader\Qt_File_Loader;
use Symfony\Component\Translation\Loader\Xliff_File_Loader;
use Symfony\Component\Translation\Loader\Yaml_File_Loader;
use Symfony\Component\Translation\Locale_Switcher;
use Symfony\Component\Translation\Logging_Translator;
use Symfony\Component\Translation\Reader\Translation_Reader;
use Symfony\Component\Translation\Reader\Translation_Reader_Interface;
use Symfony\Component\Translation\Writer\Translation_Writer;
use Symfony\Component\Translation\Writer\Translation_Writer_Interface;
use Symfony\Contracts\Translation\Locale_Aware_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
return static function (Container_Configurator $container): void {
    $container->services()->set('translator.default', Translator::class)->args([abstract_arg('translation loaders locator'), service('translator.formatter'), param('kernel.default_locale'), abstract_arg('translation loaders ids'), ['cache_dir' => param('kernel.cache_dir') . '/translations', 'debug' => param('kernel.debug')], abstract_arg('enabled locales')])->call('setConfigCacheFactory', [service('config_cache_factory')])->tag('kernel.locale_aware')->alias(Translator_Interface::class, 'translator')->set('translator.logging', Logging_Translator::class)->args([service('translator.logging.inner'), service('logger')])->tag('monolog.logger', ['channel' => 'translation'])->set('translator.formatter.default', Message_Formatter::class)->args([service('identity_translator')])->set('translation.loader.php', Php_File_Loader::class)->tag('translation.loader', ['alias' => 'php'])->set('translation.loader.yml', Yaml_File_Loader::class)->tag('translation.loader', ['alias' => 'yaml', 'legacy-alias' => 'yml'])->set('translation.loader.xliff', Xliff_File_Loader::class)->tag('translation.loader', ['alias' => 'xlf', 'legacy-alias' => 'xliff'])->set('translation.loader.po', Po_File_Loader::class)->tag('translation.loader', ['alias' => 'po'])->set('translation.loader.mo', Mo_File_Loader::class)->tag('translation.loader', ['alias' => 'mo'])->set('translation.loader.qt', Qt_File_Loader::class)->tag('translation.loader', ['alias' => 'ts'])->set('translation.loader.csv', Csv_File_Loader::class)->tag('translation.loader', ['alias' => 'csv'])->set('translation.loader.res', Icu_Res_File_Loader::class)->tag('translation.loader', ['alias' => 'res'])->set('translation.loader.dat', Icu_Dat_File_Loader::class)->tag('translation.loader', ['alias' => 'dat'])->set('translation.loader.ini', Ini_File_Loader::class)->tag('translation.loader', ['alias' => 'ini'])->set('translation.loader.json', Json_File_Loader::class)->tag('translation.loader', ['alias' => 'json'])->set('translation.dumper.php', Php_File_Dumper::class)->tag('translation.dumper', ['alias' => 'php'])->set('translation.dumper.xliff', Xliff_File_Dumper::class)->tag('translation.dumper', ['alias' => 'xlf'])->set('translation.dumper.xliff.xliff', Xliff_File_Dumper::class)->args(['xliff'])->tag('translation.dumper', ['alias' => 'xliff'])->set('translation.dumper.po', Po_File_Dumper::class)->tag('translation.dumper', ['alias' => 'po'])->set('translation.dumper.mo', Mo_File_Dumper::class)->tag('translation.dumper', ['alias' => 'mo'])->set('translation.dumper.yml', Yaml_File_Dumper::class)->tag('translation.dumper', ['alias' => 'yml'])->set('translation.dumper.yaml', Yaml_File_Dumper::class)->args(['yaml'])->tag('translation.dumper', ['alias' => 'yaml'])->set('translation.dumper.qt', Qt_File_Dumper::class)->tag('translation.dumper', ['alias' => 'ts'])->set('translation.dumper.csv', Csv_File_Dumper::class)->tag('translation.dumper', ['alias' => 'csv'])->set('translation.dumper.ini', Ini_File_Dumper::class)->tag('translation.dumper', ['alias' => 'ini'])->set('translation.dumper.json', Json_File_Dumper::class)->tag('translation.dumper', ['alias' => 'json'])->set('translation.dumper.res', Icu_Res_File_Dumper::class)->tag('translation.dumper', ['alias' => 'res'])->set('translation.extractor.php_ast', Php_Ast_Extractor::class)->args([tagged_iterator('translation.extractor.visitor')])->tag('translation.extractor', ['alias' => 'php'])->set('translation.extractor.visitor.trans_method', Trans_Method_Visitor::class)->tag('translation.extractor.visitor')->set('translation.extractor.visitor.translatable_message', Translatable_Message_Visitor::class)->tag('translation.extractor.visitor')->set('translation.extractor.visitor.constraint', Constraint_Visitor::class)->tag('translation.extractor.visitor')->set('translation.reader', Translation_Reader::class)->alias(Translation_Reader_Interface::class, 'translation.reader')->set('translation.extractor', Chain_Extractor::class)->alias(Extractor_Interface::class, 'translation.extractor')->set('translation.writer', Translation_Writer::class)->alias(Translation_Writer_Interface::class, 'translation.writer')->set('translation.warmer', Translations_Cache_Warmer::class)->args([service(Container_Interface::class)])->tag('container.service_subscriber', ['id' => 'translator'])->tag('kernel.cache_warmer')->set('translation.locale_switcher', Locale_Switcher::class)->args([param('kernel.default_locale'), tagged_iterator('kernel.locale_aware', exclude: 'translation.locale_switcher'), service('router.request_context')->ignore_on_invalid()])->tag('kernel.reset', ['method' => 'reset'])->tag('kernel.locale_aware')->alias(Locale_Aware_Interface::class, 'translation.locale_switcher')->alias(Locale_Switcher::class, 'translation.locale_switcher');
};