# HealthTrain video

Simple Symfony application for internal tools and redirects to other services.

Pages

- `/tools/embed/`: Tool for converting Sproutvideo embed code to HealthTrain video embed URL

Redirects

- `video.healthtrain.app/embed/sv/`: Sproutvideo embed to proxy requests and support domain whitelisting 
- `invite.healthtrain.app/` and `i.healthtrain.app`: Invite URL redirects for each environment (HT_PROD, HT_AT, HT_IT, HTGER_PROD, HTGER_AT)

## Tooling

- Symfony
- Twig

## Dev setup

- `cd my-project/`
- `symfony proxy:domain:attach my-domain`
- `symfony proxy:domain:attach healthtrain`
- `symfony server:start`

URLs
- Domain served: [http://healthtrain.wip](http://healthtrain.wip)
- List of PROXY project: [http://127.0.0.1:7080/](http://127.0.0.1:7080/)

## PROD deploy

- SSH into server
- `cd my-project/`
- `APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear`