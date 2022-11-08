<?php

namespace Doublespark\IsotopeStripeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class IsotopeStripeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}