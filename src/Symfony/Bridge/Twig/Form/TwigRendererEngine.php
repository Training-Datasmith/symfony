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
namespace Symfony\Bridge\Twig\Form;

use Symfony\Component\Form\Abstract_Renderer_Engine;
use Symfony\Component\Form\Form_View;
use Twig\Environment;
use Twig\Template;
/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Twig_Renderer_Engine extends Abstract_Renderer_Engine
{
    private Template $template;
    public function __construct(array $default_themes, private readonly Environment $environment)
    {
        parent::__construct($default_themes);
    }
    public function render_block(Form_View $view, mixed $resource, string $block_name, array $variables = []): string
    {
        $cache_key = $view->vars[self::CACHE_KEY_VAR];
        $context = $variables + $this->environment->get_globals();
        ob_start();
        // By contract,This method can only be called after getting the resource
        // (which is passed to the method). Getting a resource for the first time
        // (with an empty cache) is guaranteed to invoke loadResourcesFromTheme(),
        // where the property $template is initialized.
        // We do not call renderBlock here to avoid too many nested level calls
        // (XDebug limits the level to 100 by default)
        $this->template->display_block($block_name, $context, $this->resources[$cache_key]);
        return ob_get_clean();
    }
    /**
     * Loads the cache with the resource for a given block name.
     *
     * This implementation eagerly loads all blocks of the themes assigned to the given view
     * and all of its ancestors views. This is necessary, because Twig receives the
     * list of blocks later. At that point, all blocks must already be loaded, for the
     * case that the function "block()" is used in the Twig template.
     *
     * @see getResourceForBlock()
     */
    protected function load_resource_for_block_name(string $cache_key, Form_View $view, string $block_name): bool
    {
        // The caller guarantees that $this->resources[$cacheKey][$block] is
        // not set, but it doesn't have to check whether $this->resources[$cacheKey]
        // is set. If $this->resources[$cacheKey] is set, all themes for this
        // $cacheKey are already loaded (due to the eager population, see doc comment).
        if (isset($this->resources[$cache_key])) {
            // As said in the previous, the caller guarantees that
            // $this->resources[$cacheKey][$block] is not set. Since the themes are
            // already loaded, it can only be a non-existing block.
            $this->resources[$cache_key][$block_name] = false;
            $this->set_resource_inheritability($cache_key, $block_name, true);
            return false;
        }
        // Recursively try to find the block in the themes assigned to $view,
        // then of its parent view, then of the parent view of the parent and so on.
        // When the root view is reached in this recursion, also the default
        // themes are taken into account.
        // Check each theme whether it contains the searched block
        if (isset($this->themes[$cache_key])) {
            for ($i = \count($this->themes[$cache_key]) - 1; $i >= 0; --$i) {
                $this->load_resources_from_theme($cache_key, $this->themes[$cache_key][$i]);
                // CONTINUE LOADING (see doc comment)
            }
        }
        // Check the default themes once we reach the root view without success
        if (!$view->parent) {
            if (!isset($this->use_default_themes[$cache_key]) || $this->use_default_themes[$cache_key]) {
                for ($i = \count($this->default_themes) - 1; $i >= 0; --$i) {
                    $this->load_resources_from_theme($cache_key, $this->default_themes[$i]);
                    // CONTINUE LOADING (see doc comment)
                }
            }
        }
        // Proceed with the themes of the parent view
        if ($view->parent) {
            $parent_cache_key = $view->parent->vars[self::CACHE_KEY_VAR];
            if (!isset($this->resources[$parent_cache_key])) {
                $this->load_resource_for_block_name($parent_cache_key, $view->parent, $block_name);
            }
            // EAGER CACHE POPULATION (see doc comment)
            foreach ($this->resources[$parent_cache_key] as $nested_block_name => $resource) {
                if (!isset($this->resources[$cache_key][$nested_block_name]) && $this->is_resource_inheritable($parent_cache_key, $nested_block_name)) {
                    $this->resources[$cache_key][$nested_block_name] = $resource;
                    $this->set_resource_inheritability($cache_key, $nested_block_name, true);
                }
            }
        }
        // Even though we loaded the themes, it can happen that none of them
        // contains the searched block
        if (!isset($this->resources[$cache_key][$block_name])) {
            // Cache that we didn't find anything to speed up further accesses
            $this->resources[$cache_key][$block_name] = false;
            $this->set_resource_inheritability($cache_key, $block_name, true);
        }
        return false !== $this->resources[$cache_key][$block_name];
    }
    /**
     * Loads the resources for all blocks in a theme.
     *
     * @param mixed $theme The theme to load the block from. This parameter
     *                     is passed by reference, because it might be necessary
     *                     to initialize the theme first. Any changes made to
     *                     this variable will be kept and be available upon
     *                     further calls to this method using the same theme.
     */
    protected function load_resources_from_theme(string $cache_key, mixed &$theme): void
    {
        if (!$theme instanceof Template) {
            $theme = $this->environment->load($theme)->unwrap();
        }
        // Store the first Template instance that we find so that
        // we can call displayBlock() later on. It doesn't matter *which*
        // template we use for that, since we pass the used blocks manually
        // anyway.
        $this->template ??= $theme;
        // Use a separate variable for the inheritance traversal, because
        // theme is a reference and we don't want to change it.
        $current_theme = $theme;
        $context = $this->environment->get_globals();
        // The do loop takes care of template inheritance.
        // Add blocks from all templates in the inheritance tree, but avoid
        // overriding blocks already set.
        do {
            foreach ($current_theme->get_blocks() as $block => $block_data) {
                if (!isset($this->resources[$cache_key][$block])) {
                    // The resource given back is the key to the bucket that
                    // contains this block.
                    $this->resources[$cache_key][$block] = $block_data;
                    $this->set_resource_inheritability($cache_key, $block, true);
                }
            }
        } while (false !== $current_theme = $current_theme->get_parent($context));
    }
}