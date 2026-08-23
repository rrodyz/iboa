<?php

/**
 * [A3 — durcissement dump MySQL] Un CREATE TABLE sans clause ENGINE explicite
 * retombe sur @@default_storage_engine du serveur cible. En local (Laragon/
 * MySQL 8) ce défaut est InnoDB — sur un hébergement mutualisé (constaté :
 * PlanetHoster N0C / MariaDB 10.6, default=MyISAM) la même table se crée
 * dans un moteur différent, et une FK InnoDB ne peut pas référencer une
 * table non-InnoDB (MySQL #1005 / errno 150). Toutes les tables A3 doivent
 * être InnoDB par écriture explicite dans le dump — jamais par héritage du
 * défaut serveur.
 *
 * Lit et valide un dump .sql, ne le modifie jamais.
 *
 * Usage : php scripts/validate-mysql-dump.php <chemin-du-dump.sql>
 * Sortie : exit 0 si valide, exit 1 si au moins une anomalie.
 */

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php validate-mysql-dump.php <dump.sql>\n");
    exit(2);
}

$path = $argv[1];
if (! is_file($path)) {
    fwrite(STDERR, "Fichier introuvable : {$path}\n");
    exit(2);
}

const EXPECTED_CHARSET  = 'utf8mb4';
const EXPECTED_COLLATION = 'utf8mb4_unicode_ci';

$handle = fopen($path, 'r');
if ($handle === false) {
    fwrite(STDERR, "Impossible d'ouvrir : {$path}\n");
    exit(2);
}

$totalTables = 0;
$innodbCount = 0;
$nonInnodbCount = 0;
$missingEngineCount = 0;
$missingCharsetCount = 0;
$errors = [];

$inTable = false;
$currentTable = null;

while (($line = fgets($handle)) !== false) {
    // Début d'un bloc CREATE TABLE — phpMyAdmin/mysqldump place toujours le
    // nom entre backticks sur cette même ligne.
    if (! $inTable && preg_match('/^CREATE TABLE `([^`]+)`/', $line, $m)) {
        $inTable = true;
        $currentTable = $m[1];
        continue;
    }

    if (! $inTable) {
        continue;
    }

    // Fin du bloc — la parenthèse fermante est TOUJOURS seule en tête de
    // ligne dans ce format d'export (aucune définition de colonne/enum ne
    // commence par ")"), ce qui distingue le vrai terminateur d'un simple
    // ")" apparaissant au milieu d'une définition ENUM/SET.
    if (preg_match('/^\)\s*(.*);\s*$/', $line, $m)) {
        $tail = $m[1];
        $totalTables++;

        $hasEngine = (bool) preg_match('/ENGINE\s*=\s*(\w+)/i', $tail, $eng);
        $engine = $hasEngine ? strtoupper($eng[1]) : null;

        if (! $hasEngine) {
            $missingEngineCount++;
            $errors[] = ['table' => $currentTable, 'error' => 'missing ENGINE clause'];
        } elseif ($engine !== 'INNODB') {
            $nonInnodbCount++;
            $errors[] = ['table' => $currentTable, 'error' => "engine is {$engine}, expected InnoDB"];
        } else {
            $innodbCount++;
        }

        if ($hasEngine && $engine === 'INNODB') {
            $hasCharset   = (bool) preg_match('/CHARSET\s*=\s*' . preg_quote(EXPECTED_CHARSET, '/') . '\b/i', $tail);
            $hasCollation = (bool) preg_match('/COLLATE\s*=\s*' . preg_quote(EXPECTED_COLLATION, '/') . '\b/i', $tail);
            if (! $hasCharset || ! $hasCollation) {
                $missingCharsetCount++;
                $errors[] = [
                    'table' => $currentTable,
                    'error' => sprintf(
                        'expected CHARSET=%s COLLATE=%s, got: %s',
                        EXPECTED_CHARSET, EXPECTED_COLLATION, trim($tail) ?: '(aucun)'
                    ),
                ];
            }
        }

        $inTable = false;
        $currentTable = null;
    }
}
fclose($handle);

$result = ($missingEngineCount === 0 && $nonInnodbCount === 0 && $missingCharsetCount === 0) ? 'PASS' : 'FAIL';

echo "A3 MYSQL DUMP VALIDATION\n\n";
echo "File:\n" . basename($path) . "\n\n";
echo "CREATE TABLE:\n{$totalTables}\n\n";
echo "InnoDB:\n{$innodbCount}\n\n";
echo "Non-InnoDB:\n{$nonInnodbCount}\n\n";
echo "Missing ENGINE:\n{$missingEngineCount}\n\n";
echo "Missing utf8mb4:\n{$missingCharsetCount}\n\n";
echo "Invalid tables:\n" . count($errors) . "\n\n";

foreach ($errors as $e) {
    echo "TABLE:\n{$e['table']}\n\nERROR:\n{$e['error']}\n\n";
}

echo "RESULT:\n{$result}\n";

exit($result === 'PASS' ? 0 : 1);
