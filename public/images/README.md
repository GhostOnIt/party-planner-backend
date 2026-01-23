# Logo pour les emails

Placez votre fichier logo ici pour qu'il soit utilisé dans les emails de l'application.

## Formats supportés

- `logo.png` (recommandé)
- `logo.svg`

## Configuration

### Option 1 : Logo automatique (recommandé)

1. Placez votre logo dans ce dossier avec le nom `logo.png` ou `logo.svg`
2. Le logo sera automatiquement détecté et utilisé dans tous les emails

### Option 2 : Configuration manuelle via .env

Si vous préférez utiliser une URL personnalisée (par exemple, un CDN), ajoutez dans votre fichier `.env` :

```env
MAIL_LOGO_URL=https://votre-domaine.com/images/logo.png
```

## Taille recommandée

- **Largeur** : 200-300px
- **Hauteur** : 48-75px (sera automatiquement ajustée dans les emails)
- **Format** : PNG avec fond transparent ou SVG

## Exemple d'URL

Si votre application est accessible à `https://party-planner.com`, le logo sera accessible à :
- `https://party-planner.com/images/logo.png`

