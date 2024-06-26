<?php

namespace Knuckles\Scribe\Matching;

use Dingo\Api\Routing\RouteCollection;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Knuckles\Scribe\Tools\RoutePatternMatcher;

class RouteMatcher implements RouteMatcherInterface
{
    public function getRoutes(array $routeRules = [], string $router = 'laravel'): array
    {
        $usingDingoRouter = strtolower($router) == 'dingo';

        return $this->getRoutesToBeDocumented($routeRules, $usingDingoRouter);
    }

    private function getRoutesToBeDocumented(array $routeRules, bool $usingDingoRouter = false): array
    {
        $allRoutes = $this->getAllRoutes($usingDingoRouter);

        $matchedRoutes = [];

        foreach ($routeRules as $routeRule) {
            $includes = $routeRule['include'] ?? [];

            foreach ($allRoutes as $route) {
                if (is_array($route)) {
                    $route = new LumenRouteAdapter($route);
                }

                if ($this->shouldExcludeRoute($route, $routeRule)) {
                    continue;
                }

                $existingUris = array_map(function (MatchedRoute $matchedRoute) {
                    return $matchedRoute->getRoute()->methods[0] . ' ' . $matchedRoute->getRoute()->uri;
                }, $matchedRoutes);

                if ($this->shouldIncludeRoute($route, $routeRule, $includes, $usingDingoRouter, $existingUris)) {
                    $matchedRoutes[] = new MatchedRoute($route, $routeRule['apply'] ?? []);
                }
            }
        }

        return $matchedRoutes;
    }

    private function getAllRoutes(bool $usingDingoRouter)
    {
        if (! $usingDingoRouter) {
            return RouteFacade::getRoutes();
        }

        /** @var \Dingo\Api\Routing\Router $router */
        $router = app(\Dingo\Api\Routing\Router::class);
        $allRouteCollections = $router->getRoutes();

        return collect($allRouteCollections)
            ->flatMap(function (RouteCollection $collection) {
                return $collection->getRoutes();
            })->toArray()
        ;
    }

    private function shouldIncludeRoute(Route $route, array $routeRule, array $mustIncludes, bool $usingDingoRouter, array $existingUris): bool
    {
        if (in_array($route->methods[0] . ' ' . $route->uri, $existingUris)) {
            return false;
        }

        if (RoutePatternMatcher::matches($route, $mustIncludes)) {
            return true;
        }

        $matchesVersion = true;
        if ($usingDingoRouter) {
            $matchesVersion = ! empty(array_intersect($route->versions(), $routeRule['match']['versions'] ?? []));
        }

        $domainsToMatch = $routeRule['match']['domains'] ?? [];
        $pathsToMatch = $routeRule['match']['prefixes'] ?? [];

        return Str::is($domainsToMatch, $route->getDomain()) && Str::is($pathsToMatch, $route->uri())
            && $matchesVersion;
    }

    private function shouldExcludeRoute(Route $route, array $routeRule): bool
    {
        $excludes = $routeRule['exclude'] ?? [];

        // Exclude this package's routes
        $excludes[] = 'scribe';
        $excludes[] = 'scribe.*';

        // Exclude Laravel Telescope routes
        if (class_exists("Laravel\Telescope\Telescope")) {
            $excludes[] = 'telescope/*';
        }

        return RoutePatternMatcher::matches($route, $excludes);
    }
}
