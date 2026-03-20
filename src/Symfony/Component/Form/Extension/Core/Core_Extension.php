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
namespace Symfony\Component\Form\Extension\Core;

use Symfony\Component\Form\Abstract_Extension;
use Symfony\Component\Form\Choice_List\Factory\Caching_Factory_Decorator;
use Symfony\Component\Form\Choice_List\Factory\Choice_List_Factory_Interface;
use Symfony\Component\Form\Choice_List\Factory\Default_Choice_List_Factory;
use Symfony\Component\Form\Choice_List\Factory\Property_Access_Decorator;
use Symfony\Component\Form\Extension\Core\Type\Transformation_Failure_Extension;
use Symfony\Component\Form\Flow;
use Symfony\Component\Property_Access\Property_Access;
use Symfony\Component\Property_Access\Property_Accessor_Interface;
use Symfony\Contracts\Translation\Translator_Interface;
/**
 * Represents the main form extension, which loads the core functionality.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Core_Extension extends Abstract_Extension
{
    private readonly Property_Accessor_Interface $property_accessor;
    private readonly Choice_List_Factory_Interface $choice_list_factory;
    public function __construct(?Property_Accessor_Interface $property_accessor = null, ?Choice_List_Factory_Interface $choice_list_factory = null, private readonly ?Translator_Interface $translator = null)
    {
        $this->property_accessor = $property_accessor ?: Property_Access::create_property_accessor();
        $this->choice_list_factory = $choice_list_factory ?? new Caching_Factory_Decorator(new Property_Access_Decorator(new Default_Choice_List_Factory(), $this->property_accessor));
    }
    protected function load_types(): array
    {
        return [new Type\Form_Type($this->property_accessor), new Type\Birthday_Type(), new Type\Checkbox_Type(), new Type\Choice_Type($this->choice_list_factory, $this->translator), new Type\Collection_Type(), new Type\Country_Type(), new Type\Date_Interval_Type(), new Type\Date_Type(), new Type\Date_Time_Type(), new Type\Email_Type(), new Type\Hidden_Type(), new Type\Integer_Type(), new Type\Language_Type(), new Type\Locale_Type(), new Type\Money_Type(), new Type\Number_Type(), new Type\Password_Type(), new Type\Percent_Type(), new Type\Radio_Type(), new Type\Range_Type(), new Type\Repeated_Type(), new Type\Search_Type(), new Type\Textarea_Type(), new Type\Text_Type(), new Type\Time_Type(), new Type\Timezone_Type(), new Type\Url_Type(), new Type\File_Type($this->translator), new Type\Button_Type(), new Type\Submit_Type(), new Type\Reset_Type(), new Type\Currency_Type(), new Type\Tel_Type(), new Type\Color_Type($this->translator), new Type\Week_Type(), new Flow\Type\Button_Flow_Type(), new Flow\Type\Finish_Flow_Type(), new Flow\Type\Navigator_Flow_Type(), new Flow\Type\Next_Flow_Type(), new Flow\Type\Previous_Flow_Type(), new Flow\Type\Form_Flow_Type($this->property_accessor)];
    }
    protected function load_type_extensions(): array
    {
        return [new Transformation_Failure_Extension($this->translator)];
    }
}