<?php
/**
 * Worker API - Métriques CPU/RAM/GPU en temps réel
 */

header('Content-Type: application/json');

// Récupérer les métriques système
function get_cpu_usage() {
    $load = sys_getloadavg();
    return round($load[0] * 100 / 4, 2); // Approximation pour 4 cores
}

function get_ram_usage() {
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    $memory_usage = round($mem[2]/$mem[1]*100, 2);
    return $memory_usage;
}

function get_gpu_usage() {
    // Si nvidia-smi disponible
    $gpu = @shell_exec('nvidia-smi --query-gpu=utilization.gpu --format=csv,noheader,nounits');
    if ($gpu !== null) {
        return floatval(trim($gpu));
    }
    return 0; // Pas de GPU
}

// Worker ID (depuis env ou hostname)
$worker_id = getenv('WORKER_ID') ?: gethostname();

echo json_encode([
    'worker_id' => $worker_id,
    'cpu' => get_cpu_usage(),
    'ram' => get_ram_usage(),
    'gpu' => get_gpu_usage(),
    'timestamp' => time(),
    'uptime' => exec('uptime -p') ?: 'N/A'
]);

