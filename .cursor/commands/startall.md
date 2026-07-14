# startall

Start the local development services for this repository.

This command is intended for standard agents to launch the app environment using the repository Makefile or scripts.

Typical steps:
- `make services-up` to start MongoDB and MailHog
- `make laravel` to start the Laravel test server
- `make php` or `make dev` to start the PHP website

If needed, use `php -S` with `router.php` or the `frontend` build workflow described in the repository docs.
