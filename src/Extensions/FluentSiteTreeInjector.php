<?php
namespace SubsiteFluentExtensions;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DB;
use TractorCow\Fluent\Extension\FluentSiteTreeExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class FluentSiteTreeInjector extends FluentSiteTreeExtension
{
    public function LocaleLink($Locale)
    {
        if($this->owner->SubsiteID > 0)
        {
            $base = $this->getPageSubsiteDomainByLocale($Locale,$this->owner->SubsiteID);
            $relativeLink = $this->owner->RelativeLink();
            $currentLocale = Locale::getCurrentLocale();

            if ($currentLocale != null && $currentLocale->DomainID > 0) {
                
                $relativeLink = str_replace($currentLocale->URLSegment . '/', '', $relativeLink);
                if($relativeLink == $currentLocale->URLSegment)
                {
                    $relativeLink = "/";
                }
            }
            
            return Controller::join_links($base,$relativeLink);
        }

        //On Mainsite default Link.Att works correctly

        return FluentState::singleton()->withState(function (FluentState $state) use ($Locale) {
            $state->setLocale($Locale);
            $page = SiteTree::get()->byID($this->owner->ID);
            return $page->Link();
        });
    }
    protected function getPageSubsiteDomainByLocale($Locale,$SubsiteID)
    {
        $subsiteDomain = DB::query("SELECT sd.Domain From SubsiteDomain sd WHERE sd.SubsiteID = $SubsiteID AND sd.Locale = '$Locale'")->value();
        if ($subsiteDomain != null && $subsiteDomain != "") {
            if (!strpos($subsiteDomain, "https://") && !strpos($subsiteDomain, "http://")) {
                $preHost = "http://";
                if (Director::is_https()) {
                    $preHost = "https://";
                }
                $subsiteDomain = $preHost . $subsiteDomain;
            }

            $subsiteDomain = Controller::join_links($subsiteDomain, Director::baseURL());
        } else {
            $subsiteDomain = Director::absoluteBaseURL();
        }
        return $subsiteDomain;
    }
    protected function addLocalePrefixToUrlSegment(FieldList $fields)
    {

        // Ensure the field is available in the list
        $segmentField = $fields->fieldByName('Root.Main.URLSegment');
        if (!$segmentField || !($segmentField instanceof SiteTreeURLSegmentField)) {
            return $this;
        }
        // Mock frontend and get link to parent object / page
        $baseURL = FluentState::singleton()
            ->withState(function (FluentState $tempState) {
                $tempState->setIsDomainMode(true);
                $tempState->setIsFrontend(true);

                // Get relative link up until the current URL segment
                if (SiteTree::config()->get('nested_urls') && $this->owner->ParentID) {
                    $parentRelative = $this->owner->Parent()->RelativeLink();
                } else {
                    $parentRelative = '/';
                    $action = null;
                    $this->updateRelativeLink($parentRelative, $action);
                }
                if ($this->owner->SubsiteID == 0) {
                    $domain = Locale::getCurrentLocale()->getDomain();
                    if ($domain) {
                        $parentBase = Controller::join_links($domain->Link(), Director::baseURL());
                    } else {
                        $parentBase = Director::absoluteBaseURL();
                    }
                } else {
                    $Locale = Locale::getCurrentLocale()->Locale;
                    $SubsiteID = $this->owner->SubsiteID;
                    $parentBase = $this->getSubsiteDomainByLocale($Locale,$SubsiteID);
                    
                }
                // Get absolute base path

                // Join base / relative links
                return Controller::join_links($parentBase, $parentRelative);
            });

        //TODO add Extension point here in fork then PR
        $segmentField->setURLPrefix($baseURL);
        return $this;
    }
    public function updateLink(&$link, &$action, &$relativeLink)
    {

        // Get appropriate locale for this record
        if ($this->owner->SubsiteID == 0) {
            parent::updateLink($link,$action,$relativeLink);
        }
        else
        {
            $Locale = Locale::getCurrentLocale()->Locale;
            $SubsiteID = $this->owner->SubsiteID;
            $SubsiteBaseLink = $this->getSubsiteDomainByLocale($Locale,$SubsiteID);
            if($SubsiteBaseLink != "")
            {

            }
            if(strpos($link,$Locale) !== false)
            {
                $link = str_replace($Locale."/","",$link);
            }
            
            $link = Controller::join_links($SubsiteBaseLink,$link);
        }
    }
    private function getSubsiteDomainByLocale($Locale,$SubsiteID)
    {
        $subsiteDomain = DB::query("SELECT sd.Domain From SubsiteDomain sd WHERE sd.SubsiteID = $SubsiteID AND sd.Locale = '$Locale'")->value();
        if ($subsiteDomain != null && $subsiteDomain != "") {
            if (!strpos($subsiteDomain, "https://") && !strpos($subsiteDomain, "http://")) {
                $preHost = "http://";
                if (Director::is_https()) {
                    $preHost = "https://";
                }
                $subsiteDomain = $preHost . $subsiteDomain;
            }

            $parentBase .= Controller::join_links($subsiteDomain, Director::baseURL());
        } else {
            $subsiteDomain = Director::absoluteBaseURL();
        }
        return $subsiteDomain;
    }
}
