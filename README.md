Stripe payment module for Isotope
-----------------------------
Adds stripe payments to Isotope e-commerce.

Configuration
-------------
-------------
The module will attempt to automatically create a webhook in Stripe for confirming payments.

This may need to be done manually in some cases. Please confirm that a webhook has been created
in Stripe under `Developers > Webhooks`.

The webhook URL should be:
`[WEBSITE_URL]/_isotope/postsale/pay/[PAYMENT_MODULE_ID]`

`PAYMENT_MODULE_ID` should be the record ID of the payment module created in Isotope's settings.

The webhook should be listening for the following events:
- checkout.session.completed

Once the webhook is created, the webhook's "Signing secret" should be set on the module's "Webhook secret" field.
If the webhook was automatically created, this will autofill with the correct value otherwise it can get found in the
Stripe dashboard.