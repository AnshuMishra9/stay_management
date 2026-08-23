<?php
/**
 * Find the first line where brace-depth breaks in Ops_Controller.php
 * (naive tokenizer: skips // # * comments and quoted strings).
 */
$f = dirname(__DIR__).'/application/core/Ops_Controller.php';
$lines = file($f);
$depth = 0;
$inStr = null; $inComment = false;
foreach ($lines as $ln => $line) {
    $prev = '';
    $len = strlen($line);
    for ($i = 0; $i < $len; $i++) {
        $ch = $line[$i];
        if ($inComment) { if ($ch === "\n") { $inComment = false; } continue; }
        if ($inStr !== null) {
            if ($ch === '\\') { $i++; continue; }
            if ($ch === $inStr) { $inStr = null; }
            continue;
        }
        if ($ch === "'" || $ch === '"') { $inStr = $ch; continue; }
        if ($ch === '/' && isset($line[$i+1]) && $line[$i+1] === '/') break;
        if ($ch === '{') { $depth++; }
        elseif ($ch === '}') {
            $depth--;
            if ($depth < 0) {
                echo "EXTRA } at line ".($ln+1)."\n--- context ---\n";
                echo implode('', array_slice($lines, max(0,$ln-6), 7));
                exit(1);
            }
        }
    }
}
echo "No premature close. Final depth: {$depth} (".(count($lines))." lines)\n";
