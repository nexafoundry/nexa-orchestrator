<?php
/**
 * Générateurs de Produits Spécialisés
 * 4 types de produits selon la niche
 */

/**
 * Générer un SaaS Chat IA complet
 */
function generate_saas_chat($niche, $language, $keywords, $claude_key) {
    $prompt = "Crée un SaaS CHAT IA complet en $language pour:

Niche: $niche
Keywords: " . implode(', ', array_slice($keywords, 0, 10)) . "

Génère:
1. Dashboard HTML/CSS/JS (page de chat)
2. 50 réponses pré-écrites de l'IA coach
3. Système d'objectifs (3 objectifs types)
4. Page analytics (suivi progression)

Format JSON:
{
  \"dashboard\": \"HTML complet...\",
  \"responses\": [\"réponse1\", ..., \"réponse50\"],
  \"goals\": [{\"title\": \"\", \"description\": \"\", \"duration\": \"7 jours\"}, ...],
  \"analytics\": \"HTML page analytics...\"
}

TOUT en $language. JSON UNIQUEMENT.";

    return call_claude($prompt, $claude_key);
}

/**
 * Générer un SaaS Technique
 */
function generate_saas_technical($niche, $language, $keywords, $claude_key) {
    $prompt = "Crée un SaaS TECHNIQUE en $language pour:

Niche: $niche (tracking/monitoring/alertes)

Génère:
1. Dashboard technique HTML/CSS/JS
2. Page de configuration
3. Système d'alertes (email templates)
4. Documentation API

Format JSON:
{
  \"dashboard\": \"HTML...\",
  \"config\": \"HTML...\",
  \"alerts\": [\"template1\", ..., \"template5\"],
  \"api_docs\": \"HTML...\"
}

TOUT en $language. JSON UNIQUEMENT.";

    return call_claude($prompt, $claude_key);
}

/**
 * Générer Programme + PDFs
 */
function generate_program_pdfs($niche, $language, $keywords, $claude_key) {
    $prompt = "Crée 10 GUIDES PDF en $language pour:

Niche: $niche

Génère le CONTENU de 10 PDFs (format Markdown):
1. Guide principal (plan 30 jours)
2. Guide nutrition/exercices
3. Recettes/routines
4. FAQ complète
5. Success stories
6. Checklist quotidienne
7. Templates à remplir
8. Guide motivation
9. Erreurs à éviter
10. Bonus tips & astuces

Format JSON:
{
  \"pdfs\": [
    {\"title\": \"Guide Principal\", \"content\": \"# Titre\\n\\nContenu markdown...\"}, 
    ...
  ]
}

Chaque PDF = 1000-1500 mots. TOUT en $language. JSON UNIQUEMENT.";

    return call_claude($prompt, $claude_key);
}

/**
 * Générer Formation/Cours
 */
function generate_course($niche, $language, $keywords, $claude_key) {
    $prompt = "Crée une FORMATION complète en $language pour:

Niche: $niche

Génère 20 MODULES de cours:
- Chaque module = titre + objectifs + contenu (500 mots) + exercice

Format JSON:
{
  \"course\": {
    \"title\": \"Titre formation\",
    \"description\": \"Description...\",
    \"duration\": \"6 semaines\",
    \"modules\": [
      {
        \"number\": 1,
        \"title\": \"Module 1\",
        \"objectives\": [\"obj1\", \"obj2\"],
        \"content\": \"Contenu markdown...\",
        \"exercise\": \"Exercice pratique...\"
      },
      ... (20 modules)
    ]
  }
}

TOUT en $language. JSON UNIQUEMENT.";

    return call_claude($prompt, $claude_key);
}

/**
 * Appeler Claude (helper)
 */
function call_claude($prompt, $api_key) {
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-api-key: ' . $api_key,
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (!isset($data['content'][0]['text'])) {
        return null;
    }
    
    $text = $data['content'][0]['text'];
    
    if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
        return json_decode($matches[0], true);
    }
    
    return null;
}

