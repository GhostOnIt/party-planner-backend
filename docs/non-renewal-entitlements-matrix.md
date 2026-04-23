# Non-renouvellement et matrice entitlements

## Phases de cycle de vie

- `active`: abonnement valide, accès complet selon plan.
- `renewal_due`: abonnement proche de l'expiration (J-7 à J-2).
- `renewal_last_day`: veille / dernier jour avant expiration (J-1/J0).
- `grace_period`: abonnement expiré, mode restreint actif.
- `archived`: période de grâce dépassée, ressources archivées (sans suppression).
- `expired`: abonnement expiré sans phase supplémentaire.

## Séquence recommandée

1. J-7: rappel renouvellement (in-app + email/SMS).
2. J-1: dernier rappel avec impact.
3. M+1: passage en `grace_period` (actions restreintes).
4. M+3+: passage en `archived`.
5. Paiement confirmé: restauration immédiate `active`.

## Stratégie plans (cible produit)

- **Gratuit**: 2 événements actifs, 30 invités/event, 0 collaborateur, watermark PDF.
- **Starter (3 500 FCFA/mois)**: 10 événements, 150 invités/event, 2 collaborateurs, 50 photos/event.
- **Pro (9 900 FCFA/mois)**: événements illimités, 500 invités/event, 10 collaborateurs + rôles, check-in tablette, analytics avancés.
- **Business (sur devis)**: multi-comptes, invités illimités, intégrations API, SLA/support dédié.

## Règles de restriction en mode non-renouvellement

- Les données existantes restent conservées.
- Les actions de création/extension sont limitées au seuil gratuit.
- Les fonctionnalités avancées (analytics/exports avancés/check-in tablette) sont restreintes.
- Le réabonnement restaure immédiatement l'accès complet.
