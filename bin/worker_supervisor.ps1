# Supervisor do worker da fila (bin/worker.php) — reinicia sozinho se o
# processo cair, e fica registrado no Agendador de Tarefas do Windows pra
# ligar de novo no login, sem precisar lembrar de rodar `php bin/worker.php`
# manualmente (era exatamente essa lacuna que deixava rascunhos travados
# indefinidamente na fila do Redis sem ninguém consumindo — 2026-09-09).
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$logDir = Join-Path $root 'storage\logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$logFile = Join-Path $logDir 'worker.log'

while ($true) {
    Add-Content -Path $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [supervisor] iniciando worker"
    # Redirecionamento nativo via cmd /c (nao o operador *>> do PowerShell):
    # PowerShell 5.1 embrulha qualquer coisa que um .exe nativo escreva em
    # stderr num NativeCommandError (mesmo linha informativa, nao so erro
    # de verdade) quando redirecionada com *>>/2>&1 direto no PowerShell -
    # cmd.exe usa redirecionamento de shell puro, sem essa armadilha.
    cmd /c "php bin\worker.php >> `"$logFile`" 2>&1"
    Add-Content -Path $logFile -Value "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss') [supervisor] worker saiu (codigo $LASTEXITCODE) - reiniciando em 5s"
    Start-Sleep -Seconds 5
}
