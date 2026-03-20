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
namespace Symfony\Component\Dom_Crawler;

use Symfony\Component\Dom_Crawler\Field\Choice_Form_Field;
use Symfony\Component\Dom_Crawler\Field\Form_Field;
/**
 * Form represents an HTML form.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Form extends Link implements \ArrayAccess
{
    private \Dom_Element $button;
    private Form_Field_Registry $fields;
    /**
     * @param \DOMElement $node       A \DOMElement instance
     * @param string|null $currentUri The URI of the page where the form is embedded
     * @param string|null $method     The method to use for the link (if null, it defaults to the method defined by the form)
     * @param string|null $baseHref   The URI of the <base> used for relative links, but not for empty action
     *
     * @throws \LogicException if the node is not a button inside a form tag
     */
    public function __construct(\Dom_Element $node, ?string $current_uri = null, ?string $method = null, private readonly ?string $base_href = null)
    {
        parent::__construct($node, $current_uri, $method);
        $this->initialize();
    }
    /**
     * Gets the form node associated with this form.
     */
    public function get_form_node(): \Dom_Element
    {
        return $this->node;
    }
    /**
     * Sets the value of the fields.
     *
     * @param array $values An array of field values
     *
     * @return $this
     */
    public function set_values(array $values): static
    {
        foreach ($values as $name => $value) {
            $this->fields->set($name, $value);
        }
        return $this;
    }
    /**
     * Gets the field values.
     *
     * The returned array does not include file fields (@see getFiles).
     */
    public function get_values(): array
    {
        $values = [];
        foreach ($this->fields->all() as $name => $field) {
            if ($field->is_disabled()) {
                continue;
            }
            if (!$field instanceof Field\File_Form_Field && $field->has_value()) {
                $values[$name] = $field->get_value();
            }
        }
        return $values;
    }
    /**
     * Gets the file field values.
     */
    public function get_files(): array
    {
        if (!\in_array($this->get_method(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return [];
        }
        $files = [];
        foreach ($this->fields->all() as $name => $field) {
            if ($field->is_disabled()) {
                continue;
            }
            if ($field instanceof Field\File_Form_Field) {
                $files[$name] = $field->get_value();
            }
        }
        return $files;
    }
    /**
     * Gets the field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     */
    public function get_php_values(): array
    {
        $values = [];
        foreach ($this->get_values() as $name => $value) {
            $qs = http_build_query([$name => $value], '', '&');
            if ($qs) {
                parse_str($qs, $expanded_value);
                $var_name = substr((string) $name, 0, \strlen((string) key($expanded_value)));
                $values[] = [$var_name => current($expanded_value)];
            }
        }
        return array_replace_recursive([], ...$values);
    }
    /**
     * Gets the file field values as PHP.
     *
     * This method converts fields with the array notation
     * (like foo[bar] to arrays) like PHP does.
     * The returned array is consistent with the array for field values
     * (@see getPhpValues), rather than uploaded files found in $_FILES.
     * For a compound file field foo[bar] it will create foo[bar][name],
     * instead of foo[name][bar] which would be found in $_FILES.
     */
    public function get_php_files(): array
    {
        $values = [];
        foreach ($this->get_files() as $name => $value) {
            $qs = http_build_query([$name => $value], '', '&');
            if ($qs) {
                parse_str($qs, $expanded_value);
                $var_name = substr((string) $name, 0, \strlen((string) key($expanded_value)));
                array_walk_recursive($expanded_value, static function (&$value, $key): void {
                    if (ctype_digit((string) $value) && ('size' === $key || 'error' === $key)) {
                        $value = (int) $value;
                    }
                });
                reset($expanded_value);
                $values[] = [$var_name => current($expanded_value)];
            }
        }
        return array_replace_recursive([], ...$values);
    }
    /**
     * Gets the URI of the form.
     *
     * The returned URI is not the same as the form "action" attribute.
     * This method merges the value if the method is GET to mimics
     * browser behavior.
     */
    public function get_uri(): string
    {
        $uri = parent::get_uri();
        if (!\in_array($this->get_method(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $current_parameters = [];
            if ($query = parse_url($uri, \PHP_URL_QUERY)) {
                parse_str($query, $current_parameters);
            }
            $query_string = http_build_query(array_merge($current_parameters, $this->get_values()), '', '&');
            $pos = strpos($uri, '?');
            $base = false === $pos ? $uri : substr($uri, 0, $pos);
            $uri = rtrim($base . '?' . $query_string, '?');
        }
        return $uri;
    }
    protected function get_raw_uri(): string
    {
        // If the form was created from a button rather than the form node, check for HTML5 action overrides
        if ($this->button !== $this->node && $this->button->get_attribute('formaction')) {
            return $this->button->get_attribute('formaction');
        }
        return $this->node->get_attribute('action');
    }
    /**
     * Gets the form method.
     *
     * If no method is defined in the form, GET is returned.
     */
    public function get_method(): string
    {
        if (null !== $this->method) {
            return $this->method;
        }
        // If the form was created from a button rather than the form node, check for HTML5 method override
        if ($this->button !== $this->node && $this->button->get_attribute('formmethod')) {
            return strtoupper($this->button->get_attribute('formmethod'));
        }
        return $this->node->get_attribute('method') ? strtoupper($this->node->get_attribute('method')) : 'GET';
    }
    /**
     * Gets the form name.
     *
     * If no name is defined on the form, an empty string is returned.
     */
    public function get_name(): string
    {
        return $this->node->get_attribute('name');
    }
    /**
     * Returns true if the named field exists.
     */
    public function has(string $name): bool
    {
        return $this->fields->has($name);
    }
    /**
     * Removes a field from the form.
     */
    public function remove(string $name): void
    {
        $this->fields->remove($name);
    }
    /**
     * Gets a named field.
     *
     * @return FormField|FormField[]|FormField[][]
     *
     * @throws \InvalidArgumentException When field is not present in this form
     */
    public function get(string $name): Form_Field|array
    {
        return $this->fields->get($name);
    }
    /**
     * Sets a named field.
     */
    public function set(Form_Field $field): void
    {
        $this->fields->add($field);
    }
    /**
     * Gets all fields.
     *
     * @return FormField[]
     */
    public function all(): array
    {
        return $this->fields->all();
    }
    /**
     * Returns true if the named field exists.
     *
     * @param string $name The field name
     */
    public function offsetExists(mixed $name): bool
    {
        return $this->has($name);
    }
    /**
     * Gets the value of a field.
     *
     * @param string $name The field name
     *
     * @return FormField|FormField[]|FormField[][]
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetGet(mixed $name): Form_Field|array
    {
        return $this->fields->get($name);
    }
    /**
     * Sets the value of a field.
     *
     * @param string       $name  The field name
     * @param string|array $value The value of the field
     *
     * @throws \InvalidArgumentException if the field does not exist
     */
    public function offsetSet(mixed $name, mixed $value): void
    {
        $this->fields->set($name, $value);
    }
    /**
     * Removes a field from the form.
     *
     * @param string $name The field name
     */
    public function offsetUnset(mixed $name): void
    {
        $this->fields->remove($name);
    }
    /**
     * Disables validation.
     *
     * @return $this
     */
    public function disable_validation(): static
    {
        foreach ($this->fields->all() as $field) {
            if ($field instanceof Choice_Form_Field) {
                $field->disable_validation();
            }
        }
        return $this;
    }
    /**
     * Sets the node for the form.
     *
     * Expects a 'submit' button \DOMElement and finds the corresponding form element, or the form element itself.
     *
     * @throws \LogicException If given node is not a button or input or does not have a form ancestor
     */
    protected function set_node(\Dom_Element $node): void
    {
        $this->button = $node;
        if ('button' === $node->node_name || 'input' === $node->node_name && \in_array(strtolower($node->get_attribute('type')), ['submit', 'button', 'image'], true)) {
            if ($node->has_attribute('form')) {
                // if the node has the HTML5-compliant 'form' attribute, use it
                $form_id = $node->get_attribute('form');
                $form = $node->owner_document->get_element_by_id($form_id);
                if (null === $form) {
                    throw new \LogicException(\sprintf('The selected node has an invalid form attribute (%s).', $form_id));
                }
                $this->node = $form;
                return;
            }
            // we loop until we find a form ancestor
            do {
                if (null === $node = $node->parent_node) {
                    throw new \LogicException('The selected node does not have a form ancestor.');
                }
            } while ('form' !== $node->node_name);
        } elseif ('form' !== $node->node_name) {
            throw new \LogicException(\sprintf('Unable to submit on a "%s" tag.', $node->node_name));
        }
        $this->node = $node;
    }
    /**
     * Adds form elements related to this form.
     *
     * Creates an internal copy of the submitted 'button' element and
     * the form node or the entire document depending on whether we need
     * to find non-descendant elements through HTML5 'form' attribute.
     */
    private function initialize(): void
    {
        $this->fields = new Form_Field_Registry();
        $xpath = new \Domx_Path($this->node->owner_document);
        // add submitted button if it has a valid name
        if ('form' !== $this->button->node_name && $this->button->has_attribute('name') && $this->button->get_attribute('name')) {
            if ('input' == $this->button->node_name && 'image' == strtolower($this->button->get_attribute('type'))) {
                $name = $this->button->get_attribute('name');
                $this->button->set_attribute('value', '0');
                // temporarily change the name of the input node for the x coordinate
                $this->button->set_attribute('name', $name . '.x');
                $this->set(new Field\Input_Form_Field($this->button));
                // temporarily change the name of the input node for the y coordinate
                $this->button->set_attribute('name', $name . '.y');
                $this->set(new Field\Input_Form_Field($this->button));
                // restore the original name of the input node
                $this->button->set_attribute('name', $name);
            } else {
                $this->set(new Field\Input_Form_Field($this->button));
            }
        }
        // find form elements corresponding to the current form
        if ($this->node->has_attribute('id')) {
            // corresponding elements are either descendants or have a matching HTML5 form attribute
            $form_id = Crawler::xpath_literal($this->node->get_attribute('id'));
            $field_nodes = $xpath->query(\sprintf('( descendant::input[@form=%s] | descendant::button[@form=%1$s] | descendant::textarea[@form=%1$s] | descendant::select[@form=%1$s] | //form[@id=%1$s]//input[not(@form)] | //form[@id=%1$s]//button[not(@form)] | //form[@id=%1$s]//textarea[not(@form)] | //form[@id=%1$s]//select[not(@form)] )[( not(ancestor::template) or ancestor::turbo-stream )]', $form_id));
        } else {
            // do the xpath query with $this->node as the context node, to only find descendant elements
            // however, descendant elements with form attribute are not part of this form
            $field_nodes = $xpath->query('( descendant::input[not(@form)] | descendant::button[not(@form)] | descendant::textarea[not(@form)] | descendant::select[not(@form)] )[( not(ancestor::template) or ancestor::turbo-stream )]', $this->node);
        }
        foreach ($field_nodes as $node) {
            $this->add_field($node);
        }
        if ($this->base_href && '' !== $this->node->get_attribute('action')) {
            $this->current_uri = $this->base_href;
        }
    }
    private function add_field(\Dom_Element $node): void
    {
        if (!$node->has_attribute('name') || !$node->get_attribute('name')) {
            return;
        }
        $node_name = $node->node_name;
        if ('select' == $node_name || 'input' == $node_name && 'checkbox' == strtolower($node->get_attribute('type'))) {
            $this->set(new Choice_Form_Field($node));
        } elseif ('input' == $node_name && 'radio' == strtolower($node->get_attribute('type'))) {
            // there may be other fields with the same name that are no choice
            // fields already registered (see https://github.com/symfony/symfony/issues/11689)
            if ($this->has($node->get_attribute('name')) && $this->get($node->get_attribute('name')) instanceof Choice_Form_Field) {
                $this->get($node->get_attribute('name'))->add_choice($node);
            } else {
                $this->set(new Choice_Form_Field($node));
            }
        } elseif ('input' == $node_name && 'file' == strtolower($node->get_attribute('type'))) {
            $this->set(new Field\File_Form_Field($node));
        } elseif ('input' == $node_name && !\in_array(strtolower($node->get_attribute('type')), ['submit', 'button', 'image'], true)) {
            $this->set(new Field\Input_Form_Field($node));
        } elseif ('textarea' == $node_name) {
            $this->set(new Field\Textarea_Form_Field($node));
        }
    }
}