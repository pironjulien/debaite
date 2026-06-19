# Debaite

Debaite is a cinematic AI debate arena: one prompt, two opposing voices, one structured contradiction engine.

Live demo: https://julienpiron.fr/debaite/

## What is included

- Static frontend: `index.html`, `style.css`, `script.js`
- PHP API relay: `api/generate.php`
- Google OAuth access flow: `api/google-start.php`, `api/google-callback.php`, `api/session.php`, `api/logout.php`
- Access and trial logic: `api/lib/access.php`
- WebP visual assets used by the live interface

No API key, OAuth secret, password, deployment credential, runtime database, or local environment file is included.

## Required runtime configuration

Configure these values on the server or in a local `.env` file that is never committed:

```env
DEEPSEEK_API_KEY=
DEBAITE_APP_SECRET=
DEBAITE_GOOGLE_CLIENT_ID=
DEBAITE_GOOGLE_CLIENT_SECRET=
DEBAITE_GOOGLE_ALLOWED_EMAILS=
DEBAITE_GOOGLE_REDIRECT_URI=
```

Optional limits:

```env
DEBAITE_TRIAL_DEBATE_LIMIT=1
DEBAITE_TRIAL_STEP_LIMIT=8
DEBAITE_PUBLIC_IP_DAILY_STEP_LIMIT=40
DEBAITE_CONTACT_URL=https://twitter.com/julienpironfr
```

## Deployment notes

The app is designed for Apache + PHP and is currently served under `/debaite/`.

The `.htaccess` file blocks runtime/private files and applies a strict Content Security Policy. If you deploy under another path, review `api/lib/access.php` and the OAuth redirect URI accordingly.

## License

Licensed under the PolyForm Strict License 1.0.0.

Commercial use, distribution, modifications, derivative works, hosted deployments, or any use outside the license require a separate written permission from Julien Piron.
