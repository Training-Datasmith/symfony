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
namespace Symfony\Component\Dependency_Injection\Compiler;

use Symfony\Component\Dependency_Injection\Definition;
/**
 * Replaces env var placeholders by their current values.
 */
class Resolve_Env_Placeholders_Pass extends Abstract_Recursive_Pass
{
    protected bool $skip_scalars = false;
    /**
     * @param string|true|null $format A sprintf() format returning the replacement for each env var name or
     *                                 null to resolve back to the original "%env(VAR)%" format or
     *                                 true to resolve to the actual values of the referenced env vars
     */
    public function __construct(private readonly string|bool|null $format = true)
    {
    }
    protected function process_value(mixed $value, bool $is_root = false): mixed
    {
        if (\is_string($value)) {
            return $this->container->resolve_env_placeholders($value, $this->format);
        }
        if ($value instanceof Definition) {
            $changes = $value->get_changes();
            if (isset($changes['class'])) {
                $value->set_class($this->container->resolve_env_placeholders($value->get_class(), $this->format));
            }
            if (isset($changes['file'])) {
                $value->set_file($this->container->resolve_env_placeholders($value->get_file(), $this->format));
            }
        }
        $value = parent::process_value($value, $is_root);
        if ($value && \is_array($value) && !$is_root) {
            return array_combine($this->container->resolve_env_placeholders(array_keys($value), $this->format), $value);
        }
        return $value;
    }
}