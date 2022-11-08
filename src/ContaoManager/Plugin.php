<?php

declare(strict_types=1);

namespace Doublespark\IsotopeStripeBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Doublespark\IsotopeStripeBundle\IsotopeStripeBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(IsotopeStripeBundle::class)->setLoadAfter(['isotope'])
        ];
    }
}