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

### 7. Lancer le serveur

```bash
php artisan serve
npm run dev
```

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
TWILIO_FROM=+237xxxxxxxxx
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

## Licence

Proprietary - Tous droits reserves.
