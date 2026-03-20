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

use Symfony\Component\Form\Extension\Csrf\Type\Form_Type_Csrf_Extension;
return static function (Container_Configurator $container): void {
    $container->services()->set('form.type_extension.csrf', Form_Type_Csrf_Extension::class)->args([service('security.csrf.token_manager'), param('form.type_extension.csrf.enabled'), param('form.type_extension.csrf.field_name'), service('translator')->null_on_invalid(), param('validator.translation_domain'), service('form.server_params'), param('form.type_extension.csrf.field_attr'), param('.form.type_extension.csrf.token_id')])->tag('form.type_extension');
};