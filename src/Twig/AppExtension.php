<?php

namespace App\Twig;

use Composer\InstalledVersions;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'app_version' => InstalledVersions::getPrettyVersion('ojooss/unifi-overview') ?? 'dev',
        ];
    }
}
