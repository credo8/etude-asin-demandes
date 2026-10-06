# API de gestion des demandes d'actes administratifs

Étude de cas DEP/ASIN 2026. API REST permettant de déposer des demandes d'actes administratifs (acte de naissance, casier judiciaire, certificat de résidence) et de suivre leur traitement. Une page web simple (`/`) affiche les demandes d'un usager.

Stack : PHP 8.3, Laravel 13, SQLite (aucune base de données à installer).

## Prérequis

- PHP 8.3 ou plus, avec les extensions `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl` et `fileinfo`
- Composer 2

## Installation et démarrage

Linux / macOS :

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Windows (PowerShell) :

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database\database.sqlite
php artisan migrate
php artisan serve
```

L'application est disponible sur http://127.0.0.1:8000 (écran) et http://127.0.0.1:8000/api (API).

## Tests automatisés

```bash
php artisan test
```

Les tests utilisent une base SQLite en mémoire : ils n'altèrent pas les données de l'application.

## API

Aucune authentification n'est requise (non demandée par le sujet). Les requêtes et les réponses sont en JSON. Les erreurs sont toujours en JSON, même sans en-tête `Accept`.

| Méthode | Route | Rôle |
|---------|-------|------|
| POST | `/api/demandes` | Déposer une demande |
| GET | `/api/demandes?numero_npi=...` | Lister les demandes d'un usager |
| GET | `/api/demandes/{id}` | Consulter une demande |
| PATCH | `/api/demandes/{id}/statut` | Faire avancer le traitement |
| GET | `/api/demandes/statistiques` | Nombre de demandes par statut |

### Déposer une demande

`POST /api/demandes`

```json
{ "numero_npi": "0123456789", "type_acte": "acte_naissance", "nombre_copies": 2 }
```

- `numero_npi` : chaîne de exactement 10 chiffres
- `type_acte` : `acte_naissance`, `casier_judiciaire` ou `certificat_residence`
- `nombre_copies` : entier de 1 à 5

Réponse `201` : la demande avec son `id` et le statut `deposee`. Le statut ne peut pas être imposé par le client.

### Lister les demandes d'un usager

`GET /api/demandes?numero_npi=0123456789&statut=en_cours&page=1&per_page=20`

- `numero_npi` : obligatoire
- `statut` : facultatif (`deposee`, `en_cours`, `validee`, `rejetee`)
- Tri : de la plus récente à la plus ancienne
- Pagination : 20 demandes maximum par page (`per_page` supérieur à 20 est ramené à 20). La réponse contient `data`, `links` et `meta` (`current_page`, `last_page`, `total`...).

### Faire avancer le traitement

`PATCH /api/demandes/{id}/statut`

```json
{ "statut": "en_cours" }
```

```json
{ "statut": "rejetee", "motif_rejet": "Pièces justificatives illisibles." }
```

Cycle de vie : `deposee` → `en_cours` → `validee` ou `rejetee`.

- Un rejet doit toujours être motivé (`motif_rejet` de 3 à 500 caractères). Le motif est refusé pour tout autre statut.
- Une demande validée ne change plus. Une demande rejetée non plus (hypothèse, voir plus bas).
- Toute autre transition (saut d'étape, retour en arrière, même statut) est refusée.

### Statistiques

`GET /api/demandes/statistiques` (paramètre facultatif `numero_npi`)

```json
{ "data": { "total": 3, "par_statut": { "deposee": 2, "en_cours": 0, "validee": 1, "rejetee": 0 } } }
```

Les quatre statuts sont toujours présents, même à 0.

### Codes de réponse

| Code | Signification |
|------|---------------|
| 200 | Succès |
| 201 | Demande créée |
| 404 | Demande ou route introuvable |
| 405 | Méthode non autorisée |
| 409 | Action interdite (transition de statut impossible) |
| 422 | Saisie invalide |

Chaque erreur contient un `message` clair en français. Les erreurs de saisie contiennent en plus `errors`, champ par champ :

```json
{
  "message": "Les données fournies sont invalides.",
  "errors": { "numero_npi": ["Le NPI doit comporter exactement 10 chiffres."] }
}
```

## Choix de conception

- Modèle de données : une table `demandes` (NPI, type d'acte, nombre de copies, statut, motif de rejet, dates), indexée sur `(numero_npi, created_at)` et `statut`.
- Statuts et types d'acte sont des énumérations PHP ; les transitions autorisées sont décrites dans l'énumération `StatutDemande`.
- Validation dans des `FormRequest`, formatage des réponses dans une `API Resource`.
- Le changement de statut est une mise à jour conditionnelle (`WHERE statut = ancien statut`) : deux traitements simultanés ne s'écrasent pas.
- `APP_DEBUG=false` dans `.env.example` : aucune trace d'erreur n'est exposée.

## Hypothèses

- Le sujet ne demande pas d'authentification : l'usager est identifié par son NPI.
- Une demande rejetée est finale, comme une demande validée.
- Une demande ne revient jamais au statut `deposee`.
- Un même usager peut déposer plusieurs demandes identiques (aucune règle d'unicité n'est demandée).

## État d'avancement

| Élément | État |
|---------|------|
| Déposer une demande | Réalisé |
| Consulter les demandes d'un usager (tri, filtre par statut) | Réalisé |
| Cycle de vie, rejet motivé, messages clairs | Réalisé |
| Bonus : pagination (20 maximum) | Réalisé |
| Bonus : nombre de demandes par statut | Réalisé |
| Bonus : tests automatisés | Réalisé |
| Bonus : écran listant les demandes d'un usager | Réalisé |

Ce qui manque, et pourquoi : authentification et gestion de rôles (non demandées), modification ou suppression d'une demande (hors périmètre du sujet), consultation de toutes les demandes sans filtre d'usager (le sujet ne parle que des demandes d'un usager).
