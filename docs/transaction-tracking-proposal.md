# Proposition: suivi des entrees et transactions

## Objectif
Construire un suivi fiable du cycle complet paiement -> abonnement -> revenu, avec corrélation métier et exploitation produit.

## Evenements normalises (backend)

Canal suggere: `payments` (distinct des logs techniques applicatifs).

- `payment_initiated`
- `payment_provider_pending`
- `payment_failed`
- `payment_timeout_expired`
- `payment_completed`
- `subscription_activated`
- `subscription_downgrade_blocked`

## Champs minimum par evenement

- `occurred_at` (ISO-8601 UTC)
- `event_name`
- `user_id`
- `subscription_id`
- `payment_id`
- `transaction_reference`
- `idempotency_key`
- `provider` (`mtn`, `airtel`)
- `plan_id`, `plan_slug`, `plan_name`
- `amount`, `currency`
- `status` (metier interne)
- `provider_status` (si present)
- `failure_reason` (ex: `LOW_BALANCE`, `TIMEOUT`, `REJECTED`)
- `metadata` (payload complémentaire)

## Correlation et persistance

1. Emettre un evenement a chaque transition de statut dans `PaymentService` et `SubscriptionController`.
2. Conserver la correlation via `payment_id` + `transaction_reference` + `idempotency_key`.
3. Stocker les evenements normalises dans `activity_logs` (phase 1), puis dans une table dediee `payment_events` (phase 2) pour analytics rapides.

## KPIs recommandes

- Taux de conversion initiation -> paiement complete
- Taux d'echec global et par provider
- Taux d'echec par cause (`LOW_BALANCE`, timeout, rejet)
- Duree mediane initiation -> completion
- Nombre de downgrades bloques
- Revenu brut journalier / mensuel par plan

## Vue admin recommandee

Un tableau de suivi avec filtres:
- periode
- provider
- plan
- statut interne
- cause echec

Colonnes clés:
- utilisateur
- plan/service
- montant
- provider
- statut
- raison echec
- reference transaction
- date/heure

## Plan d'implementation progressif

1. Phase 1 (rapide): normaliser les logs + enrichir payloads d'`activity_logs`.
2. Phase 2: table `payment_events` + endpoint admin dédié.
3. Phase 3: dashboard KPI (conversion, revenus, taux d'echec, temps de completion).
