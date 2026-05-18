# Party Planner

Application SaaS de gestion d'evenements permettant aux utilisateurs de planifier et organiser leurs evenements, gerer les invites, les taches, le budget et les photos.

## Fonctionnalites

- **Gestion d'evenements**: Creation, modification, duplication d'evenements
- **Gestion des invites**: Import CSV/Excel, envoi d'invitations par email/SMS/WhatsApp, suivi RSVP
- **Gestion des taches**: Creation, assignation, priorites, dates d'echeance
- **Gestion du budget**: Categories, suivi des depenses, alertes de depassement
- **Galerie photos**: Upload, organisation, partage
- **Collaboration**: Inviter des collaborateurs avec differents roles
- **Templates**: Templates d'evenements predefinies
- **Notifications**: Email, SMS, WhatsApp, Push (Firebase)
- **Export**: CSV, Excel, PDF

## Prerequis

- PHP 8.2+
- Composer
- PostgreSQL 14+ (ou MySQL 8+)
- Node.js 18+ et npm
- Redis (optionnel, pour les queues)

## Installation

### 1. Cloner le projet

```bash
git clone <repository-url>
cd party-planner
```

### 2. Installer les dependances PHP

```bash
composer install
```

### 3. Installer les dependances JavaScript

```bash
npm install
npm run build
```

### 4. Configuration de l'environnement

```bash
cp .env.example .env
php artisan key:generate
```

### 5. Configurer la base de donnees

Modifier les variables `DB_*` dans le fichier `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=party-planner
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### 6. Executer les migrations

```bash
php artisan migrate
php artisan db:seed
```

### Démo organisateurs (présentation commerciale)

Après le seed de base, chargez un portfolio d'événements pour une agence (gala corporate, mariage client, événement passé) :

```bash
php artisan db:seed --class=OrganizerDemoSeeder
```

Si les onglets Invités / Tâches / Budget / Collaborateurs n'apparaissent pas après un seed déjà exécuté :

```bash
php artisan demo:fix-organizer-entitlements
```

Compte principal : `alexsonicka11+1@gmail.com` / `Test@1234` (alias Gmail — les OTP arrivent sur la même boîte que l'admin). Collaborateurs : `+2`, `+3`, `+4` sur le même domaine.

### 7. Lancer le serveur

```bash
php artisan serve
npm run dev
```

### Limites d'upload PHP (photos)

L'application autorise des photos jusqu'à **5 Mo**. Par défaut, PHP limite souvent les uploads à **2 Mo**. Si vous avez l'erreur « Le fichier n'a pas pu être envoyé » avec une image &lt; 5 Mo, augmentez les limites PHP :

1. **Trouver le fichier php.ini utilisé** :
   ```bash
   php --ini
   ```
   Notez le chemin « Loaded Configuration File ».

2. **Éditer php.ini** et définir (ou modifier) :
   ```ini
   upload_max_filesize = 10M
   post_max_size = 20M
   ```
   `post_max_size` doit être supérieur à `upload_max_filesize` (surtout si vous uploadez plusieurs photos).

3. **Redémarrer le serveur** : arrêtez `php artisan serve` (Ctrl+C) puis relancez-le.

Sous **XAMPP / WAMP / Laragon** : éditez le php.ini indiqué par `php --ini`, puis redémarrez Apache ou le serveur intégré.

## Configuration des services externes

### Stripe (Paiements par carte)

1. Creer un compte sur [Stripe Dashboard](https://dashboard.stripe.com)
2. Recuperer les cles API (test ou production)
3. Configurer les webhooks:
   - URL: `https://votre-domaine.com/webhooks/stripe`
   - Evenements: `checkout.session.completed`, `payment_intent.succeeded`, `customer.subscription.*`
4. Ajouter dans `.env`:

```env
STRIPE_KEY=pk_test_xxx
STRIPE_SECRET=sk_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

### MTN Mobile Money (Cameroun)

1. S'inscrire sur [MTN MoMo Developer Portal](https://momodeveloper.mtn.com)
2. Creer une application et obtenir les cles API
3. Configurer le callback URL: `https://votre-domaine.com/webhooks/mtn`
4. Ajouter dans `.env`:

```env
MTN_ENABLED=true
MTN_API_URL=https://sandbox.momodeveloper.mtn.com
MTN_API_KEY=your_api_key
MTN_API_SECRET=your_api_secret
MTN_SUBSCRIPTION_KEY=your_subscription_key
MTN_ENVIRONMENT=sandbox  # ou 'production'
```

### Airtel Money (Afrique)

1. S'inscrire sur [Airtel Africa Developer Portal](https://developers.airtel.africa)
2. Creer une application et obtenir les credentials
3. Configurer le callback URL: `https://votre-domaine.com/webhooks/airtel`
4. Ajouter dans `.env`:

```env
AIRTEL_ENABLED=true
AIRTEL_API_URL=https://openapi.airtel.africa
AIRTEL_CLIENT_ID=your_client_id
AIRTEL_CLIENT_SECRET=your_client_secret
AIRTEL_ENVIRONMENT=sandbox  # ou 'production'
```

### Firebase Cloud Messaging (Notifications Push)

1. Creer un projet sur [Firebase Console](https://console.firebase.google.com)
2. Aller dans Project Settings > Service Accounts
3. Generer une nouvelle cle privee (fichier JSON)
4. Placer le fichier dans `storage/app/firebase-credentials.json`
5. Ajouter dans `.env`:

```env
FIREBASE_CREDENTIALS=storage/app/firebase-credentials.json
```

#### Configuration du client mobile/web

Pour recevoir les notifications push, votre application mobile ou web doit:

1. Integrer le SDK Firebase
2. Demander la permission de notification
3. Enregistrer le device token via l'API: `POST /api/user/device-tokens`

### Twilio (SMS et WhatsApp)

1. Creer un compte sur [Twilio Console](https://console.twilio.com)
2. Obtenir votre Account SID et Auth Token
3. Acheter un numero de telephone Twilio
4. Pour WhatsApp: Activer la sandbox WhatsApp ou demander un numero WhatsApp Business
5. Configurer les webhooks:
   - SMS Status: `https://votre-domaine.com/webhooks/twilio/sms/status`
   - SMS Incoming: `https://votre-domaine.com/webhooks/twilio/sms/incoming`
   - WhatsApp Status: `https://votre-domaine.com/webhooks/twilio/whatsapp/status`
   - WhatsApp Incoming: `https://votre-domaine.com/webhooks/twilio/whatsapp/incoming`
6. Ajouter dans `.env`:

```env
TWILIO_SID=ACxxxxxxxxxx
TWILIO_TOKEN=your_auth_token
TWILIO_FROM=+242xxxxxxxxx
TWILIO_WHATSAPP_FROM=+14155238886  # Numero WhatsApp sandbox ou Business
```

### AWS S3 (Stockage de fichiers)

1. Creer un compte AWS et un bucket S3
2. Creer un utilisateur IAM avec les permissions S3
3. Configurer CORS sur le bucket si necessaire
4. Ajouter dans `.env`:

```env
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=your-bucket-name
FILESYSTEM_DISK=s3  # Pour utiliser S3 par defaut
```

#### Permissions IAM recommandees

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

## Queues et Jobs

L'application utilise des jobs en arriere-plan pour:
- Envoi d'emails
- Envoi de SMS/WhatsApp
- Notifications push
- Traitement des callbacks de paiement
- Generation de rapports

### Configuration des queues

```env
QUEUE_CONNECTION=database  # ou 'redis'
```

### Demarrer le worker

```bash
php artisan queue:work --queue=high,default,low
```

En production, utilisez Supervisor:

```ini
[program:party-planner-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/party-planner/artisan queue:work --queue=high,default,low --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/party-planner/storage/logs/worker.log
stopwaitsecs=3600
```

## URLs de Webhooks

Configurez ces URLs dans les dashboards respectifs des services:

| Service | URL |
|---------|-----|
| Stripe | `https://votre-domaine.com/webhooks/stripe` |
| MTN Mobile Money | `https://votre-domaine.com/webhooks/mtn` |
| Airtel Money | `https://votre-domaine.com/webhooks/airtel` |
| Twilio SMS Status | `https://votre-domaine.com/webhooks/twilio/sms/status` |
| Twilio SMS Incoming | `https://votre-domaine.com/webhooks/twilio/sms/incoming` |
| Twilio WhatsApp Status | `https://votre-domaine.com/webhooks/twilio/whatsapp/status` |
| Twilio WhatsApp Incoming | `https://votre-domaine.com/webhooks/twilio/whatsapp/incoming` |

## API Endpoints

### Authentification
- `POST /api/login` - Connexion
- `POST /api/register` - Inscription
- `POST /api/logout` - Deconnexion

### Evenements
- `GET /api/events` - Liste des evenements
- `POST /api/events` - Creer un evenement
- `GET /api/events/{id}` - Details d'un evenement
- `PUT /api/events/{id}` - Modifier un evenement
- `DELETE /api/events/{id}` - Supprimer un evenement

### Invites
- `GET /api/events/{event}/guests` - Liste des invites
- `POST /api/events/{event}/guests` - Ajouter un invite
- `PUT /api/events/{event}/guests/{guest}` - Modifier un invite
- `DELETE /api/events/{event}/guests/{guest}` - Supprimer un invite

### Taches
- `GET /api/events/{event}/tasks` - Liste des taches
- `POST /api/events/{event}/tasks` - Creer une tache
- `PUT /api/events/{event}/tasks/{task}` - Modifier une tache
- `DELETE /api/events/{event}/tasks/{task}` - Supprimer une tache

### Budget
- `GET /api/events/{event}/budget` - Liste des elements de budget
- `POST /api/events/{event}/budget/items` - Ajouter un element
- `PUT /api/events/{event}/budget/items/{item}` - Modifier un element
- `DELETE /api/events/{event}/budget/items/{item}` - Supprimer un element

### Paiements
- `POST /api/payments/initiate` - Initier un paiement
- `POST /api/payments/mtn/initiate` - Initier un paiement MTN
- `POST /api/payments/airtel/initiate` - Initier un paiement Airtel
- `GET /api/payments/{payment}/status` - Statut d'un paiement

### Notifications
- `GET /api/notifications` - Liste des notifications
- `PUT /api/notifications/read-all` - Marquer toutes comme lues
- `POST /api/user/device-tokens` - Enregistrer un token de device

### Exports
- `GET /api/events/{event}/exports/guests/csv` - Export invites CSV
- `GET /api/events/{event}/exports/guests/xlsx` - Export invites Excel
- `GET /api/events/{event}/exports/guests/pdf` - Export invites PDF
- `GET /api/events/{event}/exports/budget/csv` - Export budget CSV
- `GET /api/events/{event}/exports/budget/xlsx` - Export budget Excel
- `GET /api/events/{event}/exports/report/pdf` - Rapport complet PDF

## Tests

```bash
php artisan test
```

## Deploiement

### Variables d'environnement production

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com

# Utiliser le cache
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Securite
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

### Commandes de deploiement

```bash
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
npm run build
```

## Structure du projet

```
app/
├── Enums/            # Enumerations PHP
├── Exports/          # Classes d'export Excel
├── Http/
│   ├── Controllers/
│   │   ├── Api/      # Controleurs API
│   │   └── Webhooks/ # Controleurs webhooks
│   ├── Middleware/
│   └── Requests/     # Form Requests pour la validation
│       ├── Admin/    # Requests pour les endpoints admin
│       │   ├── ListUsersRequest.php
│       │   ├── UpdateUserRoleRequest.php
│       │   ├── ListEventsRequest.php
│       │   ├── ListPaymentsRequest.php
│       │   ├── ListSubscriptionsRequest.php
│       │   ├── ListActivityLogsRequest.php
│       │   ├── StoreTemplateRequest.php
│       │   └── UpdateTemplateRequest.php
│       ├── Auth/
│       ├── Event/
│       ├── Guest/
│       ├── Task/
│       ├── Budget/
│       ├── Photo/
│       ├── Collaborator/
│       ├── Subscription/
│       └── Payment/
├── Jobs/             # Jobs asynchrones
├── Models/           # Modeles Eloquent
├── Notifications/    # Classes de notification
├── Policies/         # Policies d'autorisation
│   ├── EventPolicy.php
│   ├── PaymentPolicy.php
│   ├── CollaboratorPolicy.php
│   └── AdminPolicy.php
└── Services/         # Services metier
    ├── StripeService.php
    ├── FirebaseService.php
    ├── TwilioService.php
    ├── S3Service.php
    ├── PaymentService.php
    ├── SubscriptionService.php
    ├── NotificationService.php
    ├── ExportService.php
    └── AdminActivityService.php

tests/
├── Feature/
│   └── Api/
│       ├── EventControllerTest.php
│       ├── GuestControllerTest.php
│       ├── TaskControllerTest.php
│       ├── BudgetControllerTest.php
│       ├── PhotoControllerTest.php
│       ├── CollaboratorControllerTest.php
│       ├── PaymentControllerTest.php
│       ├── NotificationControllerTest.php
│       ├── SubscriptionControllerTest.php
│       ├── ExportControllerTest.php
│       ├── WebhookControllerTest.php
│       ├── AdminMiddlewareTest.php
│       ├── AdminDashboardTest.php
│       ├── AdminTemplateTest.php
│       └── AdminActivityLogTest.php
└── Unit/
    ├── Models/
    └── Services/
```

## Sauvegardes PostgreSQL (S3)

Sauvegardes automatiques de la base via [spatie/laravel-backup](https://github.com/spatie/laravel-backup).

| Paramètre | Valeur |
|-----------|--------|
| Fréquence | Toutes les **2 heures** |
| Contenu | Base PostgreSQL uniquement (`--only-db`) |
| Disque Laravel | `s3-backups` (réutilise `AWS_*` du `.env`) |
| Préfixe S3 | `BACKUP_S3_ROOT` (défaut : `backups/party-planner`) |
| Nom des archives | `party-planner/YYYY-MM-DD-HH-MM-SS.zip` (un **nouveau** fichier à chaque run) |
| Rétention | **90 jours** (`BACKUP_RETENTION_DAYS`) |
| Fuseau planificateur | `BACKUP_TIMEZONE` (défaut : `Africa/Brazzaville`) |

Chemin S3 typique (ex. production) :

`s3://{AWS_BUCKET}/backups/party-planner/party-planner/2026-05-18-13-42-35.zip`

### Variables d'environnement

Les identifiants **DB_*** et **AWS_*** du `.env` suffisent. Options dans `.env.example` :

- `BACKUP_DISK` — disque de destination (défaut `s3-backups`)
- `BACKUP_S3_ROOT` — dossier dans le bucket
- `BACKUP_NAME` — nom du jeu de sauvegardes (défaut `party-planner`)
- `BACKUP_RETENTION_DAYS` — rétention (défaut `90`)
- `BACKUP_TIMEZONE` — fuseau du scheduler
- `BACKUP_NOTIFICATION_EMAIL` — alertes en cas d'échec (sinon `FEEDBACK_MAIL_TO`)
- `PG_DUMP_PATH` — chemin de `pg_dump` sur le serveur

### Tâches planifiées (`routes/console.php`)

| Commande | Planification |
|----------|----------------|
| `backup:run --only-db` | Toutes les 2 heures |
| `backup:clean` | Quotidien à 03:30 |
| `backup:monitor` | Quotidien à 04:00 |

Le cron système doit exécuter le scheduler Laravel **chaque minute** :

```cron
* * * * * cd /home/alex/party-planner-backend && /usr/bin/php artisan schedule:run >> /home/alex/logs/schedule.log 2>&1
```

Vérifier la planification :

```bash
php artisan schedule:list
```

### Déploiement VPS (après `git pull`)

```bash
cd ~/party-planner-backend
git pull
composer install --no-dev --optimize-autoloader
php artisan config:cache

# Extensions / outils (utilisateur qui exécute PHP / cron)
sudo apt install -y postgresql-client php-zip
which pg_dump
php -m | grep -E 'zip|pgsql'

# DB_HOST joignable depuis PHP (souvent 127.0.0.1, pas "postgres")
grep '^DB_' .env
```

### Commandes utiles

**Lancer une sauvegarde immédiate :**

```bash
php artisan backup:run --only-db
# Verbose :
php artisan backup:run --only-db -v
```

**Lister les sauvegardes sur S3 (sans AWS CLI) :**

```bash
php artisan backup:list
```

**Contrôle de santé (dernière sauvegarde de moins de 24 h) :**

```bash
php artisan backup:monitor
```

**Appliquer la rétention manuellement :**

```bash
php artisan backup:clean
```

### Vérifier que ça fonctionne

1. `php artisan backup:run --only-db` se termine par `Backup completed!`
2. `php artisan backup:list` affiche le disque `s3-backups` en ✅ / Healthy
3. Console AWS S3 → bucket → `backups/party-planner/party-planner/` → fichier `.zip` daté (~taille du dump)

La CLI `aws` sur le VPS est **optionnelle** (Laravel envoie déjà vers S3). Si installée :

```bash
sudo apt install -y awscli
aws s3 ls "s3://${AWS_BUCKET}/backups/party-planner/party-planner/" --human-readable
```

### Restauration (aperçu, staging de préférence)

1. Télécharger l'archive depuis S3 (console ou `aws s3 cp`)
2. Extraire le dump SQL du `.zip`
3. Restaurer vers une base de test ou la prod **avec prudence** :

```bash
# Exemple : restaurer dans le conteneur Postgres
gunzip -c chemin/vers/dump.sql.gz 2>/dev/null | docker exec -i party-planner-postgres \
  psql -U party_app -d party-planner
```

Tester d'abord sur une copie de la base. Le dump peut contenir `--clean` (suppression des objets existants).

### Dépannage

| Symptôme | Piste |
|----------|--------|
| `pg_dump: command not found` | `sudo apt install postgresql-client` |
| Connexion DB refusée | `DB_HOST=127.0.0.1`, `DB_PORT=5432` dans `.env` |
| `Class "ZipArchive" not found` | `sudo apt install php-zip` puis redémarrer PHP-FPM si besoin |
| Erreur S3 / Access Denied | Droits IAM sur `s3://{bucket}/backups/party-planner/*` |
| Pas de backup automatique | `crontab -l`, logs `~/logs/schedule.log`, `php artisan schedule:list` |

## Licence

Proprietary - Tous droits reserves.
