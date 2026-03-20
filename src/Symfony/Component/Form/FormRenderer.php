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
namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\BadMethodCallException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Security\Csrf\Csrf_Token_Manager_Interface;
use Twig\Environment;
/**
 * Renders a form into HTML using a rendering engine.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form_Renderer implements Form_Renderer_Interface
{
    public const CACHE_KEY_VAR = 'unique_block_prefix';
    private array $block_name_hierarchy_map = [];
    private array $hierarchy_level_map = [];
    private array $variable_stack = [];
    public function __construct(private readonly Form_Renderer_Engine_Interface $engine, private readonly ?Csrf_Token_Manager_Interface $csrf_token_manager = null)
    {
    }
    public function get_engine(): Form_Renderer_Engine_Interface
    {
        return $this->engine;
    }
    public function set_theme(Form_View $view, mixed $themes, bool $use_default_themes = true): void
    {
        $this->engine->set_theme($view, $themes, $use_default_themes);
    }
    public function render_csrf_token(string $token_id): string
    {
        if (null === $this->csrf_token_manager) {
            throw new BadMethodCallException('CSRF tokens can only be generated if a CsrfTokenManagerInterface is injected in FormRenderer::__construct(). Try running "composer require symfony/security-csrf".');
        }
        return $this->csrf_token_manager->get_token($token_id)->get_value();
    }
    public function render_block(Form_View $view, string $block_name, array $variables = []): string
    {
        $resource = $this->engine->get_resource_for_block_name($view, $block_name);
        if (!$resource) {
            throw new LogicException(\sprintf('No block "%s" found while rendering the form.', $block_name));
        }
        $view_cache_key = $view->vars[self::CACHE_KEY_VAR];
        // The variables are cached globally for a view (instead of for the
        // current suffix)
        if (!isset($this->variable_stack[$view_cache_key])) {
            $this->variable_stack[$view_cache_key] = [];
            // The default variable scope contains all view variables, merged with
            // the variables passed explicitly to the helper
            $scope_variables = $view->vars;
            $var_init = true;
        } else {
            // Reuse the current scope and merge it with the explicitly passed variables
            $scope_variables = end($this->variable_stack[$view_cache_key]);
            $var_init = false;
        }
        // Merge the passed with the existing attributes
        if (isset($variables['attr']) && isset($scope_variables['attr'])) {
            $variables['attr'] = array_replace($scope_variables['attr'], $variables['attr']);
        }
        // Merge the passed with the exist *label* attributes
        if (isset($variables['label_attr']) && isset($scope_variables['label_attr'])) {
            $variables['label_attr'] = array_replace($scope_variables['label_attr'], $variables['label_attr']);
        }
        // Do not use array_replace_recursive(), otherwise array variables
        // cannot be overwritten
        $variables = array_replace($scope_variables, $variables);
        $this->variable_stack[$view_cache_key][] = $variables;
        // Do the rendering
        $html = $this->engine->render_block($view, $resource, $block_name, $variables);
        // Clear the stack
        array_pop($this->variable_stack[$view_cache_key]);
        if ($var_init) {
            unset($this->variable_stack[$view_cache_key]);
        }
        return $html;
    }
    public function search_and_render_block(Form_View $view, string $block_name_suffix, array $variables = []): string
    {
        $render_only_once = 'row' === $block_name_suffix || 'widget' === $block_name_suffix;
        if ($render_only_once && $view->is_rendered()) {
            // This is not allowed, because it would result in rendering same IDs multiple times, which is not valid.
            throw new BadMethodCallException(\sprintf('Field "%s" has already been rendered, save the result of previous render call to a variable and output that instead.', $view->vars['name']));
        }
        // The cache key for storing the variables and types
        $view_cache_key = $view->vars[self::CACHE_KEY_VAR];
        $view_and_suffix_cache_key = $view_cache_key . $block_name_suffix;
        // In templates, we have to deal with two kinds of block hierarchies:
        //
        //   +---------+          +---------+
        //   | Theme B | -------> | Theme A |
        //   +---------+          +---------+
        //
        //   form_widget -------> form_widget
        //       ^
        //       |
        //  choice_widget -----> choice_widget
        //
        // The first kind of hierarchy is the theme hierarchy. This allows to
        // override the block "choice_widget" from Theme A in the extending
        // Theme B. This kind of inheritance needs to be supported by the
        // template engine and, for example, offers "parent()" or similar
        // functions to fall back from the custom to the parent implementation.
        //
        // The second kind of hierarchy is the form type hierarchy. This allows
        // to implement a custom "choice_widget" block (no matter in which theme),
        // or to fallback to the block of the parent type, which would be
        // "form_widget" in this example (again, no matter in which theme).
        // If the designer wants to explicitly fallback to "form_widget" in their
        // custom "choice_widget", for example because they only want to wrap
        // a <div> around the original implementation, they can call the
        // widget() function again to render the block for the parent type.
        //
        // The second kind is implemented in the following blocks.
        if (!isset($this->block_name_hierarchy_map[$view_and_suffix_cache_key])) {
            // INITIAL CALL
            // Calculate the hierarchy of template blocks and start on
            // the bottom level of the hierarchy (= "_<id>_<section>" block)
            $block_name_hierarchy = [];
            foreach ($view->vars['block_prefixes'] as $block_name_prefix) {
                $block_name_hierarchy[] = $block_name_prefix . '_' . $block_name_suffix;
            }
            $hierarchy_level = \count($block_name_hierarchy) - 1;
            $hierarchy_init = true;
        } else {
            // RECURSIVE CALL
            // If a block recursively calls searchAndRenderBlock() again, resume rendering
            // using the parent type in the hierarchy.
            $block_name_hierarchy = $this->block_name_hierarchy_map[$view_and_suffix_cache_key];
            $hierarchy_level = $this->hierarchy_level_map[$view_and_suffix_cache_key] - 1;
            $hierarchy_init = false;
        }
        // The variables are cached globally for a view (instead of for the
        // current suffix)
        if (!isset($this->variable_stack[$view_cache_key])) {
            $this->variable_stack[$view_cache_key] = [];
            // The default variable scope contains all view variables, merged with
            // the variables passed explicitly to the helper
            $scope_variables = $view->vars;
            $var_init = true;
        } else {
            // Reuse the current scope and merge it with the explicitly passed variables
            $scope_variables = end($this->variable_stack[$view_cache_key]);
            $var_init = false;
        }
        // Load the resource where this block can be found
        $resource = $this->engine->get_resource_for_block_name_hierarchy($view, $block_name_hierarchy, $hierarchy_level);
        // Update the current hierarchy level to the one at which the resource was
        // found. For example, if looking for "choice_widget", but only a resource
        // is found for its parent "form_widget", then the level is updated here
        // to the parent level.
        $hierarchy_level = $this->engine->get_resource_hierarchy_level($view, $block_name_hierarchy, $hierarchy_level);
        // The actually existing block name in $resource
        $block_name = $block_name_hierarchy[$hierarchy_level];
        // Escape if no resource exists for this block
        if (!$resource) {
            if (\count($block_name_hierarchy) !== \count(array_unique($block_name_hierarchy))) {
                throw new LogicException(\sprintf('Unable to render the form because the block names array contains duplicates: "%s".', implode('", "', array_reverse($block_name_hierarchy))));
            }
            throw new LogicException(\sprintf('Unable to render the form as none of the following blocks exist: "%s".', implode('", "', array_reverse($block_name_hierarchy))));
        }
        // Merge the passed with the existing attributes
        if (isset($variables['attr']) && isset($scope_variables['attr'])) {
            $variables['attr'] = array_replace($scope_variables['attr'], $variables['attr']);
        }
        // Merge the passed with the exist *label* attributes
        if (isset($variables['label_attr']) && isset($scope_variables['label_attr'])) {
            $variables['label_attr'] = array_replace($scope_variables['label_attr'], $variables['label_attr']);
        }
        // Do not use array_replace_recursive(), otherwise array variables
        // cannot be overwritten
        $variables = array_replace($scope_variables, $variables);
        // In order to make recursive calls possible, we need to store the block hierarchy,
        // the current level of the hierarchy and the variables so that this method can
        // resume rendering one level higher of the hierarchy when it is called recursively.
        //
        // We need to store these values in maps (associative arrays) because within a
        // call to widget() another call to widget() can be made, but for a different view
        // object. These nested calls should not override each other.
        $this->block_name_hierarchy_map[$view_and_suffix_cache_key] = $block_name_hierarchy;
        $this->hierarchy_level_map[$view_and_suffix_cache_key] = $hierarchy_level;
        // We also need to store the variables for the view so that we can render other
        // blocks for the same view using the same variables as in the outer block.
        $this->variable_stack[$view_cache_key][] = $variables;
        // Do the rendering
        $html = $this->engine->render_block($view, $resource, $block_name, $variables);
        // Clear the stack
        array_pop($this->variable_stack[$view_cache_key]);
        // Clear the caches if they were filled for the first time within
        // this function call
        if ($hierarchy_init) {
            unset($this->block_name_hierarchy_map[$view_and_suffix_cache_key], $this->hierarchy_level_map[$view_and_suffix_cache_key]);
        }
        if ($var_init) {
            unset($this->variable_stack[$view_cache_key]);
        }
        if ($render_only_once) {
            $view->set_rendered();
        }
        return $html;
    }
    public function humanize(string $text): string
    {
        return ucfirst(strtolower(trim((string) preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $text))));
    }
    /**
     * @internal
     */
    public function encode_currency(Environment $environment, string $text, string $widget = ''): string
    {
        if ('UTF-8' === $charset = $environment->get_charset()) {
            $text = htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $text = htmlentities($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
            $text = iconv('UTF-8', $charset, $text);
            $widget = iconv('UTF-8', $charset, $widget);
        }
        return str_replace('{{ widget }}', $widget, $text);
    }
}