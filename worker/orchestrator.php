<?php
/**
 * Orchestrateur Worker - Boucle principale qui exécute les jobs
 * À lancer en background: php orchestrator.php
 */

// Config
$ENGINE_URL = getenv('ENGINE_URL') ?: 'https://nexafoundry.ai/love/public';
$ENGINE_TOKEN = getenv('ENGINE_TOKEN') ?: 'nexa-engine-secret';
$WORKER_ID = getenv('WORKER_ID') ?: gethostname();
$CLAUDE_API_KEY = getenv('CLAUDE_API_KEY') ?: '';

echo "🦄 Nexa Worker Orchestrator Started\n";
echo "Worker ID: $WORKER_ID\n";
echo "Engine URL: $ENGINE_URL\n";
echo "---\n\n";

// Boucle infinie
while (true) {
    try {
        // 1. Envoyer heartbeat (stats) au moteur toutes les 30s
        send_heartbeat($ENGINE_URL, $ENGINE_TOKEN, $WORKER_ID);
        
        // 2. Vérifier s'il y a un job à traiter
        $job_file = __DIR__ . '/../storage/current_job.json';
        
        if (file_exists($job_file)) {
            $job = json_decode(file_get_contents($job_file), true);
            
            if ($job) {
                echo "📋 Processing job: {$job['id']}\n";
                
                // Traiter le job
                $result = process_job($job, $CLAUDE_API_KEY, $WORKER_ID, $ENGINE_URL, $ENGINE_TOKEN);
                
                // Notifier le moteur
                notify_job_completed($job['id'], $result, $ENGINE_URL, $ENGINE_TOKEN, $WORKER_ID);
                
                // Supprimer le job local
                unlink($job_file);
                
                echo "✅ Job {$job['id']} completed\n\n";
            }
        }
        
        // Attendre 5 secondes avant next iteration
        sleep(5);
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        sleep(10);
    }
}

/**
 * Envoyer heartbeat au moteur
 */
function send_heartbeat($engine_url, $token, $worker_id) {
    static $last_heartbeat = 0;
    
    // Envoyer seulement toutes les 30s
    if (time() - $last_heartbeat < 30) {
        return;
    }
    
    $stats = get_system_stats();
    
    $ch = curl_init("$engine_url/api/nexa_stats.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Engine-Token: ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'worker_id' => $worker_id,
            'cpu' => $stats['cpu'],
            'ram' => $stats['ram'],
            'gpu' => $stats['gpu'],
            'status' => 'idle'
        ]),
        CURLOPT_TIMEOUT => 5
    ]);
    
    curl_exec($ch);
    curl_close($ch);
    
    $last_heartbeat = time();
}

/**
 * Récupérer stats système
 */
function get_system_stats() {
    $load = sys_getloadavg();
    $cpu = round($load[0] * 100 / 4, 2);
    
    // RAM
    $free = @shell_exec('free');
    $ram = 0;
    if ($free) {
        $free_arr = explode("\n", trim($free));
        if (isset($free_arr[1])) {
            $mem = array_values(array_filter(explode(" ", $free_arr[1])));
            if (isset($mem[2]) && isset($mem[1]) && $mem[1] > 0) {
                $ram = round($mem[2]/$mem[1]*100, 2);
            }
        }
    }
    
    // GPU
    $gpu = 0;
    $gpu_output = @shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits');
    if ($gpu_output !== null) {
        $gpu = floatval(trim($gpu_output));
    }
    
    return ['cpu' => $cpu, 'ram' => $ram, 'gpu' => $gpu];
}

/**
 * Traiter un job avec Claude AI
 */
function process_job($job, $claude_key, $worker_id, $engine_url, $token) {
    $niche = $job['niche'] ?? '';
    $country = $job['country'] ?? '';
    $domain = $job['domain'] ?? '';
    $params = $job['params'] ?? [];
    $niche_slug = $params['niche_slug'] ?? '';
    
    // Charger les générateurs de produits
    require_once __DIR__ . '/product_generators.php';
    
    // Déterminer le type de produit
    $product_type = $params['product_type'] ?? 'saas_chat'; // Par défaut
    
    update_job_progress($job['id'], 10, $engine_url, $token, $worker_id);
    
    // Générer selon le type de produit
    $product_result = null;
    
    switch ($product_type) {
        case 'saas_chat':
            update_job_progress($job['id'], 20, $engine_url, $token, $worker_id);
            $product_result = generate_saas_chat($niche, $country, [], $claude_key);
            break;
            
        case 'saas_technical':
            update_job_progress($job['id'], 20, $engine_url, $token, $worker_id);
            $product_result = generate_saas_technical($niche, $country, [], $claude_key);
            break;
            
        case 'program_pdf':
            update_job_progress($job['id'], 20, $engine_url, $token, $worker_id);
            $product_result = generate_program_pdfs($niche, $country, [], $claude_key);
            break;
            
        case 'course':
            update_job_progress($job['id'], 20, $engine_url, $token, $worker_id);
            $product_result = generate_course($niche, $country, [], $claude_key);
            break;
            
        default:
            // Fallback: juste les landing pages
            $product_result = null;
    }
    
    update_job_progress($job['id'], 50, $engine_url, $token, $worker_id);
    
    // Générer les landing pages (toujours)
    $prompt = "Génère 4 landing pages HTML pour:
Niche: $niche
Pays: $country
Type produit: $product_type

Landing pages complètes (A, B, C, D) standalone.
Responsive. CTA → https://pay.nexafoundry.ai/checkout?product=$domain

JSON:
{\"landings\": [{\"variant\": \"A\", \"html\": \"...\"}, ...]}";

    update_job_progress($job['id'], 30, $engine_url, $token, $worker_id);
    
    // Appel Claude
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $claude_key,
            'anthropic-version: 2023-06-01'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'claude-sonnet-4-20250514',
            'max_tokens' => 8000,
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ]),
        CURLOPT_TIMEOUT => 120
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    update_job_progress($job['id'], 70, $engine_url, $token, $worker_id);
    
    $data = json_decode($response, true);
    
    if (!isset($data['content'][0]['text'])) {
        throw new Exception("Claude AI response invalid");
    }
    
    $text = $data['content'][0]['text'];
    
    // Extraire JSON
    if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
        $result = json_decode($matches[0], true);
        
        if ($result && isset($result['landings'])) {
            // Sauvegarder les landing pages
            $site_dir = __DIR__ . '/../storage/sites/' . $domain;
            @mkdir($site_dir, 0755, true);
            
            foreach ($result['landings'] as $landing) {
                $file = "$site_dir/landing-{$landing['variant']}.html";
                file_put_contents($file, $landing['html']);
            }
            
            update_job_progress($job['id'], 100, $engine_url, $token, $worker_id);
            
            return [
                'site_url' => "http://" . $_SERVER['SERVER_ADDR'] . ":8080/storage/sites/$domain/",
                'landings_count' => count($result['landings'])
            ];
        }
    }
    
    throw new Exception("Failed to parse Claude response");
}

function update_job_progress($job_id, $progress, $engine_url, $token, $worker_id) {
    // TODO: Envoyer au moteur
}

function notify_job_completed($job_id, $result, $engine_url, $token, $worker_id) {
    $ch = curl_init("$engine_url/api/nexa_webhook.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Engine-Token: ' . $token
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'worker_id' => $worker_id,
            'job_id' => $job_id,
            'status' => 'completed',
            'result' => $result
        ]),
        CURLOPT_TIMEOUT => 10
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}

