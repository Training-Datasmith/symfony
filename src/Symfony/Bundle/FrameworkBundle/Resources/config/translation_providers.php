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

use Symfony\Component\Translation\Bridge\Crowdin\Crowdin_Provider_Factory;
use Symfony\Component\Translation\Bridge\Loco\Loco_Provider_Factory;
use Symfony\Component\Translation\Bridge\Lokalise\Lokalise_Provider_Factory;
use Symfony\Component\Translation\Bridge\Phrase\Phrase_Provider_Factory;
use Symfony\Component\Translation\Provider\Null_Provider_Factory;
use Symfony\Component\Translation\Provider\Translation_Provider_Collection;
use Symfony\Component\Translation\Provider\Translation_Provider_Collection_Factory;
return static function (Container_Configurator $container): void {
    $container->services()->set('translation.provider_collection', Translation_Provider_Collection::class)->factory([service('translation.provider_collection_factory'), 'fromConfig'])->args([[]])->set('translation.provider_collection_factory', Translation_Provider_Collection_Factory::class)->args([tagged_iterator('translation.provider_factory'), []])->set('translation.provider_factory.null', Null_Provider_Factory::class)->tag('translation.provider_factory')->set('translation.provider_factory.crowdin', Crowdin_Provider_Factory::class)->args([service('http_client'), service('logger'), param('kernel.default_locale'), service('translation.loader.xliff'), service('translation.dumper.xliff')])->tag('translation.provider_factory')->set('translation.provider_factory.loco', Loco_Provider_Factory::class)->args([service('http_client'), service('logger'), param('kernel.default_locale'), service('translation.loader.xliff'), service('translator')])->tag('translation.provider_factory')->set('translation.provider_factory.lokalise', Lokalise_Provider_Factory::class)->args([service('http_client'), service('logger'), param('kernel.default_locale'), service('translation.loader.xliff')])->tag('translation.provider_factory')->set('translation.provider_factory.phrase', Phrase_Provider_Factory::class)->args([service('http_client'), service('logger'), service('translation.loader.xliff'), service('translation.dumper.xliff'), service('cache.app'), param('kernel.default_locale')])->tag('translation.provider_factory');
};