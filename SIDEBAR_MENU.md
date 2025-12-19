# Structure du Menu Sidebar - Party Planner

Ce document liste tous les liens de navigation disponibles dans le sidebar de l'application, organisés par sections.

## 📋 Menu Principal (Toujours visible)

| Route | Icône | Label | Badge |
|-------|-------|-------|-------|
| `dashboard` | `home` | Dashboard | - |
| `events.index` | `calendar` | Événements | Nombre d'événements |
| `collaborations.index` | `users` | Collaborations | Invitations en attente |
| `templates.index` | `template` | Templates | - |
| `subscriptions.index` | `credit-card` | Abonnements | - |
| `payments.index` | `credit-card` | Paiements | - |

---

## 🎯 Menu Contextuel - Vue d'ensemble de l'événement

*Visible uniquement quand on est sur une page d'événement spécifique*

| Route | Icône | Label | Paramètres |
|-------|-------|-------|------------|
| `events.dashboard` | `dashboard` | Vue d'ensemble | `{event}` |
| `events.show` | `eye` | Détails | `{event}` |
| `events.edit` | `edit` | Modifier | `{event}` |

---

## 👥 Section Invités

*Sous-section d'un événement*

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.guests.index` | `users` | Liste des invités | `{event}` | GET |
| `events.guests.create` | `user-plus` | Ajouter un invité | `{event}` | GET |
| `events.guests.store` | - | Créer invité | `{event}` | POST |
| `events.guests.edit` | `edit` | Modifier invité | `{event}`, `{guest}` | GET |
| `events.guests.update` | - | Mettre à jour | `{event}`, `{guest}` | PUT |
| `events.guests.destroy` | - | Supprimer | `{event}`, `{guest}` | DELETE |
| `events.guests.import.form` | `upload` | Importer (CSV) | `{event}` | GET |
| `events.guests.import` | - | Traiter import | `{event}` | POST |
| `events.guests.send-invitation` | - | Envoyer invitation | `{event}`, `{guest}` | POST |
| `events.guests.send-all-invitations` | - | Envoyer toutes | `{event}` | POST |
| `events.guests.check-in` | - | Check-in | `{event}`, `{guest}` | POST |

---

## ✅ Section Tâches

*Sous-section d'un événement*

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.tasks.index` | `checklist` | Liste des tâches | `{event}` | GET |
| `events.tasks.store` | `plus` | Nouvelle tâche | `{event}` | POST |
| `events.tasks.update` | - | Mettre à jour | `{event}`, `{task}` | PUT |
| `events.tasks.destroy` | - | Supprimer | `{event}`, `{task}` | DELETE |
| `events.tasks.assign` | - | Assigner | `{event}`, `{task}` | POST |
| `events.tasks.complete` | - | Compléter | `{event}`, `{task}` | POST |
| `events.tasks.reopen` | - | Rouvrir | `{event}`, `{task}` | POST |

---

## 💰 Section Budget

*Sous-section d'un événement*

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.budget.index` | `money` | Vue budget | `{event}` | GET |
| `events.budget.create` | `plus` | Ajouter élément | `{event}` | GET |
| `events.budget.store` | - | Créer élément | `{event}` | POST |
| `events.budget.edit` | `edit` | Modifier élément | `{event}`, `{item}` | GET |
| `events.budget.update` | - | Mettre à jour | `{event}`, `{item}` | PUT |
| `events.budget.destroy` | - | Supprimer | `{event}`, `{item}` | DELETE |
| `events.budget.export-pdf` | `download` | Exporter PDF | `{event}` | GET |

---

## 📸 Section Galerie

*Sous-section d'un événement*

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.gallery.index` | `photo` | Galerie photos | `{event}` | GET |
| `events.gallery.create` | `upload` | Ajouter photos | `{event}` | GET |
| `events.photos.store` | - | Upload photos | `{event}` | POST |
| `events.photos.destroy` | - | Supprimer photo | `{event}`, `{photo}` | DELETE |
| `events.photos.set-featured` | - | Mettre en avant | `{event}`, `{photo}` | POST |

---

## 🤝 Section Collaborateurs

*Sous-section d'un événement*

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.collaborators.index` | `users` | Liste collaborateurs | `{event}` | GET |
| `events.collaborators.store` | `user-plus` | Inviter collaborateur | `{event}` | POST |
| `events.collaborators.update` | - | Modifier rôle | `{event}`, `{user}` | PUT |
| `events.collaborators.destroy` | - | Retirer | `{event}`, `{user}` | DELETE |

---

## 💳 Section Paiements & Abonnements

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `events.subscription.show` | `credit-card` | Choix plan | `{event}` | GET |
| `events.subscription.subscribe` | - | Souscrire | `{event}` | POST |
| `subscriptions.index` | `credit-card` | Liste abonnements | - | GET |
| `payments.index` | `credit-card` | Historique paiements | - | GET |
| `payments.mtn.initiate` | - | Paiement MTN | - | POST |
| `payments.airtel.initiate` | - | Paiement Airtel | - | POST |
| `payments.status` | - | Statut paiement | `{payment}` | GET |

---

## 🔔 Section Notifications

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `notifications.index` | `bell` | Liste notifications | - | GET |
| `notifications.read` | - | Marquer comme lu | `{notification}` | PUT |
| `notifications.read-all` | - | Tout marquer comme lu | - | PUT |
| `notifications.settings` | `cog` | Paramètres notifications | - | GET |
| `notifications.settings.update` | - | Mettre à jour paramètres | - | PUT |

---

## 👤 Section Profil

| Route | Icône | Label | Paramètres | Actions |
|-------|-------|-------|------------|---------|
| `profile.edit` | `cog` | Profil utilisateur | - | GET |
| `profile.update` | - | Mettre à jour profil | - | PUT |
| `profile.password` | `cog` | Changer mot de passe | - | GET |
| `profile.password.update` | - | Mettre à jour mot de passe | - | PUT |

---

## 📝 Notes d'implémentation

### Badges dynamiques
Les badges peuvent être calculés dynamiquement :
- **Événements** : Nombre d'événements actifs de l'utilisateur
- **Collaborations** : Nombre d'invitations en attente
- **Notifications** : Nombre de notifications non lues
- **Invités** : Nombre total d'invités pour l'événement
- **Tâches** : Nombre de tâches en attente
- **Photos** : Nombre de photos dans la galerie

### Visibilité conditionnelle
- Le menu contextuel (vue d'ensemble, détails, modifier) n'est visible que sur les pages d'événement
- Les sous-sections (invités, tâches, budget, galerie, collaborateurs) sont visibles uniquement dans le contexte d'un événement spécifique
- Les routes POST/DELETE/PUT ne sont généralement pas affichées dans le menu, mais accessibles via des boutons d'action dans les pages

### Icônes disponibles
- `home` - Dashboard
- `calendar` - Événements
- `users` - Collaborations/Invités/Collaborateurs
- `template` - Templates
- `credit-card` - Paiements/Abonnements
- `bell` - Notifications
- `cog` - Paramètres/Profil
- `dashboard` - Vue d'ensemble
- `eye` - Détails
- `edit` - Modifier
- `user-plus` - Ajouter invité/Collaborateur
- `upload` - Importer/Upload
- `checklist` - Tâches
- `plus` - Ajouter
- `money` - Budget
- `download` - Exporter
- `photo` - Galerie

---

## 🎨 Structure recommandée du sidebar

```
┌─────────────────────────────┐
│ Logo + Nom                  │
├─────────────────────────────┤
│ Menu Principal              │
│ ├─ Dashboard                │
│ ├─ Événements               │
│ ├─ Collaborations           │
│ ├─ Templates                │
│ ├─ Abonnements              │
│ └─ Paiements                │
├─────────────────────────────┤
│ [Si sur page événement]     │
│ Menu Contextuel             │
│ ├─ Vue d'ensemble          │
│ ├─ Détails                 │
│ └─ Modifier                │
│                             │
│ Invités                     │
│ ├─ Liste                   │
│ ├─ Ajouter                 │
│ └─ Importer                │
│                             │
│ Tâches                      │
│ ├─ Liste                   │
│ └─ Nouvelle                │
│                             │
│ Budget                      │
│ ├─ Vue budget              │
│ ├─ Ajouter élément         │
│ └─ Exporter PDF            │
│                             │
│ Galerie                     │
│ ├─ Galerie photos          │
│ └─ Ajouter photos          │
│                             │
│ Collaborateurs              │
│ ├─ Liste                   │
│ └─ Inviter                 │
├─────────────────────────────┤
│ Menu Secondaire             │
│ ├─ Notifications           │
│ └─ Profil                  │
├─────────────────────────────┤
│ Carte Utilisateur           │
└─────────────────────────────┘
```

