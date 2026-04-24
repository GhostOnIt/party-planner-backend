# API Business Sur Devis

## Vue d'ensemble

Ce module remplace le `mailto` du plan Business par un flux applicatif:

- création de demande de devis côté client
- suivi de la demande côté client
- modération admin en pipeline Kanban
- planification de call et issue commerciale

## Endpoints client

- `POST /api/quote-requests`
  - Crée une demande de devis.
  - Protection anti-spam: `throttle:6,1`.
  - Validation stricte sur les champs textuels.
- `GET /api/quote-requests/mine`
  - Retourne les demandes de l'utilisateur connecté avec historique.

## Endpoints admin

- `GET /api/admin/quote-requests`
- `GET /api/admin/quote-requests/{quoteRequest}`
- `PATCH /api/admin/quote-requests/{quoteRequest}/stage`
- `PATCH /api/admin/quote-requests/{quoteRequest}/assign`
- `POST /api/admin/quote-requests/{quoteRequest}/notes`
- `POST /api/admin/quote-requests/{quoteRequest}/schedule-call`
- `PATCH /api/admin/quote-requests/{quoteRequest}/outcome`

## Pipeline personnalisable

- `GET /api/admin/quote-request-stages`
- `POST /api/admin/quote-request-stages`
- `PUT /api/admin/quote-request-stages/{stage}`
- `DELETE /api/admin/quote-request-stages/{stage}`
- `PATCH /api/admin/quote-request-stages/reorder`

## Traçabilité

Chaque action métier écrit un enregistrement dans `quote_request_activities`:

- création de demande
- changement d'étape
- assignation
- note interne
- call planifié
- issue commerciale

