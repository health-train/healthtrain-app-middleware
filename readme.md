# healthtrain.app middleware

This application consists of several parts:

- Video tools and embeds
- Invite link redirecting to property backends
- Stripe checkout middleware
- Stripe webhook handling 

----

## Videos

Two functions
- Sproutvideo embed which allows for whitelisting video's based on domain, and adding tracking functionality
- Sproutvideo embed code converter tool. Allows content editors to convert any Sproutvideo embed code or link to a HealthTrain embed URL to be used in HealthTrain Platform

### Routes

- `video.healthtrain.app/tools/embed/`: Tool for converting Sproutvideo embed code to HealthTrain video embed URL
- `video.healthtrain.app/embed/sproutvideo/`: Sproutvideo embed pages. These views are cached in Symfony to improve performance

----

## Invite links

Provide short links for embedding in invite SMS messages, redirecting to the appropriate backend URL.

### Routes

- `(i|invite).healthtrain.app/i/` (HT_PROD)
- `(i|invite).healthtrain.app/it/` (HT_IT)
- `(i|invite).healthtrain.app/at/` (HT_AT)
- `(i|invite).healthtrain.app/ig/` (HTGER_PROD)
- `(i|invite).healthtrain.app/Invite/` (HTGER_PROD)
- `(i|invite).healthtrain.app/htger_at/` (HTGER_AT)

----

## Stripe checkout middleware

- Show product pages with options to select quantity of licenses to purchase
- Build checkout pages with custom configuration
- Display checkout success or failure pages
- Handle Stripe webhooks after checkout is completed
- Optionally allow automatic onboarding in HealthTrain Platform based on Stripe checkout data
- Notify external services such as marketing automation, CRM and Slack of billing events.

The Stripe middelware supports several products and seperate Stripe accounts for seperate sales companies within HealthTrain. Each product can consist of 

### Configuration

- Plans are configured in `./config/healthtrain/plans.yml`
- .env variables are mapped to product configs in `./config/healthtrain/config.yml`

### Routes

- `checkout.healthtrain.app/plan/{plan}`: Loads any plan available in Plan configuration and present in `templates/checkout/plan-{plan}.html.twig`
- `checkout.healthtrain.app/session/success`: Landing page after successful checkout
- `checkout.healthtrain.app/session/cancelled`: Landing page after cancelled checkout
- `checkout.healthtrain.app/portal/{configKey}`: Redirect to the Stripe customer portal. The config passed determines for which sales company the billing portal is loaded.

### Setup Stripe checkout from website

Websites should POST to the `/session/create` endpoint. The user will be redirected to a Stripe checkout page based on the Stripe Price metadata provided in the `productId`. The `productId` should be configured in the Plan configuration.

```
<form action="https://checkout.healthtrain.app/session/create" method="POST">
    <input type="hidden" name="productId" value="{productId}"/>
    <label for="licenses">Aantal licenties:</label>
    <input type="number" id="licenses" name="quantity" value="1" min="1" max="75"/>
    <button>Start now</button>
</form>
```

### Stripe price metadata

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

---

## Tooling

- Symfony
- Twig
- Stripe PHP SDK

## Dev setup

- `cd healthtrain-app-middleware/`
- `symfony proxy:domain:attach healthtrain "*.healthtrain"`
- `symfony proxy start`
- `symfony server:start`
- `symfony npm run watch`

URLs
- Domain served: [http://healthtrain.wip](http://healthtrain.wip)
- List of PROXY project: [http://127.0.0.1:7080/](http://127.0.0.1:7080/)

## PROD deploy

- SSH into server
- `cd healthtrain-app-middleware`
- `composer install`
- `npm run build`
- `APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear`

## Setup secrets

- Stripe secrets, billing url and webhook secrets for multiple accounts, both in livemode and testmode to support testmode in the production environment. Enabling testmode allows for easy trialing of functionality without affecting real sales data.
- Spotler secret for marketing automation
- SlackBot webhook url for notifying Slack channels
- Better stack secret for logging purposes


