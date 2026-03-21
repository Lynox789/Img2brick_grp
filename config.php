<?php
session_start();
require_once 'connexion/Database.php';
require_once 'classes/Security.php';
require_once 'classes/UserManager.php';
require_once 'classes/TokenManager.php';
require_once 'classes/Logger.php';

// use for sending email when password is forgotten
// set your URL of your site next to BASE_URL
define('BASE_URL', $_ENV['BASE_URL'] ?? '');

$db = Database::getInstance()->getConnection();
$userMgr = new UserManager($db);
$tokenMgr = new TokenManager($db);

// get the current language
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? 'en'; // English by default

//Fetch all text strings from the database at once
$colonneTexte = ($currentLang === 'en') ? 'content_en' : 'content_fr';
$textes = [];

try {
    // First check if the table exists to prevent crashing the whole site if the SQL setup hasn't been done
    $checkTable = $db->query("SHOW TABLES LIKE 'site_messages'");
    if($checkTable->rowCount() > 0) {
        $stmt = $db->query("SELECT msg_key, $colonneTexte FROM site_messages");
        $textes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (Exception $e) {
    
}

if (!function_exists('msg')) {
    function msg($key) {
        global $textes;
        return htmlspecialchars($textes[$key] ?? "[{$key}]");
    }
}

if (!function_exists('chargerEnv')){
    function chargerEnv($chemin_fichier) {
        // Verify if the file exist
        if (!file_exists($chemin_fichier)) {
            return false;
        }

        // Read line by line
        $lignes = file($chemin_fichier, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lignes as $ligne) {
            $ligne = trim($ligne);
            
            // Ignore the non-code line
            if (strpos($ligne, '#') === 0) {
                continue;
            }

            // trim value and name for variables
            if (strpos($ligne, '=') !== false) {
                list($nom, $valeur) = explode('=', $ligne, 2);
                $nom = trim($nom);
                // Erase space en double quote if there is some
                $valeur = trim($valeur, " \t\n\r\0\x0B\"'"); 
                
                // globale variable creation
                $_ENV[$nom] = $valeur;
            }
        }
    }
}
// launch new method if not set 
if(!isset($_ENV['TURNSTILE_SECRET'])){
    chargerEnv(__DIR__ . '/.env');    
}

?>