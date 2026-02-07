# Intégration Tâches → Dépenses (Budget)

## 📋 Vue d'ensemble

Cette fonctionnalité permet de créer automatiquement une ligne de dépense (BudgetItem) lorsqu'une tâche a un coût associé.

**Exemple** : Si vous créez une tâche "Achat du gâteau" avec un coût de 50000 FCFA, une dépense correspondante sera automatiquement créée dans le budget de l'événement.

## 🗂️ Modifications apportées

### 1. Migrations

#### `2025_02_02_000001_add_cost_fields_to_tasks_table.php`
- Ajoute `estimated_cost` (decimal 12,2) dans la table `tasks`
- Ajoute `budget_category` (string) dans la table `tasks`

#### `2025_02_02_000002_add_task_id_to_budget_items_table.php`
- Ajoute `task_id` (foreign key) dans la table `budget_items`
- Permet de lier une dépense à sa tâche source

### 2. Modèles

#### `Task` (app/Models/Task.php)
- ✅ Ajout de `estimated_cost` et `budget_category` dans `$fillable`
- ✅ Cast de `estimated_cost` en `decimal:2`
- ✅ Nouvelle relation `budgetItem()` : `HasOne`
- ✅ Méthode `hasCost()` : vérifie si la tâche a un coût

#### `BudgetItem` (app/Models/BudgetItem.php)
- ✅ Ajout de `task_id` dans `$fillable`
- ✅ Nouvelle relation `task()` : `BelongsTo`
- ✅ Méthode `isLinkedToTask()` : vérifie si la dépense est liée à une tâche

### 3. Service

#### `TaskBudgetService` (app/Services/TaskBudgetService.php)
Service dédié à la synchronisation entre tâches et dépenses :

- **`syncBudgetItemFromTask()`** : Crée ou met à jour une dépense à partir d'une tâche
- **`updateBudgetItemFromTask()`** : Met à jour une dépense existante lors de la modification d'une tâche
- **`removeBudgetItemFromTask()`** : Supprime la dépense associée si la tâche n'a plus de coût
- **`shouldCreateBudgetItem()`** : Vérifie si une dépense doit être créée

### 4. Contrôleur

#### `TaskController` (app/Http/Controllers/Api/TaskController.php)
- ✅ Injection de `TaskBudgetService`
- ✅ Validation de `estimated_cost` et `budget_category` dans `store()` et `update()`
- ✅ Synchronisation automatique après création/mise à jour/suppression
- ✅ Chargement de la relation `budgetItem` dans les réponses

## 🔄 Flux de fonctionnement

### Création d'une tâche avec coût

```
1. POST /api/events/{event}/tasks
   {
     "title": "Achat du gâteau",
     "estimated_cost": 50000,
     "budget_category": "catering"
   }

2. Task créée → TaskBudgetService.syncBudgetItemFromTask()
   
3. BudgetItem créé automatiquement :
   - name = "Achat du gâteau" (depuis task.title)
   - estimated_cost = 50000 (depuis task.estimated_cost)
   - category = "catering" (depuis task.budget_category)
   - task_id = [id de la tâche]
   - event_id = [id de l'événement]
```

### Mise à jour d'une tâche

```
1. PUT /api/events/{event}/tasks/{task}
   {
     "estimated_cost": 60000  // Coût modifié
   }

2. Task mise à jour → TaskBudgetService.updateBudgetItemFromTask()
   
3. BudgetItem mis à jour automatiquement :
   - estimated_cost = 60000
   - name = [titre de la tâche]
```

### Suppression d'une tâche

```
1. DELETE /api/events/{event}/tasks/{task}

2. TaskBudgetService.removeBudgetItemFromTask()
   
3. BudgetItem associé supprimé automatiquement
```

### Retrait du coût d'une tâche

```
1. PUT /api/events/{event}/tasks/{task}
   {
     "estimated_cost": null  // Coût retiré
   }

2. TaskBudgetService détecte que hasCost() = false
   
3. BudgetItem associé supprimé automatiquement
```

## 📊 Structure des données

### Task
```php
[
  "id": 1,
  "title": "Achat du gâteau",
  "estimated_cost": 50000.00,
  "budget_category": "catering",
  "budget_item": {  // Relation chargée
    "id": 10,
    "name": "Achat du gâteau",
    "estimated_cost": 50000.00,
    "category": "catering",
    "task_id": 1
  }
]
```

### BudgetItem
```php
[
  "id": 10,
  "event_id": 5,
  "task_id": 1,  // Lien vers la tâche
  "name": "Achat du gâteau",
  "estimated_cost": 50000.00,
  "category": "catering",
  "task": {  // Relation chargée (optionnel)
    "id": 1,
    "title": "Achat du gâteau"
  }
]
```

## ✅ Avantages de cette approche

1. **Synchronisation automatique** : Pas besoin de créer manuellement la dépense
2. **Cohérence** : Le titre et le coût restent synchronisés entre tâche et dépense
3. **Flexibilité** : 
   - La dépense peut être modifiée indépendamment (actual_cost, paid, etc.)
   - Si on retire le coût de la tâche, la dépense est supprimée
4. **Traçabilité** : On sait quelle dépense vient de quelle tâche via `task_id`
5. **Rétrocompatibilité** : Les tâches sans coût fonctionnent comme avant

## 🔒 Permissions

Les permissions existantes sont respectées :
- Pour créer une tâche avec coût → `tasks.create`
- Pour modifier une tâche avec coût → `tasks.edit`
- La création automatique de la dépense utilise les mêmes permissions que la tâche

## 🎯 Cas d'usage

### Exemple 1 : Tâche avec coût initial
```json
POST /api/events/1/tasks
{
  "title": "Location salle",
  "estimated_cost": 200000,
  "budget_category": "location",
  "priority": "high"
}
```
→ Crée automatiquement une dépense "Location salle" de 200000 FCFA

### Exemple 2 : Ajout de coût à une tâche existante
```json
PUT /api/events/1/tasks/5
{
  "estimated_cost": 15000,
  "budget_category": "decoration"
}
```
→ Crée automatiquement une dépense si elle n'existe pas, ou met à jour celle existante

### Exemple 3 : Modification du coût
```json
PUT /api/events/1/tasks/5
{
  "estimated_cost": 18000
}
```
→ Met à jour le `estimated_cost` de la dépense associée

### Exemple 4 : Retrait du coût
```json
PUT /api/events/1/tasks/5
{
  "estimated_cost": null
}
```
→ Supprime la dépense associée

## 🚀 Prochaines étapes possibles

1. **Synchronisation inverse** : Mettre à jour le coût de la tâche si on modifie la dépense
2. **Notification** : Alerter quand une tâche avec coût est complétée
3. **Rapport** : Vue consolidée tâches + dépenses
4. **Template** : Créer des tâches avec coûts depuis un template

## 📝 Notes importantes

- Le `actual_cost` et le statut `paid` de la dépense ne sont **pas** modifiés automatiquement
- Si une dépense existe déjà pour une tâche, elle est mise à jour (pas de doublon)
- La description de la tâche peut être utilisée comme notes de la dépense
- La catégorie par défaut est `'other'` si non spécifiée
