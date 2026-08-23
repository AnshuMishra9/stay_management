$php = 'C:\xampp\php\php.exe'
$f   = "$PWD\application\core\Ops_Controller.php"
$fixed = 0
for ($i = 0; $i -lt 8; $i++) {
    $null = & $php -l $f 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { $ok = $true; break }
    Add-Content -Path $f -Value '}' -Encoding UTF8
    $fixed++
}
if ($fixed -gt 0) { Write-Output "appended $fixed brace(s)" }
if ($ok) { Write-Output 'LINT OK' } else { Write-Output 'STILL BROKEN' }
