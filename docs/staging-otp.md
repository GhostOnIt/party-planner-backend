# Code de connexion fixe en staging

Activation explicite dans les services Railway de staging :

```dotenv
APP_ENV=staging
STAGING_FIXED_LOGIN_OTP_ENABLED=true
STAGING_OTP_ALLOWED_EMAILS=alexsonicka11@gmail.com
```

Après une demande de connexion, ce compte peut utiliser `1111` pour l'étape
de vérification par e-mail. Les inscriptions, les réinitialisations de mot
de passe, les autres canaux et les autres comptes gardent des codes aléatoires.
La valeur par défaut de l'option est false et la liste autorisée est vide.
Même activée par erreur, l'option est ignorée hors de APP_ENV=staging.

Il ne s'agit pas d'un contournement de la vérification : un OTP doit avoir
été demandé, ne pas être expiré, ne pas être déjà utilisé et ne pas avoir
épuisé ses tentatives. L'expiration et les protections existantes restent
inchangées. Les e-mails de staging restent dans les logs.

Tests de régression : `tests/Feature/StagingOtpTest.php`.
Les dix scénarios correspondants ont également été exécutés sur le runtime
PHP de staging avant déploiement, dans une transaction annulée, en substituant
uniquement le générateur aléatoire par une valeur déterministe. Ils couvrent
les restrictions d'environnement, de compte, d'activation, de type et de canal,
la normalisation des e-mails, l'usage unique et l'expiration.
Docker local n'étant pas disponible, la suite PHPUnit complète n'a pas été
relancée pour cette modification.
