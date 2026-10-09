# MoodFlow

Analyse ton humeur et reçois une playlist adaptée à ton état, à la météo et à ton activité.

![CI](https://github.com/QAliker/MoodFlow/actions/workflows/ci.yml/badge.svg)

<!-- Captures d'écran : déposer les images dans docs/screenshots/ -->
<!-- ![Dashboard](docs/screenshots/dashboard.png) -->
<!-- ![Analyse d'humeur](docs/screenshots/mood.png) -->
<!-- ![Mood DNA](docs/screenshots/dna.png) -->

## Fonctionnalités

- **Analyse d'humeur** : énergie, valence et intensité, traduites en recommandations musicales via l'API Deezer.
- **Contexte** : la météo et le mode d'activité influencent la sélection.
- **Mood DNA** : ton profil émotionnel construit à partir de ton historique.
- **Prédictions** : anticipation de ton humeur à partir des sessions passées.
- **Historique et stats** de tes sessions.
- **Temps réel** avec Laravel Reverb (WebSocket).

## Stack

| Partie | Techno |
|---|---|
| API | Laravel 12 (PHP 8.2), Sanctum, Reverb |
| Client | Nuxt 4, Nuxt UI, Pinia |
| Données | PostgreSQL, Redis |
| Infra | Docker Compose, GitHub Actions |

## Lancer en local

```bash
cp .env.example .env                              # puis remplir DEEZER_APP_ID / DEEZER_APP_SECRET
cp moodflow-api/.env.example moodflow-api/.env
docker compose up -d --build
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed
```

| Service | URL |
|---|---|
| Client | http://localhost:3000 |
| API | http://localhost:8000/api |
| Reverb | ws://localhost:8080 |

## Tests

```bash
docker compose exec api php artisan test
```
