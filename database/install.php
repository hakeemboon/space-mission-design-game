<?php
/**
 * TerraWatch-1 — database installer (module M2). Command line only.
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Usage: php database/install.php
 * Drops and recreates the 16 MVP tables, then loads the seed data.
 * WARNING: deletes all saved missions and runs.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/src/bootstrap.php';

/** Split a SQL file into statements: a statement ends with ';' at the end of a line. */
function sql_statements(string $file): array
{
    $lines = preg_split('/\R/', file_get_contents($file));
    $statements = [];
    $buffer = '';
    foreach ($lines as $line) {
        if (preg_match('/^\s*--/', $line) || trim($line) === '') {
            continue;
        }
        $buffer .= $line . "\n";
        if (preg_match('/;\s*$/', $line)) {
            $statements[] = trim($buffer);
            $buffer = '';
        }
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException("Unterminated statement in $file");
    }
    return $statements;
}

$pdo = db();
foreach (['schema.sql', 'seed.sql'] as $file) {
    $count = 0;
    foreach (sql_statements(__DIR__ . '/' . $file) as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            fwrite(STDERR, "FAILED in $file: " . $e->getMessage() . "\n" . substr($sql, 0, 300) . "\n");
            exit(1);
        }
        $count++;
    }
    echo "$file: $count statements OK\n";
}
echo "Install complete.\n";
