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

use Symfony\Contracts\Service\Reset_Interface;
/**
 * Default implementation of {@link FormRendererEngineInterface}.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Abstract_Renderer_Engine implements Form_Renderer_Engine_Interface, Reset_Interface
{
    /**
     * The variable in {@link FormView} used as cache key.
     */
    public const CACHE_KEY_VAR = 'cache_key';
    /**
     * @var array[]
     */
    protected array $themes = [];
    /**
     * @var bool[]
     */
    protected array $use_default_themes = [];
    /**
     * @var array[]
     */
    protected array $resources = [];
    /**
     * @var array<array<int|false>>
     */
    private array $resource_hierarchy_levels = [];
    /**
     * @var array<string, array<string, bool>>
     */
    private array $resource_inheritability = [];
    /**
     * Creates a new renderer engine.
     *
     * @param array $defaultThemes The default themes. The type of these
     *                             themes is open to the implementation.
     */
    public function __construct(protected array $default_themes = [])
    {
    }
    public function set_theme(Form_View $view, mixed $themes, bool $use_default_themes = true): void
    {
        $cache_key = $view->vars[self::CACHE_KEY_VAR];
        // Do not cast, as casting turns objects into arrays of properties
        $this->themes[$cache_key] = \is_array($themes) ? $themes : [$themes];
        $this->use_default_themes[$cache_key] = $use_default_themes;
        // Unset instead of resetting to an empty array, in order to allow
        // implementations (like TwigRendererEngine) to check whether $cacheKey
        // is set at all.
        unset($this->resources[$cache_key], $this->resource_hierarchy_levels[$cache_key], $this->resource_inheritability[$cache_key]);
    }
    protected function set_resource_inheritability(string $cache_key, string $block_name, bool $inheritable): void
    {
        $this->resource_inheritability[$cache_key][$block_name] = $inheritable;
    }
    protected function is_resource_inheritable(string $cache_key, string $block_name): bool
    {
        return $this->resource_inheritability[$cache_key][$block_name] ?? false;
    }
    public function get_resource_for_block_name(Form_View $view, string $block_name): mixed
    {
        $cache_key = $view->vars[self::CACHE_KEY_VAR];
        if (!isset($this->resources[$cache_key][$block_name])) {
            $this->load_resource_for_block_name($cache_key, $view, $block_name);
        }
        return $this->resources[$cache_key][$block_name];
    }
    public function get_resource_for_block_name_hierarchy(Form_View $view, array $block_name_hierarchy, int $hierarchy_level): mixed
    {
        $cache_key = $view->vars[self::CACHE_KEY_VAR];
        $block_name = $block_name_hierarchy[$hierarchy_level];
        if (!isset($this->resources[$cache_key][$block_name])) {
            $this->load_resource_for_block_name_hierarchy($cache_key, $view, $block_name_hierarchy, $hierarchy_level);
        }
        return $this->resources[$cache_key][$block_name];
    }
    public function get_resource_hierarchy_level(Form_View $view, array $block_name_hierarchy, int $hierarchy_level): int|false
    {
        $cache_key = $view->vars[self::CACHE_KEY_VAR];
        $block_name = $block_name_hierarchy[$hierarchy_level];
        if (!isset($this->resources[$cache_key][$block_name])) {
            $this->load_resource_for_block_name_hierarchy($cache_key, $view, $block_name_hierarchy, $hierarchy_level);
        }
        // If $block was previously rendered loaded with loadTemplateForBlock(), the template
        // is cached but the hierarchy level is not. In this case, we know that the  block
        // exists at this very hierarchy level, so we can just set it.
        if (!isset($this->resource_hierarchy_levels[$cache_key][$block_name])) {
            $this->resource_hierarchy_levels[$cache_key][$block_name] = $hierarchy_level;
        }
        return $this->resource_hierarchy_levels[$cache_key][$block_name];
    }
    /**
     * Loads the cache with the resource for a given block name.
     *
     * @see getResourceForBlock()
     */
    abstract protected function load_resource_for_block_name(string $cache_key, Form_View $view, string $block_name): bool;
    /**
     * Loads the cache with the resource for a specific level of a block hierarchy.
     *
     * @see getResourceForBlockHierarchy()
     */
    private function load_resource_for_block_name_hierarchy(string $cache_key, Form_View $view, array $block_name_hierarchy, int $hierarchy_level): bool
    {
        $block_name = $block_name_hierarchy[$hierarchy_level];
        // Try to find a template for that block
        if ($this->load_resource_for_block_name($cache_key, $view, $block_name)) {
            // If loadTemplateForBlock() returns true, it was able to populate the
            // cache. The only missing thing is to set the hierarchy level at which
            // the template was found.
            $this->resource_hierarchy_levels[$cache_key][$block_name] = $hierarchy_level;
            $this->set_resource_inheritability($cache_key, $block_name, true);
            return true;
        }
        if ($hierarchy_level > 0) {
            $parent_level = $hierarchy_level - 1;
            $parent_block_name = $block_name_hierarchy[$parent_level];
            // The next two if statements contain slightly duplicated code. This is by intention
            // and tries to avoid execution of unnecessary checks in order to increase performance.
            if (isset($this->resources[$cache_key][$parent_block_name])) {
                // It may happen that the parent block is already loaded, but its level is not.
                // In this case, the parent block must have been loaded by loadResourceForBlock(),
                // which does not check the hierarchy of the block. Subsequently the block must have
                // been found directly on the parent level.
                if (!isset($this->resource_hierarchy_levels[$cache_key][$parent_block_name])) {
                    $this->resource_hierarchy_levels[$cache_key][$parent_block_name] = $parent_level;
                }
                // Cache the shortcuts for further accesses
                $this->resources[$cache_key][$block_name] = $this->resources[$cache_key][$parent_block_name];
                $this->resource_hierarchy_levels[$cache_key][$block_name] = $this->resource_hierarchy_levels[$cache_key][$parent_block_name];
                $this->set_resource_inheritability($cache_key, $block_name, false);
                return true;
            }
            if ($this->load_resource_for_block_name_hierarchy($cache_key, $view, $block_name_hierarchy, $parent_level)) {
                // Cache the shortcuts for further accesses
                $this->resources[$cache_key][$block_name] = $this->resources[$cache_key][$parent_block_name];
                $this->resource_hierarchy_levels[$cache_key][$block_name] = $this->resource_hierarchy_levels[$cache_key][$parent_block_name];
                $this->set_resource_inheritability($cache_key, $block_name, false);
                return true;
            }
        }
        // Cache the result for further accesses
        $this->resources[$cache_key][$block_name] = false;
        $this->resource_hierarchy_levels[$cache_key][$block_name] = false;
        $this->set_resource_inheritability($cache_key, $block_name, true);
        return false;
    }
    public function reset(): void
    {
        $this->themes = [];
        $this->use_default_themes = [];
        $this->resources = [];
        $this->resource_hierarchy_levels = [];
        $this->resource_inheritability = [];
    }
}