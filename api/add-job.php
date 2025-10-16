<?php
/**
 * Worker API - Recevoir un job à exécuter
 */

header('Content-Type: application/json');

// Recevoir le job
$input = json_decode(file_get_contents('php://input'), true);

$job_id = $input['id'] ?? '';
$type = $input['type'] ?? 'site_generation';
$niche = $input['niche'] ?? '';
$country = $input['country'] ?? '';
$params = $input['params'] ?? [];

if (empty($job_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'job_id required']);
    exit;
}

// Sauvegarder le job localement
$job_file = __DIR__ . '/../storage/current_job.json';
file_put_contents($job_file, json_encode($input));

error_log("📋 Job received: $job_id - $niche / $country");

// Le job sera traité par l'orchestrateur en arrière-plan
echo json_encode([
    'success' => true,
    'job_id' => $job_id,
    'message' => 'Job queued for processing'
]);

