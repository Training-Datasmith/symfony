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
 * InputFormField represents an input form field (an HTML input tag).
 *
 * For inputs with type of file, checkbox, or radio, there are other more
 * specialized classes (cf. FileFormField and ChoiceFormField).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Input_Form_Field extends Form_Field
{
    /**
     * Initializes the form field.
     *
     * @throws \LogicException When node type is incorrect
     */
    protected function initialize(): void
    {
        if ('input' !== $this->node->node_name && 'button' !== $this->node->node_name) {
            throw new \LogicException(\sprintf('An InputFormField can only be created from an input or button tag (%s given).', $this->node->node_name));
        }
        $type = strtolower($this->node->get_attribute('type'));
        if ('checkbox' === $type) {
            throw new \LogicException('Checkboxes should be instances of ChoiceFormField.');
        }
        if ('file' === $type) {
            throw new \LogicException('File inputs should be instances of FileFormField.');
        }
        $this->value = $this->node->get_attribute('value');
    }
}