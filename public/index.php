<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    $trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false;
    if ($trustedProxies) {
        $ips = explode(',', $trustedProxies);
        foreach ($ips as $i => $ip) {
            $ip = trim($ip);
            if ($ip === 'REMOTE_ADDR' && isset($_SERVER['REMOTE_ADDR'])) {
                $ips[$i] = $_SERVER['REMOTE_ADDR'];
            } else {
                $ips[$i] = $ip;
            }
        }
        Request::setTrustedProxies($ips, Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST);
    } elseif (($context['APP_ENV'] ?? 'dev') === 'prod' || isset($_SERVER['RENDER']) || isset($_ENV['RENDER']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        // Automatically trust loopback, private networks, and current request's remote address in production or on cloud hosts (e.g. Render/Heroku)
        Request::setTrustedProxies(
            ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', $_SERVER['REMOTE_ADDR'] ?? ''],
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST
        );
    }

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
