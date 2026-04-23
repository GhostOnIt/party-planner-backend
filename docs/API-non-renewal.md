# API - Non-renouvellement et entitlements

## Endpoint `GET /user/subscription`

Réponse enrichie:

- `subscription`: abonnement compte (dernier abonnement connu).
- `quota`: quota créations.
- `has_subscription`: bool.
- `lifecycle`:
  - `phase`: `no_subscription|active|renewal_due|renewal_last_day|grace_period|archived|expired`
  - `days_to_expiry`
  - `grace_days_elapsed`
  - `archive_in_days`
  - `is_restricted`
  - `is_archived`

## Endpoint `GET /user/entitlements`

Réponse enrichie:

- `lifecycle`: état courant du cycle abonnement.
- `restrictions`:
  - `read_only`
  - `hide_advanced_analytics`
  - `disable_tablet_checkin`
  - `block_new_events_over_free_quota`
  - `block_guest_add_over_free_limit`

## Plan Business (sur devis)

Sur `POST /subscriptions/subscribe`, si le plan contient `sales.contact_required=true`,
l'API retourne:

- `422`
- `requires_sales_contact: true`
- message invitant à contacter l'équipe commerciale.
