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
namespace Symfony\Component\Form\Extension\Data_Collector;

use Symfony\Component\Form\Form_Interface;
use Symfony\Component\Form\Form_View;
/**
 * Extracts arrays of information out of forms.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface Form_Data_Extractor_Interface
{
    /**
     * Extracts the configuration data of a form.
     */
    public function extract_configuration(Form_Interface $form): array;
    /**
     * Extracts the default data of a form.
     */
    public function extract_default_data(Form_Interface $form): array;
    /**
     * Extracts the submitted data of a form.
     */
    public function extract_submitted_data(Form_Interface $form): array;
    /**
     * Extracts the view variables of a form.
     */
    public function extract_view_variables(Form_View $view): array;
}