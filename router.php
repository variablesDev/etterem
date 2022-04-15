<?php

function registerRoutes($routes)
{
    return function ($method, $path) use ($routes) {
        $matchedRoute = [
            'handler' => 'notFoundHandler',
            'vars' => [],
        ];
        foreach ($routes as $route) {
            $matchedRoute['handler'] = 'notFoundHandler';
            $matchedRoute['vars'] = [];

            if ($route[0] !== $method) {
                continue;
            }

            if ($route[1] === $path) {
                $matchedRoute['handler'] = $route[2];
                break;
            }

            $registeredParts = explode("/", $route[1]);
            $runtimeParts = explode("/", $path);
            if (count($registeredParts) !== count($runtimeParts)) {
                continue;
            }

            $numberOfMatchedParts = 0;
            foreach ($runtimeParts as $i => $part) {
                if ($part === $registeredParts[$i]) {
                    $numberOfMatchedParts++;
                    continue;
                }

                $key = get_string_between($registeredParts[$i], "{", "}");
                if ($key) {
                    $matchedRoute['vars'][$key] = $part;
                    $numberOfMatchedParts++;
                }
            }

            if ($numberOfMatchedParts === count($registeredParts)) {
                $matchedRoute['handler'] = $route[2];
                break;
            }
        }

        return $matchedRoute;
    };
}

function get_string_between($string, $start, $end)
{
    $string = ' ' . $string;
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    return substr($string, $ini, $len);
}
