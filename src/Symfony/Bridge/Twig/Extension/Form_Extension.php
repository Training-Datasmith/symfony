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
namespace Symfony\Bridge\Twig\Extension;

use Symfony\Bridge\Twig\Node\Render_Block_Node;
use Symfony\Bridge\Twig\Node\Search_And_Render_Block_Node;
use Symfony\Bridge\Twig\Token_Parser\Form_Theme_Token_Parser;
use Symfony\Component\Form\Choice_List\View\Choice_Group_View;
use Symfony\Component\Form\Choice_List\View\Choice_View;
use Symfony\Component\Form\Form_Error;
use Symfony\Component\Form\Form_Renderer;
use Symfony\Component\Form\Form_View;
use Symfony\Contracts\Translation\Translatable_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
use Twig\Extension\Abstract_Extension;
use Twig\Twig_Filter;
use Twig\Twig_Function;
use Twig\Twig_Test;
/**
 * FormExtension extends Twig with form capabilities.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
final class Form_Extension extends Abstract_Extension
{
    public function __construct(private readonly ?Translator_Interface $translator = null)
    {
    }
    public function get_token_parsers(): array
    {
        return [
            // {% form_theme form "SomeBundle::widgets.twig" %}
            new Form_Theme_Token_Parser(),
        ];
    }
    public function get_functions(): array
    {
        return [new Twig_Function('form_widget', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_errors', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_label', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_help', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_row', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_rest', null, ['node_class' => Search_And_Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form', null, ['node_class' => Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_start', null, ['node_class' => Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('form_end', null, ['node_class' => Render_Block_Node::class, 'is_safe' => ['html']]), new Twig_Function('csrf_token', [Form_Renderer::class, 'renderCsrfToken']), new Twig_Function('form_parent', 'Symfony\Bridge\Twig\Extension\twig_get_form_parent'), new Twig_Function('field_name', $this->get_field_name(...)), new Twig_Function('field_id', $this->get_field_id(...)), new Twig_Function('field_value', $this->get_field_value(...)), new Twig_Function('field_label', $this->get_field_label(...)), new Twig_Function('field_help', $this->get_field_help(...)), new Twig_Function('field_errors', $this->get_field_errors(...)), new Twig_Function('field_choices', $this->get_field_choices(...)), new Twig_Function('form_flow_total_steps', $this->get_form_flow_total_steps(...)), new Twig_Function('form_flow_steps', $this->get_form_flow_steps(...)), new Twig_Function('form_flow_step_index', $this->get_form_flow_step_index(...)), new Twig_Function('form_flow_current_step', $this->get_form_flow_current_step(...)), new Twig_Function('form_flow_next_step', $this->get_form_flow_next_step(...)), new Twig_Function('form_flow_previous_step', $this->get_form_flow_previous_step(...)), new Twig_Function('form_flow_first_step', $this->get_form_flow_first_step(...)), new Twig_Function('form_flow_last_step', $this->get_form_flow_last_step(...)), new Twig_Function('form_flow_is_first_step', $this->is_form_flow_first_step(...)), new Twig_Function('form_flow_is_last_step', $this->is_form_flow_last_step(...)), new Twig_Function('form_flow_can_move_next', $this->can_form_flow_move_next(...)), new Twig_Function('form_flow_can_move_back', $this->can_form_flow_move_back(...))];
    }
    public function get_filters(): array
    {
        return [new Twig_Filter('humanize', [Form_Renderer::class, 'humanize']), new Twig_Filter('form_encode_currency', [Form_Renderer::class, 'encodeCurrency'], ['is_safe' => ['html'], 'needs_environment' => true])];
    }
    public function get_tests(): array
    {
        return [new Twig_Test('selectedchoice', 'Symfony\Bridge\Twig\Extension\twig_is_selected_choice'), new Twig_Test('rootform', 'Symfony\Bridge\Twig\Extension\twig_is_root_form')];
    }
    public function get_field_name(Form_View $view): string
    {
        $view->set_rendered();
        return $view->vars['full_name'];
    }
    public function get_field_id(Form_View $view): string
    {
        return $view->vars['id'];
    }
    public function get_field_value(Form_View $view): string|array
    {
        return $view->vars['value'];
    }
    public function get_field_label(Form_View $view): ?string
    {
        if (false === $label = $view->vars['label']) {
            return null;
        }
        if (!$label && $label_format = $view->vars['label_format']) {
            $label = str_replace(['%id%', '%name%'], [$view->vars['id'], $view->vars['name']], $label_format);
        } elseif (!$label) {
            $label = ucfirst(strtolower(trim((string) preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], (string) $view->vars['name']))));
        }
        return $this->create_field_translation($label, $view->vars['label_translation_parameters'] ?: [], $view->vars['translation_domain']);
    }
    public function get_field_help(Form_View $view): ?string
    {
        return $this->create_field_translation($view->vars['help'], $view->vars['help_translation_parameters'] ?: [], $view->vars['translation_domain']);
    }
    /**
     * @return string[]
     */
    public function get_field_errors(Form_View $view): iterable
    {
        /** @var FormError $error */
        foreach ($view->vars['errors'] as $error) {
            yield $error->get_message();
        }
    }
    /**
     * @return string[]|string[][]
     */
    public function get_field_choices(Form_View $view): iterable
    {
        yield from $this->create_field_choices_list($view->vars['choices'], $view->vars['choice_translation_domain']);
    }
    public function get_form_flow_total_steps(Form_View $view): ?int
    {
        return ($view->vars['cursor'] ?? null)?->get_total_steps();
    }
    public function get_form_flow_steps(Form_View $view): ?array
    {
        return ($view->vars['cursor'] ?? null)?->get_steps();
    }
    public function get_form_flow_current_step(Form_View $view): ?string
    {
        return ($view->vars['cursor'] ?? null)?->get_current_step();
    }
    public function get_form_flow_step_index(Form_View $view): ?int
    {
        return ($view->vars['cursor'] ?? null)?->get_step_index();
    }
    public function get_form_flow_next_step(Form_View $view): ?string
    {
        return ($view->vars['cursor'] ?? null)?->get_next_step();
    }
    public function get_form_flow_previous_step(Form_View $view): ?string
    {
        return ($view->vars['cursor'] ?? null)?->get_previous_step();
    }
    public function get_form_flow_first_step(Form_View $view): ?string
    {
        return ($view->vars['cursor'] ?? null)?->get_first_step();
    }
    public function get_form_flow_last_step(Form_View $view): ?string
    {
        return ($view->vars['cursor'] ?? null)?->get_last_step();
    }
    public function is_form_flow_first_step(Form_View $view): bool
    {
        return ($view->vars['cursor'] ?? null)?->is_first_step() ?? false;
    }
    public function is_form_flow_last_step(Form_View $view): bool
    {
        return ($view->vars['cursor'] ?? null)?->is_last_step() ?? false;
    }
    public function can_form_flow_move_back(Form_View $view): bool
    {
        return ($view->vars['cursor'] ?? null)?->can_move_back() ?? false;
    }
    public function can_form_flow_move_next(Form_View $view): bool
    {
        return ($view->vars['cursor'] ?? null)?->can_move_next() ?? false;
    }
    private function create_field_choices_list(iterable $choices, string|false|null $translation_domain): iterable
    {
        foreach ($choices as $choice) {
            if ($choice instanceof Choice_Group_View) {
                $translatable_label = $this->create_field_translation($choice->label, [], $translation_domain);
                yield $translatable_label => $this->create_field_choices_list($choice, $translation_domain);
                continue;
            }
            /** @var ChoiceView $choice */
            $translatable_label = $this->create_field_translation($choice->label, $choice->label_translation_parameters, $translation_domain);
            yield $translatable_label => $choice->value;
        }
    }
    private function create_field_translation(Translatable_Interface|string|null $value, array $parameters, string|false|null $domain): ?string
    {
        if (!$this->translator || !$value || false === $domain) {
            return null !== $value ? (string) $value : null;
        }
        if ($value instanceof Translatable_Interface) {
            return $value->trans($this->translator);
        }
        return $this->translator->trans($value, $parameters, $domain);
    }
}
/**
 * Returns whether a choice is selected for a given form value.
 *
 * This is a function and not callable due to performance reasons.
 *
 * @see ChoiceView::isSelected()
 */
function twig_is_selected_choice(Choice_View $choice, string|array|null $selected_value): bool
{
    if (\is_array($selected_value)) {
        return \in_array($choice->value, $selected_value, true);
    }
    return $choice->value === $selected_value;
}
/**
 * @internal
 */
function twig_is_root_form(Form_View $form_view): bool
{
    return null === $form_view->parent;
}
/**
 * @internal
 */
function twig_get_form_parent(Form_View $form_view): ?Form_View
{
    return $form_view->parent;
}