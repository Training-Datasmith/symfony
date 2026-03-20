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
namespace Symfony\Component\Dom_Crawler\Field;

/**
 * FormField is the abstract class for all form fields.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Form_Field
{
    protected string $name;
    protected string|array|null $value = null;
    protected \Dom_Document $document;
    protected \Domx_Path $xpath;
    protected bool $disabled = false;
    /**
     * @param \DOMElement $node The node associated with this field
     */
    public function __construct(protected \Dom_Element $node)
    {
        $this->name = $node->get_attribute('name');
        $this->xpath = new \Domx_Path($node->owner_document);
        $this->initialize();
    }
    /**
     * Returns the label tag associated to the field or null if none.
     */
    public function get_label(): ?\Dom_Element
    {
        $xpath = new \Domx_Path($this->node->owner_document);
        if ($this->node->has_attribute('id')) {
            $labels = $xpath->query(\sprintf('descendant::label[@for="%s"]', $this->node->get_attribute('id')));
            if ($labels->length > 0) {
                return $labels->item(0);
            }
        }
        $labels = $xpath->query('ancestor::label[1]', $this->node);
        return $labels->length > 0 ? $labels->item(0) : null;
    }
    /**
     * Returns the name of the field.
     */
    public function get_name(): string
    {
        return $this->name;
    }
    /**
     * Gets the value of the field.
     */
    public function get_value(): string|array|null
    {
        return $this->value;
    }
    /**
     * Sets the value of the field.
     */
    public function set_value(?string $value): void
    {
        $this->value = $value ?? '';
    }
    /**
     * Returns true if the field should be included in the submitted values.
     */
    public function has_value(): bool
    {
        return true;
    }
    /**
     * Check if the current field is disabled.
     */
    public function is_disabled(): bool
    {
        return $this->node->has_attribute('disabled');
    }
    /**
     * Initializes the form field.
     */
    abstract protected function initialize(): void;
}