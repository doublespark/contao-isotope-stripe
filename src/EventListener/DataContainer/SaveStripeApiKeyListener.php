<?php

namespace Doublespark\IsotopeStripeBundle\EventListener\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\Database;
use Contao\DataContainer;
use Contao\System;
use Stripe\WebhookEndpoint;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Callback(table="tl_iso_payment", target="fields.stripe_api_key.save", priority=100)
 */
class SaveStripeApiKeyListener
{
    /**
     * If an API key is set, attempt to create the webhook in Stripe that
     * will notify our site when a payment has been made
     * @param string $value
     * @param DataContainer $dc
     * @return string
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function __invoke(string $value, DataContainer $dc): string
    {
        $webhookCreated = false;

        if(!empty($value))
        {
            $url = System::getContainer()->get('router')->generate('isotope_postsale', ['mod' => 'pay', 'id' => $dc->id], UrlGeneratorInterface::ABSOLUTE_URL);

            $stripe = new \Stripe\StripeClient($value);

            $response = $stripe->webhookEndpoints->all();

            if($response)
            {
                foreach($response as $endpoint)
                {
                    if($endpoint->url === $url AND $endpoint->status === 'enabled')
                    {
                        $webhookCreated = true;
                        break;
                    }
                }
            }

            // Webhook does not appear to exist, create it now
            if(!$webhookCreated)
            {
                $response = $stripe->webhookEndpoints->create([
                    'description'    => 'Isotope Stripe module callback URL',
                    'url'            => $url,
                    'enabled_events' => [
                        'checkout.session.completed',
                        'payment_intent.succeeded'
                    ]
                ]);

                // Save the secret key
                if(empty($dc->activeRecord->stripe_endpoint_secret))
                {
                    if($response instanceof WebhookEndpoint)
                    {
                        Database::getInstance()->prepare('UPDATE tl_iso_payment SET stripe_endpoint_secret=? WHERE id=?')->execute($response->secret,$dc->id);
                    }
                }
            }
        }

        return $value;
    }
}