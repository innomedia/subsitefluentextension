<?php
namespace SubsiteFluentExtensions;

use Exception;
use SilverStripe\Dev\Debug;
use SilverStripe\Core\Kernel;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Director;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Middleware\InitStateMiddleware;
use TractorCow\Fluent\Extension\FluentDirectorExtension;

class FluentDirectorExtensionInjector extends FluentDirectorExtension
{
    public function updateRules(&$rules)
    {
        $originalRules = $rules;
        $fluentRules = $this->getExplicitRoutes($rules);
        
        // Insert Fluent Rules before the default '$URLSegment//$Action/$ID/$OtherID'
        $rules = $this->insertRuleBefore($rules, '$URLSegment//$Action/$ID/$OtherID', $fluentRules);
        
        $request = Injector::inst()->get(HTTPRequest::class);
        if (!$request) {
            throw new Exception('No request found');
        }

        // Ensure InitStateMddleware is called here to set the correct defaultLocale
        Injector::inst()->create(InitStateMiddleware::class)->process($request, function () {
        });
        $defaultLocale = null;
        if(class_exists("\SilverStripe\Subsites\Model\SubsiteDomain") && array_key_exists("HTTP_HOST",$_SERVER))
        {
            $host = Convert::raw2sql($_SERVER["HTTP_HOST"]);
            //Maybe rewrite to SQL Request to reduce performance hit further
            //$subsiteDomain = \SilverStripe\Subsites\Model\SubsiteDomain::get()->filter("Domain",$host)->exclude("Locale","")->first();
            $subsiteDomain = DB::query("SELECT sd.Locale From SubsiteDomain sd WHERE sd.Domain = '".$host."' AND sd.Locale != ''")->value();
            if($subsiteDomain != null)
            {
                $LocaleString = $subsiteDomain->Locale;
                FluentState::singleton()->setLocale($LocaleString);
                $defaultLocale = Locale::get()->filter("Locale",$LocaleString)->first();
            }
        }
        if(!$defaultLocale)
        {
            $defaultLocale = Locale::getDefault(true);
            if (!$defaultLocale) {
                return;
            }
        }
        // If we do not wish to detect the locale automatically, fix the home page route
        // to the default locale for this domain.
        if (!static::config()->get('detect_locale')) {
            // Respect existing home controller
            $rules[''] = [
                'Controller' => $this->getRuleController($originalRules[''], $defaultLocale),
                static::config()->get('query_param') => $defaultLocale->Locale,
            ];
        }
        // If default locale doesn't have prefix, replace default route with
        // the default locale for this domain
        if (static::config()->get('disable_default_prefix')) {
            $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                'Controller' => $this->getRuleController($originalRules['$URLSegment//$Action/$ID/$OtherID'], $defaultLocale),
                static::config()->get('query_param') => $defaultLocale->Locale
            ];
        }
        
    }
}