<?php

namespace SubsiteFluentExtensions;

use SilverStripe\Forms\FormField;
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
    private function hasHttpScheme(string $domain): bool
    {
        return str_starts_with($domain, 'https://') || str_starts_with($domain, 'http://');
    }

    private function getDomainBySubsiteAndLocale(int $subsiteID, string $locale): ?string
    {
        $query = DB::prepared_query(
            'SELECT "Domain" FROM "SubsiteDomain" WHERE "SubsiteID" = ? AND "Locale" = ?',
            [$subsiteID, $locale]
        );
        $domain = $query->value();
        if (!$domain) {
            return null;
        }

        return (string) $domain;
    }

    public function LocaleLink($Locale)
    {
        if($this->owner->SubsiteID > 0)
        {
            $base = $this->getPageSubsiteDomainByLocale((string) $Locale, (int) $this->owner->SubsiteID);
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

    protected function getPageSubsiteDomainByLocale(string $locale, int $subsiteID): string
    {
        $subsiteDomain = $this->getDomainBySubsiteAndLocale($subsiteID, $locale);
        if ($subsiteDomain !== null && $subsiteDomain !== '') {
            if (!$this->hasHttpScheme($subsiteDomain)) {
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
        if (!$segmentField instanceof FormField || !($segmentField instanceof SiteTreeURLSegmentField)) {
            return $this;
        }

        // Mock frontend and get link to parent object / page
        $baseURL = FluentState::singleton()
            ->withState(function (FluentState $tempState): string {
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
                    $SubsiteID = (int) $this->owner->SubsiteID;
                    $parentBase = $this->getSubsiteDomainByLocale((string) $Locale, $SubsiteID);

                }

                // Get absolute base path

                // Join base / relative links
                return Controller::join_links($parentBase, $parentRelative);
            });

        //TODO add Extension point here in fork then PR
        $segmentField->setURLPrefix($baseURL);
        return $this;
    }

    public function updateLink(&$link, &$action, &$relativeLink): void
    {

        // Get appropriate locale for this record
        if ($this->owner->SubsiteID == 0) {
            parent::updateLink($link,$action,$relativeLink);
        }
        else
        {
            $Locale = Locale::getCurrentLocale()->Locale;
            $SubsiteID = (int) $this->owner->SubsiteID;
            $SubsiteBaseLink = $this->getSubsiteDomainByLocale((string) $Locale, $SubsiteID);
            if(str_contains((string) $link,(string) $Locale))
            {
                $link = str_replace($Locale."/","",$link);
            }

            $link = Controller::join_links($SubsiteBaseLink,$link);
        }
    }

    private function getSubsiteDomainByLocale(string $locale, int $subsiteID): string
    {
        $subsiteDomain = $this->getDomainBySubsiteAndLocale($subsiteID, $locale);
        if ($subsiteDomain !== null && $subsiteDomain !== '') {
            if (!$this->hasHttpScheme($subsiteDomain)) {
                $preHost = "http://";
                if (Director::is_https()) {
                    $preHost = "https://";
                }

                $subsiteDomain = $preHost . $subsiteDomain;
            }
        } else {
            $subsiteDomain = Director::absoluteBaseURL();
        }

        return $subsiteDomain;
    }
}
