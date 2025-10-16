#!/usr/bin/env python3
"""
Nexa Orchestrator - Main Worker Orchestration System
Gère les jobs de génération de business, déploiement, et automation
"""

import asyncio
import logging
from datetime import datetime
from typing import Dict, List, Optional
import json
import mysql.connector
from mysql.connector import Error
import anthropic
import os
from dataclasses import dataclass
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Configuration from environment
DB_CONFIG = {
    'host': os.getenv('DB_HOST', '127.0.0.1'),
    'database': os.getenv('DB_NAME', 'nexasynapse'),
    'user': os.getenv('DB_USER', 'nexasynapse'),
    'password': os.getenv('DB_PASS')
}

ANTHROPIC_API_KEY = os.getenv('ANTHROPIC_API_KEY')

if not ANTHROPIC_API_KEY:
    raise ValueError("ANTHROPIC_API_KEY environment variable is required. Set it in .env file.")

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger('NexaOrchestrator')


@dataclass
class Job:
    """Représente un job à exécuter"""
    id: int
    user_id: int
    job_type: str
    priority: int
    params: dict
    status: str = 'pending'
    created_at: Optional[datetime] = None
    started_at: Optional[datetime] = None
    completed_at: Optional[datetime] = None
    error: Optional[str] = None
    result: Optional[dict] = None


class DatabaseManager:
    """Gestion de la connexion MySQL"""
    
    def __init__(self, config: dict):
        self.config = config
        self.connection = None
    
    def connect(self):
        """Établir connexion DB"""
        try:
            self.connection = mysql.connector.connect(**self.config)
            if self.connection.is_connected():
                logger.info("✅ Connected to MySQL database")
                return True
        except Error as e:
            logger.error(f"❌ Database connection error: {e}")
            return False
    
    def disconnect(self):
        """Fermer connexion"""
        if self.connection and self.connection.is_connected():
            self.connection.close()
            logger.info("🔌 Database connection closed")
    
    def get_pending_jobs(self, limit: int = 10) -> List[Job]:
        """Récupérer les jobs en attente"""
        cursor = self.connection.cursor(dictionary=True)
        
        query = """
            SELECT * FROM worker_jobs 
            WHERE status = 'pending' 
            ORDER BY priority DESC, created_at ASC 
            LIMIT %s
        """
        
        cursor.execute(query, (limit,))
        rows = cursor.fetchall()
        cursor.close()
        
        jobs = []
        for row in rows:
            jobs.append(Job(
                id=row['id'],
                user_id=row['user_id'],
                job_type=row['job_type'],
                priority=row['priority'],
                params=json.loads(row['params']) if row['params'] else {},
                status=row['status'],
                created_at=row['created_at']
            ))
        
        return jobs
    
    def update_job_status(self, job_id: int, status: str, result: dict = None, error: str = None):
        """Mettre à jour le statut d'un job"""
        cursor = self.connection.cursor()
        
        query = """
            UPDATE worker_jobs 
            SET status = %s, 
                result = %s,
                error = %s,
                completed_at = %s
            WHERE id = %s
        """
        
        completed_at = datetime.now() if status == 'completed' else None
        
        cursor.execute(query, (
            status,
            json.dumps(result) if result else None,
            error,
            completed_at,
            job_id
        ))
        
        self.connection.commit()
        cursor.close()
        
        logger.info(f"✅ Job {job_id} updated to status: {status}")


class AIGenerator:
    """Génération de contenu avec Claude AI"""
    
    def __init__(self, api_key: str):
        self.client = anthropic.Anthropic(api_key=api_key)
        self.model = "claude-sonnet-4-20250514"
    
    async def generate_content(self, prompt: str, max_tokens: int = 4096) -> str:
        """Générer du contenu avec Claude"""
        try:
            message = self.client.messages.create(
                model=self.model,
                max_tokens=max_tokens,
                messages=[{
                    "role": "user",
                    "content": prompt
                }]
            )
            
            return message.content[0].text
        
        except Exception as e:
            logger.error(f"❌ AI generation error: {e}")
            raise


class NexaOrchestrator:
    """Orchestrateur principal"""
    
    def __init__(self):
        self.db = DatabaseManager(DB_CONFIG)
        self.ai = AIGenerator(ANTHROPIC_API_KEY)
        self.running = False
    
    async def start(self):
        """Démarrer l'orchestrateur"""
        logger.info("🚀 Nexa Orchestrator starting...")
        
        if not self.db.connect():
            logger.error("❌ Cannot connect to database. Exiting.")
            return
        
        self.running = True
        logger.info("✅ Orchestrator is now running")
        
        try:
            while self.running:
                await self.process_jobs()
                await asyncio.sleep(5)  # Check every 5 seconds
        
        except KeyboardInterrupt:
            logger.info("⏸️ Orchestrator stopped by user")
        
        finally:
            self.db.disconnect()
    
    async def process_jobs(self):
        """Traiter les jobs en attente"""
        jobs = self.db.get_pending_jobs(limit=10)
        
        if not jobs:
            return
        
        logger.info(f"📋 Found {len(jobs)} pending jobs")
        
        # Traiter les jobs en parallèle
        tasks = [self.execute_job(job) for job in jobs]
        await asyncio.gather(*tasks, return_exceptions=True)
    
    async def execute_job(self, job: Job):
        """Exécuter un job spécifique"""
        logger.info(f"🔄 Processing job {job.id} ({job.job_type})")
        
        # Marquer comme en cours
        self.db.update_job_status(job.id, 'processing')
        
        try:
            # Router selon le type de job
            if job.job_type == 'generate_landing':
                result = await self.generate_landing_page(job)
            
            elif job.job_type == 'generate_funnel':
                result = await self.generate_funnel(job)
            
            elif job.job_type == 'deploy_site':
                result = await self.deploy_site(job)
            
            elif job.job_type == 'setup_payment':
                result = await self.setup_payment_system(job)
            
            elif job.job_type == 'generate_content':
                result = await self.generate_marketing_content(job)
            
            else:
                raise ValueError(f"Unknown job type: {job.job_type}")
            
            # Marquer comme complété
            self.db.update_job_status(job.id, 'completed', result=result)
            logger.info(f"✅ Job {job.id} completed successfully")
        
        except Exception as e:
            # Marquer comme échoué
            error_msg = str(e)
            self.db.update_job_status(job.id, 'failed', error=error_msg)
            logger.error(f"❌ Job {job.id} failed: {error_msg}")
    
    async def generate_landing_page(self, job: Job) -> dict:
        """Générer une landing page avec AI"""
        params = job.params
        
        prompt = f"""
        Génère une landing page complète pour un business {params.get('niche', 'générique')}.
        
        Pays: {params.get('country', 'France')}
        Langue: {params.get('language', 'français')}
        
        La page doit contenir:
        - Hero section accrocheuse
        - Bénéfices clairs
        - Social proof
        - Pricing transparent
        - CTA puissant
        - Design moderne et responsive
        
        Retourne le code HTML complet.
        """
        
        html_content = await self.ai.generate_content(prompt, max_tokens=4096)
        
        return {
            'html': html_content,
            'generated_at': datetime.now().isoformat()
        }
    
    async def generate_funnel(self, job: Job) -> dict:
        """Générer un funnel complet"""
        logger.info(f"🎯 Generating funnel for job {job.id}")
        # TODO: Implémenter génération funnel
        return {'status': 'generated'}
    
    async def deploy_site(self, job: Job) -> dict:
        """Déployer un site sur VPS"""
        logger.info(f"🚀 Deploying site for job {job.id}")
        # TODO: Implémenter déploiement
        return {'status': 'deployed'}
    
    async def setup_payment_system(self, job: Job) -> dict:
        """Configurer Stripe"""
        logger.info(f"💳 Setting up payment for job {job.id}")
        # TODO: Implémenter setup Stripe
        return {'status': 'configured'}
    
    async def generate_marketing_content(self, job: Job) -> dict:
        """Générer du contenu marketing"""
        logger.info(f"📝 Generating marketing content for job {job.id}")
        # TODO: Implémenter génération contenu
        return {'status': 'generated'}


async def main():
    """Point d'entrée principal"""
    logger.info("=" * 60)
    logger.info("🦄 NEXA ORCHESTRATOR v1.0")
    logger.info("=" * 60)
    
    orchestrator = NexaOrchestrator()
    await orchestrator.start()


if __name__ == "__main__":
    asyncio.run(main())
