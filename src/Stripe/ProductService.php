<?php

namespace Doublespark\IsotopeStripeBundle\Stripe;

use Stripe\StripeClient;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ProductService extends Bundle
{
    /**
     * @var string
     */
    protected string $apiKey = 'sk_test_51M1TjXFZjFMDVRd3FKtlGlQAe9Z1OQ9CkoFGvh5oLb0v7NzMDXwxI37VLFoZtBTaE13eiugjWm9QmoH62Zhbe78o00dCeI3Nbi';

    public function addProducts()
    {
        $stripe = new StripeClient($this->apiKey);
        $stripe->products->create([

        ]);
    }
}