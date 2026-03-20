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
namespace Symfony\Bridge\Doctrine\Validator\Constraints;

use Symfony\Component\Validator\Attribute\Has_Named_Arguments;
use Symfony\Component\Validator\Constraint;
/**
 * Constraint for the Unique Entity validator.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class Unique_Entity extends Constraint
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077f';
    protected const ERROR_NAMES = [self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR'];
    public string $message = 'This value is already used.';
    public string $service = 'doctrine.orm.validator.unique';
    public ?string $em = null;
    public ?string $entity_class = null;
    public string $repository_method = 'findBy';
    public array|string $fields = [];
    public ?string $error_path = null;
    public bool|array|string $ignore_null = true;
    public array $identifier_field_names = [];
    /**
     * @param array|string         $fields           The combination of fields that must contain unique values or a set of options
     * @param bool|string[]|string $ignoreNull       The combination of fields that ignore null values
     * @param string|null          $em               The entity manager used to query for uniqueness instead of the manager of this class
     * @param string|null          $entityClass      The entity class to enforce uniqueness on instead of the current class
     * @param string|null          $repositoryMethod The repository method to check uniqueness instead of findBy. The method will receive as its argument
     *                                               a fieldName => value associative array according to the fields option configuration
     * @param string|null          $errorPath        Bind the constraint violation to this field instead of the first one in the fields option configuration
     */
    #[Has_Named_Arguments]
    public function __construct(array|string $fields, ?string $message = null, ?string $service = null, ?string $em = null, ?string $entity_class = null, ?string $repository_method = null, ?string $error_path = null, bool|string|array|null $ignore_null = null, ?array $identifier_field_names = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct(null, $groups, $payload);
        $this->fields = $fields ?? $this->fields;
        $this->message = $message ?? $this->message;
        $this->service = $service ?? $this->service;
        $this->em = $em ?? $this->em;
        $this->entity_class = $entity_class ?? $this->entity_class;
        $this->repository_method = $repository_method ?? $this->repository_method;
        $this->error_path = $error_path ?? $this->error_path;
        $this->ignore_null = $ignore_null ?? $this->ignore_null;
        $this->identifier_field_names = $identifier_field_names ?? $this->identifier_field_names;
    }
    /**
     * The validator must be defined as a service with this name.
     */
    public function validated_by(): string
    {
        return $this->service;
    }
    public function get_targets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}