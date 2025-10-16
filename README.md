# 🚀 Nexa Orchestrator

Orchestrateur Python pour la génération et gestion automatique de business avec IA.

## 🎯 Fonctionnalités

- **Job Queue System** : Traitement asynchrone des tâches
- **AI Generation** : Génération de contenu avec Claude AI
- **Multi-Worker** : Scaling horizontal
- **Monitoring** : Logs et metrics en temps réel
- **Auto-Retry** : Retry automatique en cas d'échec

## 📦 Installation

```bash
# Installer les dépendances
pip install -r requirements.txt

# Configurer l'environnement
cp .env.example .env
# Éditer .env avec vos credentials
```

## 🚀 Lancement

```bash
# Démarrer l'orchestrateur
python orchestrator.py

# Ou avec uvicorn (pour API)
uvicorn api:app --reload --port 8001
```

## 🔧 Configuration

Variables d'environnement (.env):

```env
# Database
DB_HOST=127.0.0.1
DB_NAME=nexasynapse
DB_USER=nexasynapse
DB_PASS=Vinsmcmc1!!

# AI
ANTHROPIC_API_KEY=sk-ant-...
OPENAI_API_KEY=sk-...

# Scaleway
SCALEWAY_ACCESS_KEY=...
SCALEWAY_SECRET_KEY=...

# NameSilo
NAMESILO_API_KEY=aa8f00ab77a9ec89b07e

# Stripe
STRIPE_SECRET_KEY=sk_live_...
```

## 📊 Architecture

```
nexa-orchestrator/
├── orchestrator.py        # Main orchestrator
├── workers/               # Job processors
│   ├── landing_generator.py
│   ├── content_generator.py
│   ├── deployment_worker.py
│   └── payment_setup.py
├── api/                   # FastAPI endpoints
│   └── jobs.py
├── utils/                 # Helpers
│   ├── db.py
│   ├── ai.py
│   └── scaleway.py
├── models/                # Data models
│   └── job.py
└── tests/                 # Tests unitaires
```

## 🎯 Types de Jobs

| Type | Description | Durée |
|------|-------------|-------|
| `generate_landing` | Génération landing page IA | ~30s |
| `generate_funnel` | Génération funnel complet | ~60s |
| `deploy_site` | Déploiement sur VPS | ~2min |
| `setup_payment` | Configuration Stripe | ~10s |
| `generate_content` | Contenu marketing | ~20s |

## 📈 Monitoring

```bash
# Voir les logs en temps réel
tail -f logs/orchestrator.log

# Stats via API
curl http://localhost:8001/stats

# Health check
curl http://localhost:8001/health
```

## 🔄 Workflow

```
1. Job créé dans DB (via PHP API)
2. Orchestrator détecte job (polling 5s)
3. Job assigné à worker
4. Worker exécute (avec AI si besoin)
5. Résultat sauvegardé en DB
6. Job marqué completed
```

## 🚀 Deploy

```bash
# Via Docker
docker build -t nexa-orchestrator .
docker run -d nexa-orchestrator

# Via systemd
sudo cp nexa-orchestrator.service /etc/systemd/system/
sudo systemctl enable nexa-orchestrator
sudo systemctl start nexa-orchestrator
```

## 📝 Développement

```bash
# Formatter le code
black .

# Linter
flake8 .

# Tests
pytest

# Type checking
mypy orchestrator.py
```

## 📄 License

© 2025 Nexa Foundry AI - All Rights Reserved

