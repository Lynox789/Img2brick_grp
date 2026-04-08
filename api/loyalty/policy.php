<?php
/**
 * api/loyalty/policy.php
 *
 * Endpoint API qui retourne la politique de points de fidélité.
 * Appelé par le backend Node.js pour synchroniser la politique.
 *
 * La politique peut être dynamique : bonus le weekend, happy hours, etc.
 * Placez ce fichier dans votre projet PHP S3 (ex: /api/loyalty/policy.php)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

//  Configuration de la politique 

$dayOfWeek = date('N'); // 1=lundi, 7=dimanche
$hour = (int)date('H');

$timeMultiplier = 1.0;
if ($dayOfWeek >= 6) {
    $timeMultiplier = 1.5; // Weekend
} elseif ($hour >= 18 && $hour <= 22) {
    $timeMultiplier = 1.25; // Happy hour en soirée
}

$policy = [
    // Ratio score → points (10 points de score = 1 point fidélité)
    'scoreToPointsRatio' => 0.1,
    
    // Points minimum par partie (même score = 0)
    'minimumPoints' => 5,
    
    // Points maximum par partie
    'maximumPoints' => 500,
    
    // Multiplicateur temporel (calculé dynamiquement)
    'timeMultiplier' => $timeMultiplier,
    
    // Durée de validité des points en jours
    'validityDays' => 30,
    
    // Bonus pour le gagnant en mode Duplicate
    'winnerBonus' => 50,
    
    // Valeur monétaire d'un point (en euros)
    'pointMonetaryValue' => 0.01,
    
    // Métadonnées
    'updatedAt' => date('c'),
    'isWeekend' => $dayOfWeek >= 6,
    'isHappyHour' => $hour >= 18 && $hour <= 22,
];

echo json_encode([
    'success' => true,
    'policy' => $policy,
]);
