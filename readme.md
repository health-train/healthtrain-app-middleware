# healthtrain.app redirects

Simple Symfony application for internal tools and redirects to other services.

Pages

- `video.healthtrain.wip/tools/embed/`: Tool for converting Sproutvideo embed code to HealthTrain video embed URL
- `checkout.healthtrain.wip`: Example pricing plan page (for demo purposes)
- `checkout.healthtrain.wip/session/success`: Landing page after successful checkout
- `checkout.healthtrain.wip/session/cancelled`: Landing page after cancelled checkout

Redirects

- `video.healthtrain.wip/embed/sv/`: Sproutvideo embed to proxy requests and support domain whitelisting 
- `invite.healthtrain.wip/` and `i.healthtrain.wip`: Invite URL redirects for each environment (HT_PROD, HT_AT, HT_IT, HTGER_PROD, HTGER_AT)
- `checkout.healthtrain.wip/session/create`: Create Stripe session and redirect to checkout
    - Checkout options can be managed with Stripe pricing metadata
- `checkout.healthtrain.wip/portal`: Redirect to Stripe Portal

## Setup Stripe checkout from website

Websites should POST to the `/session/create` endpoint. The user will be redirected to a Stripe checkout page based on the Stripe Price metadata.

```
<form action="https://checkout.healthtrain.wip/session/create" method="POST">
    <input type="hidden" name="priceId" value="[STRIPE PRICE ID]"/>
    <label for="licenses">Aantal licenties:</label>
    <input type="number" id="licenses" name="quantity" value="1" min="1" max="75"/>
    <button>Start now</button>
</form>
```

## Stripe price metadata

The following metadata can be attached to a Stripe price to adjust the behaviour of the Stripe checkout session

- `adjust_quantity` (true|false): Allow user to adjust quantity (Default: `false`)
- `adjust_quantity_min` (int): Minimum quantity allowed on checkout page (default: `1`)
- `adjust_quantity_max` (int): Maximum quantity allowed on checkout page (default: `75`)
- `allow_promotion_codes` (true|false): Allow user to enter promotion codes (default:`false`)
- `custom_text_after_submit` (string, markdown): Markdown formatted text to display below submit button (default: `null`)
- `custom_text_submit` (string, markdown): Markdown formatted text to display above submit button (default: `null`)
- `custom_text_terms` (string, markdown): Markdown formatted text to display next to terms consent checkbox (default: `null`)
- `locale` (string): Default locale for the checkout session (default: `"nl"`)
- `taxRateId` (string): Default Stripe tax rate ID to apply to checkout session (default: environment variable `STRIPE_DEFAULT_TAXRATE_ID`)
- `trial_period` (true|false): Allow customer to trial the product (default: `false`)
- `trial_period_days` (int): Duration of trial (default: `14`)

## Tooling

- Symfony
- Twig
- Stripe PHP SDK

## Dev setup

- `cd healthtrain-app-middleware/`
- `symfony proxy:domain:attach healthtrain`
- `symfony proxy start`
- `symfony server:start`

URLs
- Domain served: [http://healthtrain.wip](http://healthtrain.wip)
- List of PROXY project: [http://127.0.0.1:7080/](http://127.0.0.1:7080/)

## PROD deploy

- SSH into server
- `cd healthtrain-app-middleware/`
- `APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear`

## Setup secrets

- `STRIPE_SECRET_KEY` and `STRIPE_WEBHOOK_SECRET`
