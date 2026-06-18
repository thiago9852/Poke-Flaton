<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();
        $timezone = $_ENV['APP_TIMEZONE'] ?? $_SERVER['APP_TIMEZONE'] ?? 'America/Sao_Paulo';
        date_default_timezone_set($timezone);
    }
}
