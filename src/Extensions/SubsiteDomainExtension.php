<?php
namespace SubsiteFluentExtensions;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Forms\DropdownField;

class SubsiteDomainExtension extends DataExtension
{
    private static $db = [
        'Locale'    =>  'Varchar'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $allowedlocales = Locale::get();
        $preparedlocales= array();
        foreach($allowedlocales as $locale)
        {
            $preparedlocales[$locale->Locale] = $locale->Title;
        }
        $fields->push(DropdownField::create("Locale","Locale",$preparedlocales));
    }
}