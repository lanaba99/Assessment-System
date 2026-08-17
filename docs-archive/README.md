# Archived docs artifacts

`fromScribe.legacy-pre-phase6.json` was a one-off manual Postman export
(commit `cad5308`, "Setting Scribe for API documenting") predating the
current 26-group Scribe setup and the Phase 6/7 contract work. It is not
used anywhere in code, CI, or the README.

The canonical, current Postman collection is the live generated
`/docs.postman` route (see `config/scribe.php`), regenerated via
`php artisan scribe:generate`. Kept here for history only.
