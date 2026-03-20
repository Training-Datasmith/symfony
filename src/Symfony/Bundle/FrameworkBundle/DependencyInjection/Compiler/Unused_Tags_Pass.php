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
namespace Symfony\Bundle\Framework_Bundle\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Compiler\Compiler_Pass_Interface;
use Symfony\Component\Dependency_Injection\Container_Builder;
/**
 * Find all service tags which are defined, but not used and yield a warning log message.
 *
 * @author Florian Pfitzer <pfitzer@wurzel3.de>
 */
class Unused_Tags_Pass implements Compiler_Pass_Interface
{
    private const KNOWN_TAGS = ['asset_mapper.compiler', 'assets.package', 'auto_alias', 'cache.pool', 'cache.pool.clearer', 'cache.taggable', 'chatter.transport_factory', 'config_cache.resource_checker', 'console.argument_value_resolver', 'console.command', 'console.command.service_arguments', 'container.env_var_loader', 'container.env_var_processor', 'container.excluded', 'container.hot_path', 'container.no_preload', 'container.preload', 'container.private', 'container.reversible', 'container.service_locator', 'container.service_locator_context', 'container.service_subscriber', 'container.stack', 'controller.argument_value_resolver', 'controller.service_arguments', 'controller.targeted_value_resolver', 'data_collector', 'event_dispatcher.dispatcher', 'form.type', 'form.type_extension', 'form.type_guesser', 'html_sanitizer', 'http_client.client', 'json_streamer.value_transformer', 'kernel.cache_clearer', 'kernel.cache_warmer', 'kernel.event_listener', 'kernel.event_subscriber', 'kernel.fragment_renderer', 'kernel.locale_aware', 'kernel.reset', 'ldap', 'mailer.transport_factory', 'messenger.bus', 'messenger.message_handler', 'messenger.receiver', 'messenger.transport_factory', 'mime.mime_type_guesser', 'monolog.logger', 'notifier.channel', 'object_mapper.condition_callable', 'object_mapper.transform_callable', 'property_info.access_extractor', 'property_info.constructor_extractor', 'property_info.initializable_extractor', 'property_info.list_extractor', 'property_info.type_extractor', 'proxy', 'remote_event.consumer', 'routing.condition_service', 'routing.controller', 'routing.expression_language_function', 'routing.expression_language_provider', 'routing.loader', 'routing.route_loader', 'scheduler.schedule_provider', 'scheduler.task', 'security.access_token_handler.oidc.encryption_algorithm', 'security.access_token_handler.oidc.signature_algorithm', 'security.authenticator.login_linker', 'security.expression_language_provider', 'security.remember_me_handler', 'security.voter', 'serializer.encoder', 'serializer.normalizer', 'texter.transport_factory', 'translation.dumper', 'translation.extractor', 'translation.extractor.visitor', 'translation.loader', 'translation.provider_factory', 'twig.extension', 'twig.loader', 'twig.runtime', 'validator.attribute_metadata', 'validator.auto_mapper', 'validator.constraint_validator', 'validator.group_provider', 'validator.initializer', 'workflow'];
    public function process(Container_Builder $container): void
    {
        $tags = array_unique(array_merge($container->find_tags(), self::KNOWN_TAGS));
        foreach ($container->find_unused_tags() as $tag) {
            // skip known tags
            if (\in_array($tag, self::KNOWN_TAGS, true)) {
                continue;
            }
            // check for typos
            $candidates = [];
            foreach ($tags as $defined_tag) {
                if ($defined_tag === $tag) {
                    continue;
                }
                if (str_contains($defined_tag, $tag) || levenshtein($tag, $defined_tag) <= \strlen($tag) / 3) {
                    $candidates[] = $defined_tag;
                }
            }
            $services = array_keys($container->find_tagged_service_ids($tag));
            $message = \sprintf('Tag "%s" was defined on service(s) "%s", but was never used.', $tag, implode('", "', $services));
            if ($candidates) {
                $message .= \sprintf(' Did you mean "%s"?', implode('", "', $candidates));
            }
            $container->log($this, $message);
        }
    }
}