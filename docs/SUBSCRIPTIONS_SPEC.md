# Spécification fonctionnelle — Abonnements dynamiques & Plans Admin (Party Planner)

## 1) Objectif

Mettre en place un **système d’abonnement dynamique** (plans administrables côté admin) permettant :

-   de définir des **plans** (nom, description, prix, durée) ;
-   de définir des **droits** et **limitations** par plan (features + quotas/limits) ;
-   de gérer un modèle **mensuel** (par défaut **30 jours**) avec **compteur de créations d’événements** (création + duplication) ;
-   d’offrir des **top-ups / packs de crédits** pour augmenter le quota de créations dans une période donnée, sans changer de plan ;
-   de garder un système clair de **conditionnement (gating)** backend (la source de vérité) + UX frontend.

Ce document décrit uniquement le **fonctionnel** (pas l’implémentation).

---

## 2) Concepts (glossaire)

-   **Plan** : offre commerciale (ex: Essai, PRO, AGENCE). Contient un prix, une durée, et un ensemble d’entitlements.
-   **Entitlements** : droits effectifs d’un utilisateur/compte. Deux catégories :
    -   **Features** : booléens (ex: `budget.enabled`, `exports.pdf`).
    -   **Limits / Quotas** : nombres (ex: `events.creations_per_period`, `guests.max_per_event`).
-   **Scope** :
    -   **Compte (Account)** : s’applique au compte utilisateur/organisation.
    -   **Événement (Event)** : s’applique à un événement particulier.
-   **Période de facturation (billing period)** : fenêtre temporelle du plan (ex: 30 jours) servant de base aux quotas “par période”.
-   **Top-up / Pack de crédits** : achat additionnel qui ajoute des crédits de création d’événements sur la période en cours (recommandé).
-   **Add-ons** : options (WhatsApp/SMS, branding, reporting, assistance) activables indépendamment du plan principal.

---

## 3) Portée (Scope) : compte vs événement

### 3.1 Recommandation

-   **Plan principal = Scope Compte**
    -   cohérent avec des offres “par mois” et des quotas “créations/mois”.
-   **Scope Événement** réservé à des cas d’“upgrade ponctuel” (optionnel), idéalement sous forme d’add-on ou “event pass”.

### 3.2 Règle de calcul des droits effectifs

Les droits effectifs utilisés par le produit doivent être déterminés de manière prévisible :

-   Pour les features : l’activation effective est un **OR** entre ce que donne le plan compte et un éventuel override événement.
-   Pour les limits : la valeur effective est généralement le **maximum** (ex: `-1` illimité > tout).

Convention : `-1` signifie **illimité** (JSON-safe, simple à comparer).

---

## 4) Quotas “créations d’événements par période”

### 4.1 Définition

Le plan de compte fournit un quota :

-   `events.creations_per_billing_period = N` (ex: PRO = 200)

### 4.2 Ce qui consomme un crédit

Consomme **1 crédit** :

-   **Création** d’un événement
-   **Duplication** d’un événement (considérée comme une création)

Ne consomme pas (par défaut) :

-   édition, archivage, suppression, changement de statut, etc.

### 4.3 Règle de non-remboursement

Si un événement est supprimé après création :

-   **le crédit n’est pas rendu**.

### 4.4 Blocage

Si `remaining == 0` :

-   l’utilisateur **ne peut plus créer** ni **dupliquer** d’événement
-   jusqu’à :
    -   le **reset** de période (nouveau cycle payé), ou
    -   l’achat d’un **top-up** (pack de crédits), ou
    -   un **upgrade** de plan augmentant le quota.

---

## 5) Top-ups / Packs de crédits (Option A)

### 5.1 But

Permettre à un utilisateur ayant épuisé ses crédits de :

-   **continuer à créer** des événements immédiatement,
-   **sans** racheter un second abonnement,
-   **sans** dépendre de l’existence d’un événement.

### 5.2 Produit “pack”

Exemples de packs (à définir côté admin) :

-   `+1 créations`
-   `+2 créations`
-   `+10 créations`
-   `+50 créations`
-   `+200 créations`

Chaque pack ajoute `credits = X`.

### 5.3 Validité (recommandée)

Top-up lié à la période en cours :

-   valide **jusqu’au `billing_period_end`** du plan compte courant.

Variante (plus complexe, non recommandée au départ) :

-   validité “30 jours glissants” par top-up.

### 5.4 Calcul des crédits restants

Séparer les composantes :

-   `base_quota` : quota mensuel du plan
-   `topup_quota` : somme des top-ups payés sur la période
-   `used_quota` : créations + duplications sur la période

Formule :

-   `remaining = base_quota + topup_quota - used_quota`

### 5.5 UX attendue

Quand quota épuisé (création/duplication), afficher :

-   “Quota atteint”
-   crédits restants = 0
-   date de reset (fin de période)
-   CTA :
    -   acheter un pack (solution rapide)
    -   upgrade plan (option B)

Après paiement pack réussi :

-   rafraîchissement des entitlements
-   création/duplication redevient possible.

---

## 6) Upgrade de plan (Option B)

Permettre de passer vers un plan supérieur (ex: PRO → AGENCE) :

-   augmente `base_quota` et/ou active plus de features.

Règle recommandée pour la V1 :

-   les top-ups déjà achetés sur la période restent valides (pas de proration complexe).

---

## 7) “Abonnement événement” si quota compte = 0 (problème & approche)

### 7.1 Problème

Si un produit nécessite “créer l’événement puis payer”, alors quota=0 bloque l’achat.

### 7.2 Réponse fonctionnelle

Ne pas dépendre d’un événement existant pour monétiser un dépassement. Deux voies :

1. **Top-up (recommandé)** : achat scope compte → débloque création.
2. **Event pass (optionnel)** : achat scope compte qui donne :
    - `+1 création` (pour pouvoir créer l’événement),
    - et un “upgrade événement” (ex: invités illimités sur 1 event).

---

## 8) “Invités illimités” & autres limits

### 8.1 Recommandation

“Invités illimités” doit signifier :

-   illimité **par événement**

Soit :

-   `guests.max_per_event = -1`

Pareil pour collaborateurs illimités :

-   `collaborators.max_per_event = -1`

---

## 9) Features & Limits — catalogue fonctionnel (à administrer)

Ce catalogue sert de base à l’admin pour composer les offres.

### 9.1 Limits (quotas / capacités)

-   `events.creations_per_billing_period` (quota de créations + duplications par période)
-   `guests.max_per_event`
-   `collaborators.max_per_event`
-   `photos.max_per_event`
-   `exports.max_per_period` (optionnel)
-   `storage.max_mb` (optionnel)

### 9.2 Features (booléens)

-   `budget.enabled`
-   `planning.enabled`
-   `tasks.enabled`
-   `guests.manage` (gestion invités)
-   `guests.import` (import CSV/Excel)
-   `guests.export` (export)
-   `invitations.sms` (add-on)
-   `invitations.whatsapp` (add-on)
-   `collaborators.manage`
-   `roles_permissions.enabled` (rôles et permissions avancés + custom roles)
-   `exports.pdf`
-   `exports.excel`
-   `exports.csv`
-   `history.enabled`
-   `reporting.enabled`
-   `branding.custom`
-   `support.whatsapp_priority`
-   `support.dedicated`
-   `multi_client.enabled`
-   `assistance.human`

Remarque :

-   Certaines features peuvent être déduites d’autres.

---

## 10) Offres par défaut (plans de base)

### 10.1 Essai Gratuit — 14 jours

Découvrez Party Planner sans engagement

-   **Durée** : 14 jours
-   **Prix** : Gratuit
-   **Limits**
    -   `events.creations_per_billing_period = 1`
    -   `guests.max_per_event = 100`
    -   `collaborators.max_per_event = 1`
-   **Features**
    -   `budget.enabled = true`
    -   `planning.enabled = true`
    -   `tasks.enabled = true`
-   **Paiement**
    -   “pas de carte requise” (règle commerciale / UX)

### 10.2 PRO — 10 000 FCFA / mois

Pour organisateurs indépendants & freelances
Idéal pour les organisateurs réguliers

-   **Durée** : 30 jours
-   **Prix** : 10 000 FCFA
-   **Limits**
    -   `events.creations_per_billing_period = 200`
    -   `guests.max_per_event = -1` (illimités)
    -   `collaborators.max_per_event = -1` (illimités)
-   **Features**
    -   `budget.enabled = true`
    -   `planning.enabled = true`
    -   `tasks.enabled = true`
    -   `collaborators.manage = true`
    -   `support.whatsapp_priority = true`

### 10.3 AGENCE / ORGANISATION — 25 000 FCFA / mois

Pour agences, églises, ONG, entreprises

-   **Durée** : 30 jours
-   **Prix** : 25 000 FCFA
-   **Limits**
    -   `events.creations_per_billing_period = -1` (événements illimités)
    -   `guests.max_per_event = -1` (illimités)
    -   `collaborators.max_per_event = -1` (illimités)
-   **Features**
    -   `multi_client.enabled = true`
    -   `roles_permissions.enabled = true`
    -   `exports.pdf = true`
    -   `exports.excel = true`
    -   `history.enabled = true`
    -   `reporting.enabled = true`
    -   `support.dedicated = true`

### 10.4 Add-ons (Options supplémentaires)

-   `invitations.sms = true` — Invitations par SMS
-   `invitations.whatsapp = true` — Invitations WhatsApp
-   `branding.custom = true` — Branding personnalisé (optionnel)
-   `reporting.advanced = true` — Reporting avancé (optionnel)
-   `assistance.human = true` — Assistance humaine (optionnel)

---

## 11) Règles de conditionnement (gating) — principes

### 11.1 Backend = source de vérité

Le backend doit toujours :

-   refuser l’action si feature absente ou quota atteint,
-   renvoyer un message clair et structuré (ex: “quota atteint”, date de reset, offre suggérée).

### 11.2 Frontend = UX

Le frontend peut :

-   désactiver boutons,
-   afficher “Abonnement requis”,
-   proposer Top-up / Upgrade,
    mais ne doit jamais être la seule barrière.

---

## 12) Résumé des décisions prises

-   Quota “Événements 200” = **200 créations/duplications par période (mois)**.
-   **Duplication** décrémente le quota.
-   Suppression d’un événement **ne rend pas** le crédit.
-   Stratégie produit = **Option C** :
    -   **Top-up** (solution rapide) + **Upgrade** de plan.
-   “Invités illimités” = illimité **par événement**.
