# Party Planner API Documentation

## Introduction

Party Planner est une API RESTful pour la gestion d'événements. Cette documentation décrit tous les endpoints disponibles pour le frontend React.

### URL de base

```
Production: https://api.party-planner.com
Développement: http://localhost:8000
```

### Authentification

L'API utilise **Laravel Sanctum** avec des tokens Bearer.

```http
Authorization: Bearer <votre-token>
```

### Format des réponses

Toutes les réponses sont en JSON. Les réponses réussies suivent ce format :

```json
{
    "data": { ... },
    "message": "Message de succès"
}
```

Les erreurs suivent ce format :

```json
{
    "message": "Description de l'erreur",
    "errors": {
        "field": ["Détail de l'erreur"]
    }
}
```

### Codes HTTP

| Code | Description |
|------|-------------|
| 200 | Succès |
| 201 | Ressource créée |
| 204 | Succès sans contenu |
| 400 | Requête invalide |
| 401 | Non authentifié |
| 403 | Non autorisé |
| 404 | Ressource non trouvée |
| 422 | Erreur de validation |
| 500 | Erreur serveur |

---

## 1. Authentification

### Inscription

```http
POST /api/auth/register
```

**Corps de la requête :**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Réponse (201) :**
```json
{
    "message": "Inscription réussie.",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "created_at": "2024-01-15T10:30:00Z"
    },
    "token": "1|abc123..."
}
```

### Connexion

```http
POST /api/auth/login
```

**Corps de la requête :**
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

**Réponse (200) :**
```json
{
    "message": "Connexion réussie.",
    "user": { ... },
    "token": "2|xyz789..."
}
```

### Déconnexion

```http
POST /api/auth/logout
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "message": "Déconnexion réussie."
}
```

### Mot de passe oublié

```http
POST /api/auth/forgot-password
```

**Corps de la requête :**
```json
{
    "email": "john@example.com"
}
```

**Réponse (200) :**
```json
{
    "message": "We have emailed your password reset link."
}
```

### Réinitialiser le mot de passe

```http
POST /api/auth/reset-password
```

**Corps de la requête :**
```json
{
    "token": "reset-token-from-email",
    "email": "john@example.com",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

### Modifier le mot de passe (authentifié)

```http
PUT /api/auth/password
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "current_password": "oldpassword",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
}
```

### Renvoyer l'email de vérification

```http
POST /api/auth/email/verification-notification
Authorization: Bearer <token>
```

### Vérifier l'email

```http
GET /api/auth/verify-email/{id}/{hash}
Authorization: Bearer <token>
```

### Utilisateur courant

```http
GET /api/user
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "email_verified_at": "2024-01-15T10:30:00Z",
    "avatar_url": null,
    "created_at": "2024-01-15T10:30:00Z"
}
```

---

## 2. Événements

### Lister les événements

```http
GET /api/events
Authorization: Bearer <token>
```

**Paramètres de requête :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| status | string | Filtrer par statut |
| type | string | Filtrer par type |
| search | string | Rechercher par titre |
| per_page | integer | Nombre par page (défaut: 15) |

**Réponse (200) :**
```json
{
    "data": [
        {
            "id": 1,
            "title": "Mariage de Jean",
            "type": "mariage",
            "status": "planning",
            "date": "2024-06-15",
            "location": "Paris",
            "guest_count": 150,
            "budget_total": 50000,
            "created_at": "2024-01-15T10:30:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "last_page": 3,
        "per_page": 15,
        "total": 42
    }
}
```

### Créer un événement

```http
POST /api/events
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "title": "Mariage de Jean",
    "type": "mariage",
    "date": "2024-06-15",
    "time": "14:00",
    "location": "Paris, France",
    "description": "Un mariage magnifique",
    "expected_guests": 150,
    "budget": 50000,
    "theme": "Romantique"
}
```

**Types valides :** `mariage`, `anniversaire`, `baby_shower`, `soiree`, `brunch`, `autre`

### Voir un événement

```http
GET /api/events/{id}
Authorization: Bearer <token>
```

### Modifier un événement

```http
PUT /api/events/{id}
Authorization: Bearer <token>
```

### Supprimer un événement

```http
DELETE /api/events/{id}
Authorization: Bearer <token>
```

### Événement public (sans auth)

```http
GET /api/events/{id}/public
```

Retourne des informations limitées pour les invités non-authentifiés.

---

## 3. Invités

### Lister les invités

```http
GET /api/events/{eventId}/guests
Authorization: Bearer <token>
```

**Paramètres de requête :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| status | string | Filtrer par statut RSVP |
| search | string | Rechercher par nom |
| per_page | integer | Nombre par page |

**Réponse (200) :**
```json
{
    "data": [
        {
            "id": 1,
            "name": "Marie Dupont",
            "email": "marie@example.com",
            "phone": "+33612345678",
            "rsvp_status": "accepted",
            "plus_one": true,
            "dietary_restrictions": "Végétarien",
            "checked_in_at": null,
            "invitation_sent_at": "2024-01-20T10:00:00Z"
        }
    ],
    "stats": {
        "total": 100,
        "accepted": 75,
        "declined": 10,
        "pending": 15
    }
}
```

### Créer un invité

```http
POST /api/events/{eventId}/guests
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "name": "Marie Dupont",
    "email": "marie@example.com",
    "phone": "+33612345678",
    "plus_one": true,
    "plus_one_name": "Pierre Dupont",
    "dietary_restrictions": "Végétarien",
    "notes": "Amie de la mariée"
}
```

### Modifier un invité

```http
PUT /api/events/{eventId}/guests/{guestId}
Authorization: Bearer <token>
```

### Supprimer un invité

```http
DELETE /api/events/{eventId}/guests/{guestId}
Authorization: Bearer <token>
```

### Envoyer une invitation

```http
POST /api/events/{eventId}/guests/{guestId}/send-invitation
Authorization: Bearer <token>
```

### Check-in d'un invité

```http
POST /api/events/{eventId}/guests/{guestId}/check-in
Authorization: Bearer <token>
```

### Annuler le check-in

```http
POST /api/events/{eventId}/guests/{guestId}/undo-check-in
Authorization: Bearer <token>
```

---

## 4. Tâches

### Lister les tâches

```http
GET /api/events/{eventId}/tasks
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
[
    {
        "id": 1,
        "title": "Réserver le traiteur",
        "description": "Contacter 3 traiteurs pour devis",
        "status": "in_progress",
        "priority": "high",
        "due_date": "2024-02-15",
        "assigned_to": {
            "id": 2,
            "name": "Marie"
        },
        "completed_at": null
    }
]
```

### Créer une tâche

```http
POST /api/events/{eventId}/tasks
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "title": "Réserver le traiteur",
    "description": "Contacter 3 traiteurs pour devis",
    "priority": "high",
    "due_date": "2024-02-15",
    "assigned_to": 2
}
```

**Priorités valides :** `low`, `medium`, `high`

**Statuts valides :** `todo`, `in_progress`, `completed`, `cancelled`

### Modifier une tâche

```http
PUT /api/events/{eventId}/tasks/{taskId}
Authorization: Bearer <token>
```

### Supprimer une tâche

```http
DELETE /api/events/{eventId}/tasks/{taskId}
Authorization: Bearer <token>
```

### Marquer comme complétée

```http
POST /api/events/{eventId}/tasks/{taskId}/complete
Authorization: Bearer <token>
```

### Rouvrir une tâche

```http
POST /api/events/{eventId}/tasks/{taskId}/reopen
Authorization: Bearer <token>
```

---

## 5. Budget

### Voir le budget

```http
GET /api/events/{eventId}/budget
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "items": [
        {
            "id": 1,
            "category": "catering",
            "name": "Traiteur principal",
            "estimated_cost": 10000,
            "actual_cost": 11500,
            "paid": true,
            "paid_at": "2024-02-01T10:00:00Z",
            "notes": "Acompte versé"
        }
    ],
    "stats": {
        "total_estimated": 50000,
        "total_actual": 45000,
        "total_paid": 30000,
        "total_remaining": 15000,
        "budget_variance": -5000
    },
    "by_category": {
        "catering": {
            "estimated": 15000,
            "actual": 11500
        }
    }
}
```

### Statistiques du budget

```http
GET /api/events/{eventId}/budget/statistics
Authorization: Bearer <token>
```

### Créer un élément de budget

```http
POST /api/events/{eventId}/budget/items
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "category": "catering",
    "name": "Traiteur principal",
    "estimated_cost": 10000,
    "actual_cost": 11500,
    "notes": "Menu gastronomique"
}
```

**Catégories valides :** `location`, `catering`, `decoration`, `entertainment`, `photography`, `transportation`, `other`

### Modifier un élément

```http
PUT /api/events/{eventId}/budget/items/{itemId}
Authorization: Bearer <token>
```

### Supprimer un élément

```http
DELETE /api/events/{eventId}/budget/items/{itemId}
Authorization: Bearer <token>
```

### Marquer comme payé

```http
POST /api/events/{eventId}/budget/items/{itemId}/mark-paid
Authorization: Bearer <token>
```

### Marquer comme non payé

```http
POST /api/events/{eventId}/budget/items/{itemId}/mark-unpaid
Authorization: Bearer <token>
```

---

## 6. Photos / Galerie

### Lister les photos

```http
GET /api/events/{eventId}/photos
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "photos": [
        {
            "id": 1,
            "url": "https://storage.example.com/photos/1.jpg",
            "thumbnail_url": "https://storage.example.com/photos/1_thumb.jpg",
            "type": "moodboard",
            "caption": "Inspiration déco",
            "is_featured": true,
            "created_at": "2024-01-20T10:00:00Z"
        }
    ],
    "stats": {
        "total": 25,
        "moodboard": 10,
        "event_photo": 15
    },
    "can_add_photos": true,
    "remaining_slots": 75
}
```

### Statistiques des photos

```http
GET /api/events/{eventId}/photos/statistics
Authorization: Bearer <token>
```

### Uploader une photo

```http
POST /api/events/{eventId}/photos
Authorization: Bearer <token>
Content-Type: multipart/form-data
```

**Corps de la requête (form-data) :**
| Champ | Type | Description |
|-------|------|-------------|
| photo | file | Image (jpg, png, gif, webp) |
| type | string | `moodboard` ou `event_photo` |
| caption | string | Légende (optionnel) |

### Modifier une photo

```http
PUT /api/events/{eventId}/photos/{photoId}
Authorization: Bearer <token>
```

### Supprimer une photo

```http
DELETE /api/events/{eventId}/photos/{photoId}
Authorization: Bearer <token>
```

### Définir comme featured

```http
POST /api/events/{eventId}/photos/{photoId}/set-featured
Authorization: Bearer <token>
```

### Toggle featured

```http
POST /api/events/{eventId}/photos/{photoId}/toggle-featured
Authorization: Bearer <token>
```

### Suppression en masse

```http
POST /api/events/{eventId}/photos/bulk-delete
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "photo_ids": [1, 2, 3]
}
```

---

## 7. Collaborateurs

### Lister les collaborateurs

```http
GET /api/events/{eventId}/collaborators
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "collaborators": [
        {
            "id": 1,
            "user": {
                "id": 2,
                "name": "Marie Dupont",
                "email": "marie@example.com"
            },
            "role": "editor",
            "accepted_at": "2024-01-20T10:00:00Z"
        }
    ],
    "stats": {
        "total": 3,
        "editors": 2,
        "viewers": 1
    },
    "can_add_collaborator": true,
    "remaining_slots": 2
}
```

### Inviter un collaborateur

```http
POST /api/events/{eventId}/collaborators
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "email": "marie@example.com",
    "role": "editor"
}
```

**Rôles valides :** `editor`, `viewer`

### Modifier le rôle

```http
PUT /api/events/{eventId}/collaborators/{userId}
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "role": "viewer"
}
```

### Retirer un collaborateur

```http
DELETE /api/events/{eventId}/collaborators/{userId}
Authorization: Bearer <token>
```

### Accepter une invitation

```http
POST /api/events/{eventId}/collaborators/accept
Authorization: Bearer <token>
```

### Décliner une invitation

```http
POST /api/events/{eventId}/collaborators/decline
Authorization: Bearer <token>
```

### Quitter un événement

```http
POST /api/events/{eventId}/collaborators/leave
Authorization: Bearer <token>
```

### Renvoyer une invitation

```http
POST /api/events/{eventId}/collaborators/{userId}/resend
Authorization: Bearer <token>
```

### Mes collaborations

```http
GET /api/collaborations
Authorization: Bearer <token>
```

### Invitations en attente

```http
GET /api/collaborations/pending
Authorization: Bearer <token>
```

---

## 8. Notifications

### Lister les notifications

```http
GET /api/notifications
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "data": [
        {
            "id": "uuid-123",
            "type": "event_reminder",
            "data": {
                "event_id": 1,
                "event_title": "Mariage de Jean",
                "message": "L'événement est dans 7 jours"
            },
            "read_at": null,
            "created_at": "2024-01-25T10:00:00Z"
        }
    ],
    "unread_count": 5
}
```

### Notifications récentes

```http
GET /api/notifications/recent
Authorization: Bearer <token>
```

### Nombre non lues

```http
GET /api/notifications/unread-count
Authorization: Bearer <token>
```

### Marquer comme lue

```http
PUT /api/notifications/{notificationId}/read
Authorization: Bearer <token>
```

### Marquer toutes comme lues

```http
PUT /api/notifications/read-all
Authorization: Bearer <token>
```

### Supprimer une notification

```http
DELETE /api/notifications/{notificationId}
Authorization: Bearer <token>
```

### Supprimer les lues

```http
DELETE /api/notifications/clear-read
Authorization: Bearer <token>
```

### Paramètres de notification

```http
GET /api/notifications/settings
Authorization: Bearer <token>
```

### Modifier les paramètres

```http
PUT /api/notifications/settings
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "email_notifications": true,
    "push_notifications": true,
    "sms_notifications": false,
    "event_reminders": true,
    "task_reminders": true,
    "collaboration_updates": true
}
```

### Enregistrer un device token

```http
POST /api/user/device-tokens
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "token": "firebase-device-token",
    "platform": "android"
}
```

---

## 9. Abonnements

### Voir l'abonnement d'un événement

```http
GET /api/events/{eventId}/subscription
Authorization: Bearer <token>
```

**Réponse (200) :**
```json
{
    "subscription": {
        "id": 1,
        "plan": "pro",
        "status": "active",
        "starts_at": "2024-01-01T00:00:00Z",
        "ends_at": "2024-12-31T23:59:59Z",
        "guest_limit": 200,
        "collaborator_limit": null
    },
    "limits": {
        "guests": {
            "used": 150,
            "limit": 200,
            "remaining": 50
        },
        "collaborators": {
            "used": 3,
            "limit": null,
            "remaining": null
        }
    }
}
```

### S'abonner

```http
POST /api/events/{eventId}/subscription
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "plan": "pro",
    "payment_method": "mtn_mobile_money",
    "phone_number": "+237670000000"
}
```

### Mettre à niveau

```http
POST /api/events/{eventId}/subscription/upgrade
Authorization: Bearer <token>
```

### Annuler

```http
POST /api/events/{eventId}/subscription/cancel
Authorization: Bearer <token>
```

### Renouveler

```http
POST /api/events/{eventId}/subscription/renew
Authorization: Bearer <token>
```

### Calculer le prix

```http
GET /api/events/{eventId}/subscription/calculate-price
Authorization: Bearer <token>
```

**Paramètres de requête :**
| Paramètre | Type | Description |
|-----------|------|-------------|
| plan | string | `starter` ou `pro` |
| guest_count | integer | Nombre d'invités prévu |

### Vérifier les limites

```http
GET /api/events/{eventId}/subscription/check-limits
Authorization: Bearer <token>
```

### Mes abonnements

```http
GET /api/subscriptions
Authorization: Bearer <token>
```

---

## 10. Paiements

### Historique des paiements

```http
GET /api/payments
Authorization: Bearer <token>
```

### Initier un paiement

```http
POST /api/payments/initiate
Authorization: Bearer <token>
```

**Corps de la requête :**
```json
{
    "event_id": 1,
    "plan": "pro",
    "payment_method": "mtn_mobile_money",
    "phone_number": "+237670000000"
}
```

### Initier paiement MTN

```http
POST /api/payments/mtn/initiate
Authorization: Bearer <token>
```

### Initier paiement Airtel

```http
POST /api/payments/airtel/initiate
Authorization: Bearer <token>
```

### Statut d'un paiement

```http
GET /api/payments/{paymentId}/status
Authorization: Bearer <token>
```

### Polling d'un paiement

```http
GET /api/payments/{paymentId}/poll
Authorization: Bearer <token>
```

### Réessayer un paiement

```http
POST /api/payments/{paymentId}/retry
Authorization: Bearer <token>
```

---

## 11. Exports

### Export invités CSV

```http
GET /api/events/{eventId}/exports/guests/csv
Authorization: Bearer <token>
```

### Export invités PDF

```http
GET /api/events/{eventId}/exports/guests/pdf
Authorization: Bearer <token>
```

### Export invités Excel

```http
GET /api/events/{eventId}/exports/guests/xlsx
Authorization: Bearer <token>
```

### Export budget CSV

```http
GET /api/events/{eventId}/exports/budget/csv
Authorization: Bearer <token>
```

### Export budget PDF

```http
GET /api/events/{eventId}/exports/budget/pdf
Authorization: Bearer <token>
```

### Export budget Excel

```http
GET /api/events/{eventId}/exports/budget/xlsx
Authorization: Bearer <token>
```

### Export tâches CSV

```http
GET /api/events/{eventId}/exports/tasks/csv
Authorization: Bearer <token>
```

### Export tâches Excel

```http
GET /api/events/{eventId}/exports/tasks/xlsx
Authorization: Bearer <token>
```

### Rapport complet PDF

```http
GET /api/events/{eventId}/exports/report/pdf
Authorization: Bearer <token>
```

---

## 12. Templates d'événements

### Lister les templates

```http
GET /api/templates
Authorization: Bearer <token>
```

### Voir un template

```http
GET /api/templates/{templateId}
Authorization: Bearer <token>
```

### Templates par type

```http
GET /api/templates/type/{type}
Authorization: Bearer <token>
```

### Prévisualisation

```http
GET /api/templates/{templateId}/preview
Authorization: Bearer <token>
```

### Thèmes par type

```http
GET /api/templates/themes/{type}
Authorization: Bearer <token>
```

### Appliquer un template

```http
POST /api/events/{eventId}/templates/{templateId}/apply
Authorization: Bearer <token>
```

---

## 13. Invitations publiques

### Voir une invitation

```http
GET /api/invitations/{token}
```

**Réponse (200) :**
```json
{
    "event": {
        "title": "Mariage de Jean",
        "date": "2024-06-15",
        "location": "Paris"
    },
    "guest": {
        "name": "Marie Dupont"
    }
}
```

### Répondre à une invitation

```http
POST /api/invitations/{token}/respond
```

**Corps de la requête :**
```json
{
    "response": "accepted",
    "plus_one_attending": true,
    "plus_one_name": "Pierre",
    "dietary_restrictions": "Végétarien",
    "message": "Nous avons hâte d'y être!"
}
```

---

## 14. Dashboard & Statistiques

### Dashboard d'un événement

```http
GET /api/events/{eventId}/dashboard
Authorization: Bearer <token>
```

### Données de graphiques

```http
GET /api/dashboard/chart-data
Authorization: Bearer <token>
```

### Statistiques utilisateur

```http
GET /api/dashboard/user-stats
Authorization: Bearer <token>
```

---

## Enums & Types

### EventType (Type d'événement)
| Valeur | Label |
|--------|-------|
| `mariage` | Mariage |
| `anniversaire` | Anniversaire |
| `baby_shower` | Baby Shower |
| `soiree` | Soirée |
| `brunch` | Brunch |
| `autre` | Autre |

### EventStatus (Statut d'événement)
| Valeur | Label |
|--------|-------|
| `draft` | Brouillon |
| `planning` | En planification |
| `confirmed` | Confirmé |
| `completed` | Terminé |
| `cancelled` | Annulé |

### RsvpStatus (Statut RSVP)
| Valeur | Label |
|--------|-------|
| `pending` | En attente |
| `accepted` | Confirmé |
| `declined` | Décliné |
| `maybe` | Peut-être |

### TaskStatus (Statut de tâche)
| Valeur | Label |
|--------|-------|
| `todo` | À faire |
| `in_progress` | En cours |
| `completed` | Terminé |
| `cancelled` | Annulé |

### TaskPriority (Priorité)
| Valeur | Label |
|--------|-------|
| `low` | Basse |
| `medium` | Moyenne |
| `high` | Haute |

### BudgetCategory (Catégorie budget)
| Valeur | Label |
|--------|-------|
| `location` | Lieu |
| `catering` | Traiteur |
| `decoration` | Décoration |
| `entertainment` | Animation |
| `photography` | Photographie |
| `transportation` | Transport |
| `other` | Autre |

### CollaboratorRole (Rôle collaborateur)
| Valeur | Label | Description |
|--------|-------|-------------|
| `owner` | Propriétaire | Accès complet, peut supprimer l'événement |
| `editor` | Éditeur | Peut modifier l'événement et gérer les invités |
| `viewer` | Lecteur | Peut uniquement consulter l'événement |

### PaymentMethod (Méthode de paiement)
| Valeur | Label |
|--------|-------|
| `mtn_mobile_money` | MTN Mobile Money |
| `airtel_money` | Airtel Money |

### PaymentStatus (Statut paiement)
| Valeur | Label |
|--------|-------|
| `pending` | En attente |
| `completed` | Complété |
| `failed` | Échoué |
| `refunded` | Remboursé |

### PlanType (Type d'abonnement)
| Valeur | Prix de base | Invités inclus | Collaborateurs |
|--------|--------------|----------------|----------------|
| `starter` | 5 000 XAF | 50 | 2 max |
| `pro` | 15 000 XAF | 200 | Illimité |

### PhotoType (Type de photo)
| Valeur | Label |
|--------|-------|
| `moodboard` | Moodboard (inspiration) |
| `event_photo` | Photo événement |

---

## Webhooks (pour intégrations)

### Mobile Money - MTN
```http
POST /webhooks/mtn
```

### Mobile Money - Airtel
```http
POST /webhooks/airtel
```

### Stripe
```http
POST /webhooks/stripe
```

### Twilio SMS
```http
POST /webhooks/twilio/sms/status
POST /webhooks/twilio/sms/incoming
```

### Twilio WhatsApp
```http
POST /webhooks/twilio/whatsapp/status
POST /webhooks/twilio/whatsapp/incoming
```

---

## 15. Administration (Admin uniquement)

> **Note:** Tous les endpoints admin necessitent un utilisateur avec le role `admin`. Les utilisateurs non-admin recevront une erreur 403.

### Statistiques admin

```http
GET /api/admin/stats
Authorization: Bearer <token>
```

**Reponse (200):**
```json
{
    "stats": {
        "total_users": 150,
        "total_events": 450,
        "total_payments": 120,
        "revenue_total": 1500000
    }
}
```

### Donnees de graphiques admin

```http
GET /api/admin/chart-data
Authorization: Bearer <token>
```

**Parametres de requete:**
| Parametre | Type | Description |
|-----------|------|-------------|
| period | string | `week`, `month`, `year` |

### Lister les utilisateurs

```http
GET /api/admin/users
Authorization: Bearer <token>
```

**Parametres de requete (ListUsersRequest):**
| Parametre | Type | Description |
|-----------|------|-------------|
| search | string | Recherche par nom ou email |
| role | string | Filtrer par role (`admin`, `user`) |
| sort_by | string | `name`, `email`, `created_at`, `role` |
| sort_dir | string | `asc`, `desc` |
| per_page | integer | Nombre par page (1-100) |

### Voir un utilisateur

```http
GET /api/admin/users/{user}
Authorization: Bearer <token>
```

### Modifier le role d'un utilisateur

```http
PUT /api/admin/users/{user}/role
Authorization: Bearer <token>
```

**Corps de la requete (UpdateUserRoleRequest):**
```json
{
    "role": "admin"
}
```

**Roles valides:** `admin`, `user`

> **Note:** Un admin ne peut pas modifier son propre role.

### Supprimer un utilisateur

```http
DELETE /api/admin/users/{user}
Authorization: Bearer <token>
```

> **Note:** Un admin ne peut pas se supprimer lui-meme ni supprimer un autre admin.

### Lister tous les evenements

```http
GET /api/admin/events
Authorization: Bearer <token>
```

**Parametres de requete (ListEventsRequest):**
| Parametre | Type | Description |
|-----------|------|-------------|
| search | string | Recherche par titre |
| type | string | Filtrer par type d'evenement |
| status | string | Filtrer par statut |
| user_id | integer | Filtrer par proprietaire |
| from | date | Date de debut |
| to | date | Date de fin |
| sort_by | string | `title`, `date`, `created_at`, `status`, `type` |
| sort_dir | string | `asc`, `desc` |
| per_page | integer | Nombre par page (1-100) |

### Lister tous les paiements

```http
GET /api/admin/payments
Authorization: Bearer <token>
```

**Parametres de requete (ListPaymentsRequest):**
| Parametre | Type | Description |
|-----------|------|-------------|
| status | string | `pending`, `completed`, `failed`, `refunded` |
| method | string | `mtn_mobile_money`, `airtel_money` |
| user_id | integer | Filtrer par utilisateur |
| from | date | Date de debut |
| to | date | Date de fin |
| min_amount | number | Montant minimum |
| max_amount | number | Montant maximum |
| sort_by | string | `amount`, `status`, `payment_method`, `created_at` |
| sort_dir | string | `asc`, `desc` |
| per_page | integer | Nombre par page (1-100) |

### Rembourser un paiement

```http
POST /api/admin/payments/{payment}/refund
Authorization: Bearer <token>
```

**Corps de la requete:**
```json
{
    "reason": "Demande de l'utilisateur"
}
```

**Reponse (200):**
```json
{
    "message": "Paiement rembourse avec succes",
    "payment": {
        "id": 1,
        "status": "refunded",
        "refunded_at": "2024-01-20T10:00:00Z"
    }
}
```

> **Note:** Seuls les paiements avec le statut `completed` peuvent etre rembourses.

### Lister tous les abonnements

```http
GET /api/admin/subscriptions
Authorization: Bearer <token>
```

**Parametres de requete (ListSubscriptionsRequest):**
| Parametre | Type | Description |
|-----------|------|-------------|
| plan | string | `starter`, `pro` |
| status | string | `pending`, `paid`, `failed` |
| user_id | integer | Filtrer par utilisateur |
| event_id | integer | Filtrer par evenement |
| expired | boolean | Filtrer par expiration |
| sort_by | string | `plan_type`, `total_price`, `payment_status`, `created_at`, `expires_at` |
| sort_dir | string | `asc`, `desc` |
| per_page | integer | Nombre par page (1-100) |

### Annuler un abonnement

```http
POST /api/admin/subscriptions/{subscription}/cancel
Authorization: Bearer <token>
```

**Corps de la requete:**
```json
{
    "reason": "Demande de l'utilisateur"
}
```

**Reponse (200):**
```json
{
    "message": "Abonnement annule avec succes",
    "subscription": {
        "id": 1,
        "status": "cancelled",
        "cancelled_at": "2024-01-20T10:00:00Z"
    }
}
```

> **Note:** Seuls les abonnements actifs peuvent etre annules. L'utilisateur perdra l'acces aux fonctionnalites premium immediatement.

### Activer/Desactiver un utilisateur

```http
POST /api/admin/users/{user}/toggle-active
Authorization: Bearer <token>
```

**Reponse (200):**
```json
{
    "message": "Utilisateur desactive avec succes",
    "user": {
        "id": 1,
        "is_active": false
    }
}
```

> **Note:** Les utilisateurs desactives ne peuvent plus se connecter.

### Journal d'activite (systeme unifie)

Le systeme de logs d'activite trace toutes les actions de tous les utilisateurs (admin, users, systeme) avec stockage hybride SQL + S3.

#### Lister les logs d'activite (admin)

```http
GET /api/admin/activity-logs
Authorization: Bearer <token>
```

**Parametres de requete (ListActivityLogsRequest):**
| Parametre | Type | Description |
|-----------|------|-------------|
| user_id | integer | Filtrer par utilisateur |
| admin_id | integer | Filtrer par admin (retro-compatible) |
| actor_type | string | `admin`, `user`, `system`, `guest` |
| source | string | `api`, `navigation`, `ui_interaction`, `system` |
| action | string | Type d'action (voir liste ci-dessous) |
| model_type | string | `User`, `Event`, `Payment`, `Subscription`, `EventTemplate`, `Guest`, `Task`, `BudgetItem`, `Photo`, `Collaborator` |
| search | string | Recherche dans la description |
| session_id | string | Filtrer par session |
| from | date | Date de debut |
| to | date | Date de fin |
| sort_by | string | `created_at`, `action`, `model_type`, `actor_type`, `source` |
| sort_dir | string | `asc`, `desc` |
| per_page | integer | Nombre par page (1-100) |

**Actions disponibles:**
- CRUD : `create`, `update`, `delete`, `view`, `duplicate`
- Auth : `login`, `logout`
- Admin users : `update_role`, `toggle_active`
- Admin finance : `refund`, `extend`, `change_plan`, `cancel`
- Profil : `update_password`, `update_avatar`, `delete_avatar`
- Frontend : `page_view`, `click`, `modal_open`, `modal_close`, `filter_applied`, `tab_change`
- API middleware : `api_read`, `api_create`, `api_update`, `api_delete`

#### Statistiques des activites (admin)

```http
GET /api/admin/activity-logs/stats
Authorization: Bearer <token>
```

**Parametres de requete:**
| Parametre | Type | Description |
|-----------|------|-------------|
| actor_type | string | Filtrer les stats par type d'acteur |
| source | string | Filtrer les stats par source |

**Reponse:**
```json
{
    "stats": {
        "total": 1234,
        "today": 45,
        "this_week": 230,
        "this_month": 890,
        "by_action": { "login": 100, "create": 50, "page_view": 500 },
        "by_model_type": [{ "type": "Event", "count": 200 }],
        "by_actor_type": { "admin": 300, "user": 900, "system": 34 },
        "by_source": { "api": 400, "navigation": 600, "ui_interaction": 234 },
        "by_user": [{ "user_id": 1, "user_name": "Admin", "count": 300 }],
        "recent_users": [{ "user_id": 1, "name": "Admin", "last_activity": "2026-02-17T10:00:00Z" }]
    }
}
```

#### Detail d'un log (admin)

```http
GET /api/admin/activity-logs/{id}
Authorization: Bearer <token>
```

#### Lien temporaire S3 (admin)

```http
GET /api/admin/activity-logs/{id}/s3-url
Authorization: Bearer <token>
```

**Reponse:**
```json
{
    "url": "https://s3.amazonaws.com/...",
    "s3_key": "2026/02/17/uuid.json",
    "expires_at": "2026-02-17T10:30:00Z"
}
```

#### Export logs archives (admin)

```http
GET /api/admin/activity-logs/export
Authorization: Bearer <token>
```

**Parametres de requete:**
| Parametre | Type | Description |
|-----------|------|-------------|
| date_from | date | Date de debut (requis) |
| date_to | date | Date de fin (requis) |

#### Batch frontend (tous les utilisateurs authentifies)

```http
POST /api/activity-logs/batch
Authorization: Bearer <token>
```

**Corps de la requete:**
```json
{
    "events": [
        {
            "type": "navigation",
            "action": "page_view",
            "page_url": "/events",
            "session_id": "abc123",
            "timestamp": "2026-02-17T10:00:00Z",
            "metadata": { "previous_page": "/dashboard", "duration": 15 }
        },
        {
            "type": "ui_interaction",
            "action": "click",
            "page_url": "/events",
            "session_id": "abc123",
            "metadata": { "element": "btn-create-event" }
        }
    ]
}
```

**Reponse (201):**
```json
{
    "message": "2 evenements enregistres.",
    "count": 2
}
```

> **Note:** Les logs sont stockes en SQL (retention 30 jours) puis archives automatiquement vers S3. Le job `ArchiveAndPurgeLogsJob` s'execute quotidiennement a 02h00 UTC.

### Gestion des templates (Admin)

#### Lister les templates (admin)

```http
GET /api/admin/templates
Authorization: Bearer <token>
```

#### Creer un template

```http
POST /api/admin/templates
Authorization: Bearer <token>
```

**Corps de la requete (StoreTemplateRequest):**
```json
{
    "name": "Mariage Classique",
    "description": "Template pour un mariage traditionnel",
    "type": "mariage",
    "theme": "Romantique",
    "is_active": true,
    "is_featured": false,
    "tasks": [
        {
            "title": "Reserver le lieu",
            "description": "Visiter et reserver la salle de reception",
            "priority": "high",
            "days_before_event": 180
        }
    ],
    "budget_items": [
        {
            "category": "location",
            "name": "Salle de reception",
            "estimated_cost": 500000
        }
    ],
    "guest_categories": ["Famille", "Amis", "Collegues"],
    "colors": {
        "primary": "#FF6B6B",
        "secondary": "#4ECDC4",
        "accent": "#FFE66D"
    }
}
```

#### Modifier un template

```http
PUT /api/admin/templates/{template}
Authorization: Bearer <token>
```

#### Supprimer un template

```http
DELETE /api/admin/templates/{template}
Authorization: Bearer <token>
```

#### Activer/Desactiver un template

```http
POST /api/admin/templates/{template}/toggle-active
Authorization: Bearer <token>
```

---

## Policies et Autorisations

L'API utilise des policies Laravel pour gerer les autorisations. Voici les policies disponibles:

### EventPolicy
- `viewAny`: Tous les utilisateurs authentifies
- `view`: Proprietaire, collaborateur ou admin
- `create`: Tous les utilisateurs authentifies
- `update`: Proprietaire, editeur collaborateur ou admin
- `delete`: Proprietaire uniquement ou admin
- `collaborate`: Proprietaire ou admin
- `export`: Proprietaire, collaborateur ou admin

### PaymentPolicy
- `viewAny`: Tous les utilisateurs authentifies
- `view`: Proprietaire de l'abonnement ou admin
- `create`: Tous les utilisateurs authentifies
- `initiate`: Proprietaire de l'abonnement ou admin
- `checkStatus`: Proprietaire de l'abonnement ou admin
- `retry`: Proprietaire + paiement echoue ou admin
- `cancel`: Proprietaire + paiement en attente ou admin

### CollaboratorPolicy
- `viewAny`: Peut voir l'evenement
- `view`: Peut voir l'evenement
- `create`: Proprietaire de l'evenement ou collaborateur owner
- `update`: Proprietaire ou collaborateur owner (sauf autres owners)
- `delete`: Proprietaire ou collaborateur owner (sauf event owner)
- `accept/decline`: Utilisateur invite uniquement
- `leave`: Collaborateur (sauf proprietaire de l'evenement)

### AdminPolicy
- `access`: Admin uniquement
- `viewDashboard`: Admin uniquement
- `manageUsers`: Admin uniquement
- `updateUserRole`: Admin (sauf sur soi-meme)
- `deleteUser`: Admin (sauf sur soi-meme et autres admins)
- `viewAllEvents`: Admin uniquement
- `viewAllPayments`: Admin uniquement
- `viewAllSubscriptions`: Admin uniquement
- `viewActivityLogs`: Admin uniquement
- `manageTemplates`: Admin uniquement

---

## Configuration Frontend

### Variables d'environnement recommandees

```env
VITE_API_URL=http://localhost:8000
VITE_API_PREFIX=/api
```

### Exemple d'intercepteur Axios

```javascript
import axios from 'axios';

const api = axios.create({
    baseURL: import.meta.env.VITE_API_URL + '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Ajouter le token
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Gérer les erreurs 401
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;
```
