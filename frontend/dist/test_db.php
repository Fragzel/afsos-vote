<?php
header("Content-Type: text/plain; charset=UTF-8");

$dbPath = __DIR__ . '/../../backend/database.sqlite';
$logPath = __DIR__ . '/../../backend/api.log';

echo "=== DIAGNOSTIC VOTE-AFSOS ===\n\n";

if (!file_exists($dbPath)) {
    echo "Base de données introuvable à la position : $dbPath\n";
} else {
    try {
        $db = new SQLite3($dbPath);
        
        $totalUsers = $db->querySingle("SELECT COUNT(*) FROM users");
        $sentEmails = $db->querySingle("SELECT COUNT(*) FROM users WHERE reminder_sent IS NOT NULL");
        $votedUsers = $db->querySingle("SELECT COUNT(*) FROM users WHERE has_voted = 1");
        
        echo "Base de données SQLite : CONNECTÉE\n";
        echo "Nombre total d'électeurs dans la base : $totalUsers\n";
        echo "Nombre d'invitations enregistrées comme ENVOYÉES : $sentEmails\n";
        echo "Nombre de personnes ayant VOTÉ : $votedUsers\n\n";
        
        echo "=== Liste des 10 dernières invitations envoyées ===\n";
        $res = $db->query("SELECT email, reminder_sent FROM users WHERE reminder_sent IS NOT NULL ORDER BY reminder_sent DESC LIMIT 10");
        $hasRows = false;
        while ($row = $res->fetchArray(SQLite3_ASSOC)) {
            $hasRows = true;
            echo "- " . $row['email'] . " (envoyé le " . $row['reminder_sent'] . ")\n";
        }
        if (!$hasRows) {
            echo "(Aucune invitation envoyée pour le moment dans la base)\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "Erreur d'accès à la base de données : " . $e->getMessage() . "\n\n";
    }
}

echo "=== CONTENU DU JOURNAL DE LOGS (api.log) ===\n";
if (!file_exists($logPath)) {
    echo "Aucun fichier journal api.log trouvé à la position : $logPath\n";
} else {
    $logs = file($logPath);
    // Print last 50 lines of logs
    $lastLogs = array_slice($logs, -50);
    foreach ($lastLogs as $line) {
        echo $line;
    }
}
