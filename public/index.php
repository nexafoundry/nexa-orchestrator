<?php
/**
 * Nexa Worker - API Root
 * Point d'entrée du worker
 */

header('Content-Type: application/json');

echo json_encode([
    'service' => 'Nexa Worker Cloud',
    'version' => '1.0.0',
    'status' => 'online',
    'endpoints' => [
        'POST /api/add-job.php' => 'Ajouter un job à exécuter',
        'GET /api/stats.php' => 'Métriques CPU/RAM/GPU',
        'GET /api/health.php' => 'Health check'
    ],
    'uptime' => exec('uptime -p') ?: 'N/A'
]);

