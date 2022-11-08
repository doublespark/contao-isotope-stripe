<?php

namespace Doublespark\IsotopeStripeBundle\Isotope\Model\Payment;

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Input;
use Contao\Module;
use Contao\System;
use Haste\Util\StringUtil as HasteStringUtil;
use Isotope\Interfaces\IsotopeProductCollection;
use Isotope\Model\Payment\Postsale;
use Isotope\Model\ProductCollection\Order;
use Isotope\Module\Checkout;
use Psr\Log\LogLevel;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

/**
 * 1. getPostsaleOrder is called first, this retrieves the Stripe checkoutSession and sets it on the module
 * 2. processPostsale is then called and can use to checkoutSession to validate the order
 */
class Stripe extends Postsale
{
    protected ?Session $checkoutSession = null;

    public function checkoutForm(IsotopeProductCollection $objOrder, Module $objModule)
    {
        \Stripe\Stripe::setApiKey($this->stripe_api_key);

        $currency = strtolower($objOrder->getCurrency());

        $arrCheckoutData = [
            'client_reference_id' => $objOrder->getUniqueId(),
            'mode'                => 'payment',
            'success_url'         => Checkout::generateUrlForStep(Checkout::STEP_COMPLETE, $objOrder, null, true),
            'cancel_url'          => Checkout::generateUrlForStep(Checkout::STEP_REVIEW, $objOrder, null, true),
        ];

        $arrLineItems = [];

        foreach($objOrder->getItems() as $item)
        {
            // Convert to pennies
            $price = number_format(($item->getPrice()*100) , 0, '', '');

            $arrLineItems[] = [
                'quantity'   => $item->quantity,
                'price_data' => [
                    'currency'    => $currency,
                    'unit_amount' => $price,
                    'product_data' => [
                        'name' => $item->getName()
                    ]
                ]
            ];
        }

        $arrCheckoutData['line_items'] = $arrLineItems;

        if($objOrder->hasShipping())
        {
            $shippingName  = $objOrder->getShippingMethod()->getLabel();
            $shippingPrice = $objOrder->getShippingMethod()->getPrice();

            $arrShippingOptions = [
                [
                    'shipping_rate_data' => [
                        'type' => 'fixed_amount',
                        'fixed_amount' => [
                            'currency' => $currency,
                            'amount'   => number_format(($shippingPrice*100) , 0, '', '')
                        ],
                        'display_name' => $shippingName
                    ]
                ]
            ];

            $arrCheckoutData['shipping_options'] = $arrShippingOptions;
        }

        // Handle discounts
        $arrCouponLabels = [];
        $couponAmount = 0;

        foreach ($objOrder->getSurcharges() as $objSurcharge)
        {
            if(!$objSurcharge->addToTotal || $objSurcharge->type !== 'rule')
            {
                continue;
            }

            $arrCouponLabels[] = $objSurcharge->label;
            $couponAmount      = $couponAmount + $objSurcharge->total_price;
        }

        $couponId = '';

        // We have discounts to apply
        // In order to apply the discount, we have to dynamically create a coupon which
        // represents it and then apply it to the checkout session
        if(count($arrCouponLabels) > 0 AND $couponAmount < 0)
        {
            // Covert amount to positive int and convert into pennies
            $couponAmount = number_format((($couponAmount*-1.0)*100) , 0, '', '');

            $stripe = new \Stripe\StripeClient($this->stripe_api_key);

            $couponId = $objOrder->id.'_'.strtoupper(substr(md5($objOrder->id.$objOrder->getTotal()),0,6));

            $stripe->coupons->create([
                'id'         => $couponId,
                'name'       => implode(', ', $arrCouponLabels),
                'currency'   => $currency,
                'amount_off' => $couponAmount
            ]);
        }

        if(!empty($couponId))
        {
            $arrCheckoutData['discounts'] = [
                ['coupon' => $couponId]
            ];

            $arrCheckoutData['metadata'] = [
                'created_coupon_id' => $couponId
            ];
        }

        $checkout_session = \Stripe\Checkout\Session::create($arrCheckoutData);

        header("HTTP/1.1 303 See Other");
        header("Location: " . $checkout_session->url);
    }

    public function processPostsale(IsotopeProductCollection $objOrder)
    {
        if($this->checkoutSession->status === 'complete')
        {
            if($objOrder instanceof Order)
            {
                if($objOrder->isCheckoutComplete())
                {
                    System::getContainer()->get('monolog.logger.contao')->log(
                        LogLevel::ERROR,
                        "Stripe: checkout for Order ID already ".$objOrder->id." completed",
                        ['contao' => new ContaoContext('Doublespark\IsotopeStripeBundle\Isotope\Model\Payment\Stripe::processPostsale', TL_ERROR)]
                    );

                    return new Response();
                }

                if(!$objOrder->checkout())
                {
                    System::getContainer()->get('monolog.logger.contao')->log(
                        LogLevel::ERROR,
                        "Stripe:  checkout for Order ID ".$objOrder->id." failed",
                        ['contao' => new ContaoContext('Doublespark\IsotopeStripeBundle\Isotope\Model\Payment\Stripe::processPostsale', TL_ERROR)]
                    );

                    return new Response('', Response::HTTP_INTERNAL_SERVER_ERROR);
                }

                $objOrder->setDatePaid(time());
                $objOrder->updateOrderStatus($this->new_order_status);

                $objOrder->save();

                $metaData = $this->checkoutSession->metadata->toArray();

                // If we created a coupon for this order, remove it now so that we don't clutter the dashboard with coupons
                if(isset($metaData['created_coupon_id']))
                {
                    $stripe = new \Stripe\StripeClient($this->stripe_api_key);
                    $stripe->coupons->delete($metaData['created_coupon_id']);
                }
            }
        }
    }

    protected function getOrderFromCheckoutSession(Session $checkoutSession): ?Order
    {
        if($checkoutSession->status === 'complete')
        {
            $objOrder = Order::findBy('uniqid',$checkoutSession->client_reference_id);

            if($objOrder)
            {
                return $objOrder;
            }
        }

        return null;
    }

    public function getPostsaleOrder()
    {
        \Stripe\Stripe::setApiKey($this->stripe_api_key);

        $endpoint_secret = $this->stripe_endpoint_secret;

        $payload = @file_get_contents('php://input');
        $event   = null;

        try {

            $event = Event::constructFrom(
                json_decode($payload, true)
            );

        } catch(\UnexpectedValueException $e) {

            // Invalid payload
            System::getContainer()->get('monolog.logger.contao')->log(
                LogLevel::ERROR,
                "Stripe: webhook error while parsing basic request",
                ['contao' => new ContaoContext('Doublespark\IsotopeStripeBundle\Isotope\Model\Payment\Stripe::getPostsaleOrder', TL_ERROR)]
            );

            http_response_code(400);
            exit();
        }

        if($endpoint_secret)
        {
            // Only verify the event if there is an endpoint secret defined
            // Otherwise use the basic decoded event
            $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

            try {

                $event = Webhook::constructEvent($payload, $sig_header, $endpoint_secret);

            } catch(\Stripe\Exception\SignatureVerificationException $e) {

                // Invalid signature
                System::getContainer()->get('monolog.logger.contao')->log(
                    LogLevel::ERROR,
                    "Stripe: webhook error while validating signature: ". $e->getMessage(),
                    ['contao' => new ContaoContext('Doublespark\IsotopeStripeBundle\Isotope\Model\Payment\Stripe::getPostsaleOrder', TL_ERROR)]
                );

                http_response_code(400);
                exit();
            }
        }

        // Handle the event
        switch ($event->type)
        {
            case 'checkout.session.completed':
                $this->checkoutSession = $event->data->object;
                break;

            default:
                // Unexpected event type
                System::getContainer()->get('monolog.logger.contao')->log(
                    LogLevel::ERROR,
                    'Received unknown event type: '. $event->type,
                    ['contao' => new ContaoContext('Doublespark\IsotopeStripeBundle\Isotope\Model\Payment\Stripe::getPostsaleOrder', TL_ERROR)]
                );

        }

        return $this->getOrderFromCheckoutSession($this->checkoutSession);
    }
}
