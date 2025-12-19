# Contexte pour le Dashboard Utilisateur - Party Planner

## Vue d'ensemble
Créer un dashboard moderne et intuitif pour les utilisateurs de l'application Party Planner (SaaS de planification d'événements). Le dashboard doit afficher un aperçu complet de l'activité de l'utilisateur, ses événements, tâches, collaborations et statistiques personnelles.

## Stack Technique
- **Framework**: Laravel 12 (Backend)
- **Frontend**: Blade Templates avec Tailwind CSS
- **Charts**: Chart.js
- **Icons**: SVG inline (Heroicons style)
- **Design System**: Design moderne avec gradients, cards premium, badges

## Structure des Données Disponibles

### Variables passées à la vue (via DashboardController)

#### Statistiques utilisateur (`$stats`)
```php
[
    'events_count' => int,              // Nombre total d'événements créés
    'active_events' => int,             // Événements en statut 'planning' ou 'confirmed'
    'completed_events' => int,          // Événements terminés
    'collaborations_count' => int,      // Nombre d'événements où l'utilisateur collabore
    'total_guests' => int,              // Total des invités sur tous les événements
    'total_tasks' => int,               // Total des tâches sur tous les événements
    'completed_tasks' => int,           // Tâches complétées
    'upcoming_events' => int,           // Événements à venir dans le mois
]
```

#### Autres variables
- `$events`: Collection des 5 prochains événements créés par l'utilisateur (avec `guests_count`, `tasks_count`)
- `$collaborations`: Collection des 5 prochains événements où l'utilisateur collabore
- `$upcomingEvents`: Nombre d'événements à venir (non annulés)
- `$pendingTasks`: Nombre de tâches assignées à l'utilisateur avec statut 'todo'
- `$pendingInvitations`: Nombre d'invitations de collaboration en attente

## Composants du Dashboard

### 1. Hero Banner (Section d'en-tête)
**Fonctionnalités:**
- Message de bienvenue personnalisé avec le prénom de l'utilisateur
- Date du jour formatée en français
- Badge animé avec indicateur de statut
- Message contextuel basé sur le nombre d'événements à venir
- Deux boutons d'action:
  - **Créer un événement** (bouton principal blanc avec texte orange ou fond orange avec texte blanc)
  - **Templates** (bouton secondaire avec fond transparent/blanc et bordure orange)

**Design:**
- Fond avec gradient orange (`#ff6b35` → `#f7931e`) et pattern
- Texte en blanc pour le contraste
- Éléments décoratifs (cercles flous orange/blanc en arrière-plan)
- Responsive (mobile et desktop)
- Utilisation exclusive de la palette orange/noir/blanc

### 2. Cartes de Statistiques (Stats Cards)
**4 cartes principales:**

#### a) Événements
- **Icône**: Calendrier
- **Valeur**: `$stats['events_count']`
- **Label**: "Événements"
- **Badge**: Affiche `$upcomingEvents` si > 0 ("X à venir")
- **Couleur**: Orange (`#ff6b35`)
- **Action**: Clic vers la liste des événements

#### b) Invités
- **Icône**: Groupe de personnes
- **Valeur**: `$stats['total_guests']`
- **Label**: "Invités total"
- **Couleur**: Noir (`#1f2937`) avec fond orange très clair au hover

#### c) Tâches
- **Icône**: Checklist
- **Valeur**: `$stats['total_tasks']`
- **Label**: "Tâches"
- **Badge**: Affiche `$pendingTasks` si > 0 ("X en cours")
- **Couleur**: Noir (`#111827`) avec accent orange
- **Action**: Clic vers les tâches

#### d) Collaborations
- **Icône**: Utilisateurs multiples
- **Valeur**: `$stats['collaborations_count']`
- **Label**: "Collaborations"
- **Badge**: Affiche `$pendingInvitations` si > 0 ("X invit.")
- **Couleur**: Orange (`#f7931e`) avec variation
- **Action**: Clic vers les collaborations

**Design des cartes:**
- Fond blanc avec ombre légère
- Icône dans un badge coloré (icon-box)
- Nombre en grand (text-3xl font-bold)
- Badge optionnel en haut à droite
- Effet hover avec flèche
- Grid responsive: 2 colonnes mobile, 4 colonnes desktop

### 3. Graphique d'Activité
**Fonctionnalités:**
- Graphique linéaire (Chart.js) intégré dans une carte premium
- Deux séries de données:
  - **Événements** (couleur orange: #ff6b35 → #f7931e en gradient)
  - **Tâches** (couleur orange foncé/noir: #1f2937 → #111827 en gradient)
- Période: 12 derniers mois
- Design avec gradient de remplissage sous les lignes
- Points interactifs avec hover
- Légende personnalisée avec indicateurs colorés (points ronds)
- Axes avec grille subtile
- Tooltips personnalisés avec fond sombre

**Configuration Chart.js détaillée:**
```javascript
{
  type: 'line',
  data: {
    labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
    datasets: [
      {
        label: 'Événements',
        data: [2, 3, 1, 4, 2, 5, 3, 4, 6, 4, 3, events_count],
        borderColor: '#ff6b35',
        backgroundColor: 'rgba(255, 107, 53, 0.15)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#ff6b35',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7
      },
      {
        label: 'Tâches',
        data: [5, 8, 6, 12, 9, 15, 11, 14, 18, 12, 10, total_tasks],
        borderColor: '#1f2937',
        backgroundColor: 'rgba(31, 41, 55, 0.1)',
        borderWidth: 3,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#1f2937',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(17, 24, 39, 0.95)',
        titleColor: '#ffffff',
        bodyColor: 'rgba(255,255,255,0.8)',
        padding: 14,
        cornerRadius: 12,
        displayColors: true
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        grid: {
          color: 'rgba(0, 0, 0, 0.04)',
          drawBorder: false
        },
        ticks: {
          color: '#6b7280',
          font: { size: 11 }
        }
      },
      x: {
        grid: { display: false },
        ticks: {
          color: '#6b7280',
          font: { size: 11 }
        }
      }
    }
  }
}
```

**Structure HTML du graphique:**
```html
<div class="premium-card p-6">
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
      <h2 class="text-lg font-bold text-gray-900">Aperçu de l'activité</h2>
      <p class="text-gray-500 text-sm">Évolution sur les 12 derniers mois</p>
    </div>
    <div class="flex items-center gap-6 text-sm">
      <span class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-orange-500"></span>
        Événements
      </span>
      <span class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-gray-800"></span>
        Tâches
      </span>
    </div>
  </div>
  <div class="h-[280px]">
    <canvas id="activityChart"></canvas>
  </div>
</div>
```

**Données:**
- Labels: Mois en français (Jan, Fév, Mar, Avr, Mai, Jun, Jul, Aoû, Sep, Oct, Nov, Déc)
- **IMPORTANT**: Actuellement, les données historiques (11 premiers mois) sont mockées/hardcodées dans la vue
- Seule la valeur du mois actuel (décembre) utilise les vraies données depuis `$stats['events_count']` et `$stats['total_tasks']`
- Pour une implémentation complète, il faudrait créer un endpoint API ou passer les données historiques depuis le backend
- Format des données mockées (exemple):
  - Événements: `[2, 3, 1, 4, 2, 5, 3, 4, 6, 4, 3, {{ $stats['events_count'] ?? 0 }}]`
  - Tâches: `[5, 8, 6, 12, 9, 15, 11, 14, 18, 12, 10, {{ $stats['total_tasks'] ?? 0 }}]`

### 4. Liste des Événements à Venir
**Fonctionnalités:**
- Affiche les 5 prochains événements créés par l'utilisateur
- Chaque événement affiche:
  - **Badge de date**: Mois et jour dans un badge stylisé
  - **Titre**: Nom de l'événement
  - **Statut**: Badge coloré selon le statut (utiliser la palette orange/noir/blanc):
    - `draft` → gris/noir (`badge-gray`)
    - `planning` → orange clair (`badge-warning` avec orange)
    - `confirmed` → orange (`badge-success` avec orange)
    - `in_progress` → orange foncé (`badge-info` avec orange)
    - `completed` → noir (`badge-purple` remplacé par noir)
    - `cancelled` → gris foncé (`badge-danger` avec gris/noir)
  - **Localisation**: Icône + texte (limité à 20 caractères)
  - **Nombre d'invités**: Avec icône
  - **Nombre de tâches**: Avec icône
  - **Compte à rebours**: "Dans X jours" ou "Aujourd'hui !" si c'est aujourd'hui
- Lien vers chaque événement
- État vide avec message et bouton "Créer un événement"

**Design:**
- Cards avec effet hover
- Layout flex avec espacement
- Responsive

### 5. Progression des Tâches (Sidebar)
**Fonctionnalités:**
- Graphique circulaire (progress ring)
- Pourcentage de complétion: `(completed_tasks / total_tasks) * 100`
- Deux mini-cartes:
  - **Terminées**: `$stats['completed_tasks']` (fond vert clair)
  - **En attente**: `$pendingTasks` (fond jaune clair)

**Design:**
- Cercle SVG avec gradient orange (`#ff6b35` → `#f7931e`)
- Pourcentage au centre en noir (`#111827`)
- Design moderne avec ombres
- Fond blanc pour le cercle
- Mini-cartes avec fond orange très clair (`#fff7ed`) et noir très clair (`#f9fafb`)

### 6. Tâches Urgentes (Sidebar)
**Fonctionnalités:**
- Liste des 5 tâches les plus urgentes assignées à l'utilisateur
- Pour chaque tâche:
  - **Indicateur de priorité**: Point coloré (utiliser orange/noir):
    - `high` ou `urgent` → orange (`#ff6b35`) avec ring orange
    - `medium` → orange clair (`#f7931e`) avec ring orange clair
    - `low` → gris/noir (`#6b7280` ou `#1f2937`)
  - **Titre**: Nom de la tâche
  - **Événement**: Titre de l'événement associé
  - **Date d'échéance**: Formatée en français
  - **Badge "En retard !"**: Si la tâche est en retard (rouge)
- État vide avec message positif si aucune tâche

**Design:**
- Cards avec fond conditionnel:
  - Normal: Fond blanc avec bordure grise légère
  - En retard: Fond orange très clair (`#fff7ed`) avec bordure orange
- Layout compact
- Icônes d'alerte pour les retards (couleur orange `#ff6b35`)
- Indicateurs de priorité: Orange pour urgent/high, gris pour medium/low

### 7. Actions Rapides (Sidebar)
**Fonctionnalités:**
- 4 actions principales:
  1. **Nouvel événement** (orange - `#ff6b35`)
  2. **Templates** (orange foncé - `#f7931e`)
  3. **Collaborations** (noir - `#1f2937`)
  4. **Paramètres** (gris foncé - `#111827`)
- Chaque action a:
  - Icône dans un badge coloré (orange ou noir selon l'action)
  - Titre et description
  - Effet hover avec changement de couleur (fond orange très clair ou gris très clair)
  - Flèche de navigation

**Design:**
- Liste verticale avec espacement
- Effets hover subtils avec transition
- Icônes cohérentes avec le reste de l'interface
- Utilisation de la palette orange/noir/blanc uniquement

## États et Conditions

### États vides
- **Aucun événement**: Message encourageant + bouton "Créer un événement"
- **Aucune tâche urgente**: Message positif "Vous êtes à jour ! 🎉"

### Badges conditionnels
- Afficher uniquement si la valeur > 0
- Couleurs contextuelles (success, warning, info, danger)

### Responsive
- Mobile: 1 colonne, cartes empilées
- Tablet: 2 colonnes pour les stats
- Desktop: 4 colonnes pour les stats, sidebar fixe

## Palette de Couleurs

### Couleurs principales (Orange, Noir, Blanc)
- **Orange Primary**: `#ff6b35` (orange vif)
- **Orange Secondary**: `#f7931e` (orange doré)
- **Orange Gradient**: De `#ff6b35` à `#f7931e`
- **Noir Primary**: `#000000` (noir pur)
- **Noir Secondary**: `#1f2937` (gris très foncé)
- **Noir Tertiary**: `#111827` (gris foncé)
- **Blanc**: `#ffffff` (blanc pur)
- **Gris clair**: `#f9fafb`, `#f3f4f6` (pour les fonds)
- **Gris moyen**: `#6b7280`, `#9ca3af` (pour les textes secondaires)

### Utilisation des couleurs
- **Orange**: 
  - Boutons principaux, accents, icônes importantes
  - Graphique événements, badges de statut actifs
  - Hover states, liens actifs
- **Noir**:
  - Textes principaux (`#000000` ou `#111827`)
  - Graphique tâches, bordures subtiles
  - Icônes et éléments de navigation
- **Blanc**:
  - Fond des cartes et composants
  - Textes sur fonds colorés
  - Bordures et séparateurs légers

### Couleurs de fond
- **Cards**: Blanc (`#ffffff`) avec ombre légère
- **Hover states**: Orange très clair (`#fff7ed`, `#ffedd5`) ou gris très clair (`#f9fafb`)
- **Gradients**: 
  - Hero banner: Orange gradient (`#ff6b35` → `#f7931e`)
  - Progress rings: Orange gradient
  - Graphiques: Dégradés orange et noir avec transparence

### Couleurs d'accent (optionnelles, à utiliser avec parcimonie)
- **Success/Green**: `#10b981` (pour les statuts confirmés)
- **Warning/Yellow**: `#f59e0b` (pour les alertes)
- **Danger/Red**: `#ef4444` (pour les erreurs/retards)
- **Info/Blue**: `#3b82f6` (pour les informations)

## Classes CSS Personnalisées (à utiliser)

### Cards
- `stat-card`: Carte de statistique de base
- `stat-primary`, `stat-blue`, `stat-purple`, `stat-green`: Variantes colorées
- `premium-card`: Carte avec ombre et style premium
- `event-card`: Carte d'événement avec hover

### Badges
- `badge`: Badge de base
- `badge-success`, `badge-warning`, `badge-info`, `badge-danger`, `badge-gray`: Variantes
- `badge-dot`: Badge avec point indicateur

### Icônes
- `icon-box`: Container d'icône
- `icon-box-md`, `icon-box-sm`: Tailles
- `icon-box-primary`, `icon-box-blue`, etc.: Variantes colorées
- `icon-box-soft-*`: Variantes avec fond clair

### Autres
- `btn`, `btn-lg`, `btn-md`, `btn-sm`: Boutons
- `btn-primary`, `btn-white`, `btn-ghost`: Variantes
- `empty-state`: État vide
- `date-badge`: Badge de date stylisé
- `circular-progress`: Progress ring circulaire

## Structure HTML Recommandée

```html
<div class="space-y-8">
  <!-- Hero Banner -->
  <div class="hero-gradient hero-pattern ...">
    <!-- Contenu hero -->
  </div>

  <!-- Stats Cards Grid -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
    <!-- 4 cartes de stats -->
  </div>

  <!-- Main Grid -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <!-- Left: Chart + Events (xl:col-span-2) -->
    <div class="xl:col-span-2 space-y-6">
      <!-- Activity Chart -->
      <!-- Upcoming Events -->
    </div>

    <!-- Right Sidebar -->
    <div class="space-y-6">
      <!-- Task Progress -->
      <!-- Urgent Tasks -->
      <!-- Quick Actions -->
    </div>
  </div>
</div>
```

## Notes Importantes

1. **Formatage des dates**: Utiliser `translatedFormat()` pour les dates en français
2. **Formatage des nombres**: Afficher les nombres sans décimales sauf pourcentages
3. **Accessibilité**: Inclure des labels ARIA et des textes alternatifs
4. **Performance**: Le graphique Chart.js doit être initialisé dans un script séparé
5. **Responsive**: Tester sur mobile, tablette et desktop
6. **États de chargement**: Considérer les états de chargement si nécessaire
7. **Animations**: Utiliser des transitions subtiles pour les hovers
8. **Données du graphique**: Les données historiques sont actuellement mockées - à remplacer par des vraies données si disponible

## Validation avec le Code Existant

### ✅ Variables Disponibles (DashboardController)
- `$events`: Collection des 5 prochains événements (avec `guests_count`, `tasks_count`)
- `$collaborations`: Collection des 5 prochains événements où l'utilisateur collabore
- `$stats`: Tableau avec toutes les statistiques utilisateur (voir structure ci-dessus)
- `$upcomingEvents`: Nombre d'événements à venir
- `$pendingTasks`: Nombre de tâches en attente
- `$pendingInvitations`: Nombre d'invitations en attente

### ✅ Statuts d'Événements (confirmés dans le code)
- `draft` - Brouillon
- `planning` - En planification
- `confirmed` - Confirmé
- `in_progress` - En cours
- `completed` - Terminé
- `cancelled` - Annulé

### ✅ Statuts de Tâches (confirmés dans le code)
- `todo` - À faire
- `in_progress` - En cours
- `completed` - Terminé
- `cancelled` - Annulé

### ✅ Priorités de Tâches (confirmées dans le code)
- `low` - Basse
- `medium` - Moyenne
- `high` - Haute
- `urgent` - Urgente

### ✅ Relations Utilisateur (confirmées)
- `$user->events()` - Événements créés par l'utilisateur
- `$user->collaboratingEvents()` - Événements où l'utilisateur collabore (alias de `collaboratedEvents()`)
- `$user->assignedTasks()` - Tâches assignées à l'utilisateur
- `$user->pendingCollaborations()` - Collaborations en attente (où `accepted_at` est null)

### ✅ Champs des Modèles
**Event:**
- `title`, `type`, `description`, `date`, `time`, `location`
- `estimated_budget`, `actual_budget`, `theme`, `expected_guests_count`
- `status`, `user_id`

**Task:**
- `title`, `description`, `status`, `priority`, `due_date`, `completed_at`
- `event_id`, `assigned_to_user_id`

**Collaborator:**
- `event_id`, `user_id`, `role`, `invited_at`, `accepted_at`

## Exemple de Données Mock (pour v0.dev)

```javascript
const stats = {
  events_count: 12,
  active_events: 5,
  completed_events: 7,
  collaborations_count: 3,
  total_guests: 145,
  total_tasks: 48,
  completed_tasks: 32,
  upcoming_events: 2
};

const upcomingEvents = 2;
const pendingTasks = 8;
const pendingInvitations = 1;

const events = [
  {
    id: 1,
    title: "Anniversaire de mariage",
    date: "2025-12-25",
    location: "Restaurant Le Jardin",
    status: "confirmed",
    guests_count: 50,
    tasks_count: 12
  },
  // ... autres événements
];
```

## Instructions pour v0.dev

1. Créer un dashboard moderne avec Tailwind CSS
2. Implémenter tous les composants listés ci-dessus
3. Utiliser des composants réutilisables (cards, badges, etc.)
4. Assurer la responsivité mobile-first
5. Ajouter des animations et transitions subtiles
6. Utiliser Chart.js pour le graphique d'activité avec configuration détaillée fournie
7. Respecter strictement la palette de couleurs orange/noir/blanc comme couleurs principales
8. Le graphique doit utiliser orange pour les événements et noir pour les tâches
8. Inclure les états vides et les conditions d'affichage
9. Optimiser pour l'accessibilité
10. Code propre et bien structuré

