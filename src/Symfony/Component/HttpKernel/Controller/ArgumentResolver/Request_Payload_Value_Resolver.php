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
namespace Symfony\Component\Http_Kernel\Controller\Argument_Resolver;

use Symfony\Component\Event_Dispatcher\Event_Subscriber_Interface;
use Symfony\Component\Expression_Language\Expression;
use Symfony\Component\Expression_Language\Expression_Language;
use Symfony\Component\Http_Foundation\File\Uploaded_File;
use Symfony\Component\Http_Foundation\Request;
use Symfony\Component\Http_Kernel\Attribute\Map_Query_String;
use Symfony\Component\Http_Kernel\Attribute\Map_Request_Payload;
use Symfony\Component\Http_Kernel\Attribute\Map_Uploaded_File;
use Symfony\Component\Http_Kernel\Controller\Value_Resolver_Interface;
use Symfony\Component\Http_Kernel\Controller_Metadata\Argument_Metadata;
use Symfony\Component\Http_Kernel\Event\Controller_Arguments_Event;
use Symfony\Component\Http_Kernel\Exception\Bad_Request_Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Http_Exception;
use Symfony\Component\Http_Kernel\Exception\Near_Miss_Value_Resolver_Exception;
use Symfony\Component\Http_Kernel\Exception\Unsupported_Media_Type_Http_Exception;
use Symfony\Component\Http_Kernel\Kernel_Events;
use Symfony\Component\Serializer\Exception\InvalidArgumentException as SerializerInvalidArgumentException;
use Symfony\Component\Serializer\Exception\Not_Encodable_Value_Exception;
use Symfony\Component\Serializer\Exception\Partial_Denormalization_Exception;
use Symfony\Component\Serializer\Exception\Unexpected_Property_Exception;
use Symfony\Component\Serializer\Exception\Unsupported_Format_Exception;
use Symfony\Component\Serializer\Normalizer\Denormalizer_Interface;
use Symfony\Component\Serializer\Serializer_Interface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Group_Sequence;
use Symfony\Component\Validator\Constraint_Violation;
use Symfony\Component\Validator\Constraint_Violation_List;
use Symfony\Component\Validator\Exception\Validation_Failed_Exception;
use Symfony\Component\Validator\Validator\Validator_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 *
 * @psalm-type GroupResolver = \Closure(array<string, mixed>, Request, ?object):string|GroupSequence|array<string>
 *
 * @final
 */
class Request_Payload_Value_Resolver implements Value_Resolver_Interface, Event_Subscriber_Interface
{
    /**
     * @see DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS
     */
    private const CONTEXT_DENORMALIZE = ['collect_denormalization_errors' => true];
    /**
     * @see DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS
     */
    private const CONTEXT_DESERIALIZE = ['collect_denormalization_errors' => true];
    public function __construct(private readonly Serializer_Interface&Denormalizer_Interface $serializer, private readonly ?Validator_Interface $validator = null, private readonly ?Translator_Interface $translator = null, private readonly string $translation_domain = 'validators', private readonly ?Expression_Language $expression_language = null)
    {
    }
    public function resolve(Request $request, Argument_Metadata $argument): iterable
    {
        $attribute = $argument->get_attributes_of_type(Map_Query_String::class, Argument_Metadata::IS_INSTANCEOF)[0] ?? $argument->get_attributes_of_type(Map_Request_Payload::class, Argument_Metadata::IS_INSTANCEOF)[0] ?? $argument->get_attributes_of_type(Map_Uploaded_File::class, Argument_Metadata::IS_INSTANCEOF)[0] ?? null;
        if (!$attribute) {
            return [];
        }
        if ($attribute instanceof Map_Query_String && $argument->is_variadic()) {
            throw new \LogicException(\sprintf('Mapping variadic argument "$%s" is not supported.', $argument->get_name()));
        }
        if ($attribute instanceof Map_Request_Payload) {
            if ('array' === $argument->get_type()) {
                if (!$attribute->type) {
                    throw new Near_Miss_Value_Resolver_Exception(\sprintf('Please set the $type argument of the #[%s] attribute to the type of the objects in the expected array.', Map_Request_Payload::class));
                }
            } elseif ($attribute->type && !$argument->is_variadic()) {
                throw new Near_Miss_Value_Resolver_Exception(\sprintf('Please set its type to "array" when using argument $type of #[%s].', Map_Request_Payload::class));
            }
        }
        $attribute->metadata = $argument;
        return [$attribute];
    }
    public function on_kernel_controller_arguments(Controller_Arguments_Event $event): void
    {
        $arguments = $event->get_arguments();
        foreach ($arguments as $i => $argument) {
            if ($argument instanceof Map_Query_String) {
                $payload_mapper = $this->map_query_string(...);
                $validation_failed_code = $argument->validation_failed_status_code;
            } elseif ($argument instanceof Map_Request_Payload) {
                $payload_mapper = $this->map_request_payload(...);
                $validation_failed_code = $argument->validation_failed_status_code;
            } elseif ($argument instanceof Map_Uploaded_File) {
                $payload_mapper = $this->map_uploaded_file(...);
                $validation_failed_code = $argument->validation_failed_status_code;
            } else {
                continue;
            }
            $request = $event->get_request();
            if (!$argument->metadata->get_type()) {
                throw new \LogicException(\sprintf('Could not resolve the "$%s" controller argument: argument should be typed.', $argument->metadata->get_name()));
            }
            if ($this->validator) {
                $violations = new Constraint_Violation_List();
                try {
                    $payload = $payload_mapper($request, $argument->metadata, $argument);
                } catch (Partial_Denormalization_Exception $e) {
                    $trans = $this->translator ? $this->translator->trans(...) : strtr(...);
                    foreach ($e->get_errors() as $error) {
                        $parameters = [];
                        $template = 'This value was of an unexpected type.';
                        if ($expected_types = $error->get_expected_types()) {
                            $template = 'This value should be of type {{ type }}.';
                            $parameters['{{ type }}'] = implode('|', $expected_types);
                        }
                        if ($error->can_use_message_for_user()) {
                            $parameters['hint'] = $error->get_message();
                        }
                        $message = $trans($template, $parameters, $this->translation_domain);
                        $violations->add(new Constraint_Violation($message, $template, $parameters, null, $error->get_path(), null));
                    }
                    $payload = $e->get_data();
                } catch (Serializer_Invalid_Argument_Exception $e) {
                    $violations->add(new Constraint_Violation($e->get_message(), $e->get_message(), [], null, '', null));
                    $payload = null;
                }
                if (null !== $payload && !\count($violations)) {
                    $constraints = $argument->constraints ?? null;
                    if (\is_array($payload) && !empty($constraints) && !$constraints instanceof Assert\All) {
                        $constraints = new Assert\All($constraints);
                    }
                    $groups = $this->resolve_validation_groups($argument->validation_groups ?? null, $event);
                    $violations->add_all($this->validator->validate($payload, $constraints, $groups));
                }
                if (\count($violations)) {
                    throw Http_Exception::from_status_code($validation_failed_code, implode("\n", array_map(static fn(\Symfony\Component\Validator\Constraint_Violation_Interface $e): string|\Stringable => $e->get_message(), iterator_to_array($violations))), new Validation_Failed_Exception($payload, $violations));
                }
            } else {
                try {
                    $payload = $payload_mapper($request, $argument->metadata, $argument);
                } catch (Partial_Denormalization_Exception $e) {
                    throw Http_Exception::from_status_code($validation_failed_code, implode("\n", array_map(static fn(\Symfony\Component\Serializer\Exception\Not_Normalizable_Value_Exception $e): string => $e->get_message(), $e->get_errors())), $e);
                } catch (Serializer_Invalid_Argument_Exception $e) {
                    throw Http_Exception::from_status_code($validation_failed_code, $e->get_message(), $e);
                }
            }
            if ($argument->metadata->is_variadic()) {
                array_splice($arguments, $i, 1, $payload ?? []);
                continue;
            }
            if (null === $payload) {
                $payload = match (true) {
                    $argument->metadata->has_default_value() => $argument->metadata->get_default_value(),
                    $argument->metadata->is_nullable() => null,
                    default => throw Http_Exception::from_status_code($validation_failed_code),
                };
            }
            $arguments[$i] = $payload;
        }
        $event->set_arguments($arguments);
    }
    public static function get_subscribed_events(): array
    {
        return [Kernel_Events::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments'];
    }
    private function map_query_string(Request $request, Argument_Metadata $argument, Map_Query_String $attribute): ?object
    {
        if (!($data = $request->query->all($attribute->key)) && ($argument->is_nullable() || $argument->has_default_value())) {
            return null;
        }
        return $this->serializer->denormalize($data, $argument->get_type(), 'csv', $attribute->serialization_context + self::CONTEXT_DENORMALIZE + ['filter_bool' => true]);
    }
    private function map_request_payload(Request $request, Argument_Metadata $argument, Map_Request_Payload $attribute): object|array|null
    {
        if ('' === ($data = $request->request->all() ?: $request->get_content()) && ($argument->is_nullable() || $argument->has_default_value())) {
            return null;
        }
        if (null === $format = $request->get_content_type_format()) {
            throw new Unsupported_Media_Type_Http_Exception('Unsupported format.');
        }
        if ($attribute->accept_format && !\in_array($format, (array) $attribute->accept_format, true)) {
            throw new Unsupported_Media_Type_Http_Exception(\sprintf('Unsupported format, expects "%s", but "%s" given.', implode('", "', (array) $attribute->accept_format), $format));
        }
        $type = match (true) {
            $argument->is_variadic() => ($attribute->type ?? $argument->get_type()) . '[]',
            'array' === $argument->get_type() && null !== $attribute->type => $attribute->type . '[]',
            default => $argument->get_type(),
        };
        if (\is_array($data)) {
            $data = $this->merge_params_and_files($data, $request->files->all());
            return $this->serializer->denormalize($data, $type, self::has_non_string_scalar($data) ? $format : 'csv', $attribute->serialization_context + self::CONTEXT_DENORMALIZE + ('form' === $format ? ['filter_bool' => true] : []));
        }
        if ('form' === $format) {
            throw new Bad_Request_Http_Exception('Request payload contains invalid "form" data.');
        }
        try {
            return $this->serializer->deserialize($data, $type, $format, self::CONTEXT_DESERIALIZE + $attribute->serialization_context);
        } catch (Unsupported_Format_Exception $e) {
            throw new Unsupported_Media_Type_Http_Exception(\sprintf('Unsupported format: "%s".', $format), $e);
        } catch (Not_Encodable_Value_Exception $e) {
            throw new Bad_Request_Http_Exception(\sprintf('Request payload contains invalid "%s" data.', $format), $e);
        } catch (Unexpected_Property_Exception $e) {
            throw new Bad_Request_Http_Exception(\sprintf('Request payload contains invalid "%s" property.', $e->property), $e);
        }
    }
    private function map_uploaded_file(Request $request, Argument_Metadata $argument, Map_Uploaded_File $attribute): Uploaded_File|array|null
    {
        if ($files = $request->files->get($attribute->name ?? $argument->get_name())) {
            return !\is_array($files) && $argument->is_variadic() ? [$files] : $files;
        }
        if ($argument->is_nullable() || $argument->has_default_value()) {
            return null;
        }
        return 'array' === $argument->get_type() ? [] : null;
    }
    private function merge_params_and_files(array $params, array $files): array
    {
        $is_files_list = array_is_list($files);
        foreach ($params as $key => $value) {
            if (\is_array($value) && \is_array($files[$key] ?? null)) {
                $params[$key] = $this->merge_params_and_files($value, $files[$key]);
                unset($files[$key]);
            }
        }
        if (!$is_files_list) {
            return array_replace($params, $files);
        }
        foreach ($files as $value) {
            $params[] = $value;
        }
        return $params;
    }
    private function resolve_validation_groups(Expression|string|Group_Sequence|\Closure|array|null $validation_groups, Controller_Arguments_Event $event): string|Group_Sequence|array|null
    {
        if ($validation_groups instanceof Expression || $validation_groups instanceof \Closure) {
            $validation_groups = $event->evaluate($validation_groups, $this->expression_language);
        }
        if (null === $validation_groups || \is_string($validation_groups) || $validation_groups instanceof Group_Sequence) {
            return $validation_groups;
        }
        if (!\is_array($validation_groups)) {
            throw new \LogicException('The validation groups expression or closure must return a string, an array of strings, or a GroupSequence.');
        }
        foreach ($validation_groups as $group) {
            if ($group instanceof Expression) {
                throw new \LogicException('Nested expressions in validation groups are not supported. Use a single Expression or a list of strings (or a GroupSequence) instead.');
            }
            if ($group instanceof \Closure) {
                throw new \LogicException('Nested closures in validation groups are not supported. Use a single Closure or a list of strings (or a GroupSequence) instead.');
            }
            if ($group instanceof Group_Sequence) {
                throw new \LogicException('GroupSequence cannot be used inside an array of validation groups. Pass the GroupSequence as the top-level validationGroups value instead.');
            }
            if (!\is_string($group)) {
                throw new \LogicException('Validation groups must be strings.');
            }
        }
        return $validation_groups;
    }
    private static function has_non_string_scalar(array $data): bool
    {
        $stack = [$data];
        while ($stack) {
            foreach (array_pop($stack) as $v) {
                if (\is_array($v)) {
                    $stack[] = $v;
                } elseif (!\is_string($v)) {
                    return true;
                }
            }
        }
        return false;
    }
}