<?php

namespace SubsiteFluentExtensions;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Forms\DropdownField;

class SubsiteDomainExtension extends Extension
{
    private static array $db = [
        'Locale'    =>  'Varchar'
    ];

    public function updateCMSFields(FieldList $fields): void
    {
        $allowedlocales = Locale::get();
        $preparedlocales= [];
        foreach($allowedlocales as $locale)
        {
            $preparedlocales[$locale->Locale] = $locale->Title;
        }
        
        $fields->push(DropdownField::create("Locale","Locale",$preparedlocales)->setEmptyString(""));
    }
}
