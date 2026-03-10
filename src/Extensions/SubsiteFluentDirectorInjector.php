<?php

declare(strict_types=1);

namespace SubsiteFluentExtensions;

use Exception;
use SilverStripe\Admin\AdminRootController;
use SilverStripe\ORM\Connect\DatabaseException;
use SilverStripe\ORM\DB;
use SilverStripe\Subsites\Model\SubsiteDomain;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Injector\Injector;
use TractorCow\Fluent\Middleware\InitStateMiddleware;
use TractorCow\Fluent\Extension\FluentDirectorExtension;

class FluentDirectorExtensionInjector extends FluentDirectorExtension
{
    private function isAdminRequest(HTTPRequest $request): bool
    {
        $url = trim((string) $request->getURL(), '/');
        if ($url === '') {
            return false;
        }

        $adminBase = trim((string) AdminRootController::admin_url(), '/');
        if ($adminBase !== '' && ($url === $adminBase || str_starts_with($url, $adminBase . '/'))) {
            return true;
        }

        return str_starts_with($url, 'dev/');
    }

    private function getSubsiteLocaleByHost(string $host): ?string
    {
        if (!class_exists(SubsiteDomain::class)) {
            return null;
        }

        $host = strtolower((string) (explode(':', $host, 2)[0] ?? $host));
        $hostsToTry = array_unique([
            $host,
            preg_replace('/^www\./', '', $host),
        ]);

        foreach ($hostsToTry as $candidateHost) {
            if (!$candidateHost) {
                continue;
            }

            $query = DB::prepared_query(
                'SELECT "Locale"
                FROM "SubsiteDomain"
                WHERE ? LIKE replace("Domain", \'*\', \'%\')
                    AND "Locale" <> \'\'
                ORDER BY "IsPrimary" DESC
                LIMIT 1',
                [$candidateHost]
            );
            $locale = $query->value();
            if ($locale) {
                return (string) $locale;
            }
        }

        return null;
    }

    public function updateRules(&$rules): void
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
        Injector::inst()->create(InitStateMiddleware::class)->process($request, function (): void {
        });

        $defaultLocale = null;
        $subsiteLocale = null;
        $host = (string) $request->getHost();
        if ($host !== '' && !$this->isAdminRequest($request)) {
            try {
                $subsiteLocale = $this->getSubsiteLocaleByHost($host);
                if ($subsiteLocale) {
                    $defaultLocale = Locale::get()->find('Locale', $subsiteLocale);
                }
            } catch (DatabaseException) {
                // Database may be unavailable during build
            }
        }

        if(!$defaultLocale)
        {
            $defaultLocale = Locale::getDefault($host);
            if (!$defaultLocale) {
                return;
            }
        }

        // If we do not wish to detect the locale automatically, fix the home page route
        // to the default locale for this domain.
        if (!static::config()->get('detect_locale')) {
            // Respect existing home controller
            $homeRule = $originalRules[''] ?? null;
            if ($homeRule) {
                $rules[''] = [
                    'Controller' => $this->getRuleController($homeRule, $defaultLocale),
                    static::config()->get('query_param') => $defaultLocale->Locale,
                ];
            }
        }

        // If default locale doesn't have prefix, replace default route with
        // the default locale for this domain
        if ($subsiteLocale) {
            $urlSegmentRule = $originalRules['$URLSegment//$Action/$ID/$OtherID'] ?? null;
            if ($urlSegmentRule) {
                $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                    'Controller' => $this->getRuleController($urlSegmentRule, $defaultLocale),
                    static::config()->get('query_param') => $defaultLocale->Locale
                ];
            }
        } elseif (static::config()->get('disable_default_prefix')) {
            $urlSegmentRule = $originalRules['$URLSegment//$Action/$ID/$OtherID'] ?? null;
            if ($urlSegmentRule) {
                $rules['$URLSegment//$Action/$ID/$OtherID'] = [
                    'Controller' => $this->getRuleController($urlSegmentRule, $defaultLocale),
                    static::config()->get('query_param') => $defaultLocale->Locale
                ];
            }
        }

    }
}
