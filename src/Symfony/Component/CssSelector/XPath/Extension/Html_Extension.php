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
namespace Symfony\Component\Css_Selector\X_Path\Extension;

use Symfony\Component\Css_Selector\Exception\Expression_Error_Exception;
use Symfony\Component\Css_Selector\Node\Function_Node;
use Symfony\Component\Css_Selector\X_Path\Translator;
use Symfony\Component\Css_Selector\X_Path\X_Path_Expr;
/**
 * XPath expression translator HTML extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class Html_Extension extends Abstract_Extension
{
    public function __construct(Translator $translator)
    {
        $translator->get_extension('node')->set_flag(Node_Extension::ELEMENT_NAME_IN_LOWER_CASE, true)->set_flag(Node_Extension::ATTRIBUTE_NAME_IN_LOWER_CASE, true);
    }
    public function get_pseudo_class_translators(): array
    {
        return ['checked' => $this->translate_checked(...), 'link' => $this->translate_link(...), 'disabled' => $this->translate_disabled(...), 'enabled' => $this->translate_enabled(...), 'selected' => $this->translate_selected(...), 'invalid' => $this->translate_invalid(...), 'hover' => $this->translate_hover(...), 'visited' => $this->translate_visited(...)];
    }
    public function get_function_translators(): array
    {
        return ['lang' => $this->translate_lang(...)];
    }
    public function translate_checked(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('(@checked ' . "and (name(.) = 'input' or name(.) = 'command')" . "and (@type = 'checkbox' or @type = 'radio'))");
    }
    public function translate_link(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition("@href and (name(.) = 'a' or name(.) = 'link' or name(.) = 'area')");
    }
    public function translate_disabled(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('(' . '@disabled and' . '(' . "(name(.) = 'input' and @type != 'hidden')" . " or name(.) = 'button'" . " or name(.) = 'select'" . " or name(.) = 'textarea'" . " or name(.) = 'command'" . " or name(.) = 'fieldset'" . " or name(.) = 'optgroup'" . " or name(.) = 'option'" . ')' . ') or (' . "(name(.) = 'input' and @type != 'hidden')" . " or name(.) = 'button'" . " or name(.) = 'select'" . " or name(.) = 'textarea'" . ')' . ' and ancestor::fieldset[@disabled]');
        // todo: in the second half, add "and is not a descendant of that fieldset element's first legend element child, if any."
    }
    public function translate_enabled(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('(' . '@href and (' . "name(.) = 'a'" . " or name(.) = 'link'" . " or name(.) = 'area'" . ')' . ') or (' . '(' . "name(.) = 'command'" . " or name(.) = 'fieldset'" . " or name(.) = 'optgroup'" . ')' . ' and not(@disabled)' . ') or (' . '(' . "(name(.) = 'input' and @type != 'hidden')" . " or name(.) = 'button'" . " or name(.) = 'select'" . " or name(.) = 'textarea'" . " or name(.) = 'keygen'" . ')' . ' and not (@disabled or ancestor::fieldset[@disabled])' . ') or (' . "name(.) = 'option' and not(" . '@disabled or ancestor::optgroup[@disabled]' . ')' . ')');
    }
    /**
     * @throws ExpressionErrorException
     */
    public function translate_lang(X_Path_Expr $xpath, Function_Node $function): X_Path_Expr
    {
        $arguments = $function->get_arguments();
        foreach ($arguments as $token) {
            if (!($token->is_string() || $token->is_identifier())) {
                throw new Expression_Error_Exception('Expected a single string or identifier for :lang(), got ' . implode(', ', $arguments));
            }
        }
        return $xpath->add_condition(\sprintf('ancestor-or-self::*[@lang][1][starts-with(concat(' . "translate(@%s, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '-')" . ', %s)]', 'lang', Translator::get_xpath_literal(strtolower((string) $arguments[0]->get_value()) . '-')));
    }
    public function translate_selected(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition("(@selected and name(.) = 'option')");
    }
    public function translate_invalid(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('0');
    }
    public function translate_hover(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('0');
    }
    public function translate_visited(X_Path_Expr $xpath): X_Path_Expr
    {
        return $xpath->add_condition('0');
    }
    public function get_name(): string
    {
        return 'html';
    }
}