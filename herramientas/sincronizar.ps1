#requires -Version 5.1
param(
    [Parameter(Mandatory = $true)][ValidateSet('salida', 'llegada')][string]$Modo,
    [switch]$Auto
)

$ErrorActionPreference = 'Stop'
$proyecto = Split-Path -Parent $PSScriptRoot
$xampp    = Split-Path (Split-Path $proyecto)
$bin      = Join-Path $xampp 'mysql\bin'
$mysql    = Join-Path $bin 'mysql.exe'
$mysqldump = Join-Path $bin 'mysqldump.exe'
$mysqladmin = Join-Path $bin 'mysqladmin.exe'
$respaldos = Join-Path $xampp 'mysql\respaldos'
$marca     = Join-Path $respaldos 'ultimo_import.txt'
$rama      = 'main'

function Fallo($msg) { Write-Host "`n[X] $msg" -ForegroundColor Red; exit 1 }
function Ok($msg)    { Write-Host "[OK] $msg" -ForegroundColor Green }
function Info($msg)  { Write-Host "[..] $msg" -ForegroundColor Cyan }

if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Fallo 'git no esta instalado en esta PC.' }
foreach ($exe in @($mysql, $mysqldump, $mysqladmin)) {
    if (-not (Test-Path $exe)) { Fallo "No encuentro $exe. El proyecto debe estar en <xampp>\htdocs\form.mx." }
}
& $mysqladmin -u root ping *> $null
if ($LASTEXITCODE -ne 0) { Fallo 'MySQL no esta encendido. Inicia MySQL en el panel de XAMPP y vuelve a intentar.' }

$usb = $null
foreach ($d in (Get-PSDrive -PSProvider FileSystem)) {
    if (Test-Path (Join-Path $d.Root 'form.mx.git\HEAD')) { $usb = $d.Root; break }
}
if (-not $usb) { Fallo 'No encuentro la USB con el repositorio (carpeta form.mx.git). Conecta la USB.' }
$repoUsb   = (Join-Path $usb 'form.mx.git') -replace '\\', '/'
$carpetaBd = Join-Path $usb 'form.mx-bd'
$dumpUsb   = Join-Path $carpetaBd 'respaldo_dat.sql'
Info "USB encontrada en $usb"

Set-Location $proyecto

$seguros = @(git config --global --get-all safe.directory)
if ($seguros -notcontains $repoUsb) { git config --global --add safe.directory $repoUsb | Out-Null }

$remotos = @(git remote)
if ($remotos -notcontains 'usb') { git remote add usb $repoUsb } else { git remote set-url usb $repoUsb }
git fetch -q usb
if ($LASTEXITCODE -ne 0) { Fallo 'No se pudo leer el repositorio de la USB.' }

if ($Modo -eq 'salida') {
    $tieneRemoto = git rev-parse --verify --quiet "usb/$rama"
    if ($tieneRemoto) {
        $atras = [int](git rev-list --count "HEAD..usb/$rama")
        if ($atras -gt 0) { Fallo "La USB tiene cambios mas nuevos que esta PC ($atras commits). Corre primero 'TRAER DE USB'." }
    }

    New-Item -ItemType Directory -Force -Path $carpetaBd | Out-Null
    $tmp = Join-Path $env:TEMP 'respaldo_dat.sql'
    & $mysqldump -u root --databases dat --add-drop-database --routines --default-character-set=utf8mb4 --result-file=$tmp
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $tmp) -or (Get-Item $tmp).Length -lt 2000) { Fallo 'No se pudo respaldar la base dat (mysqldump fallo).' }
    if (Test-Path $dumpUsb) { Copy-Item $dumpUsb (Join-Path $carpetaBd 'respaldo_dat.anterior.sql') -Force }
    Copy-Item $tmp $dumpUsb -Force
    Remove-Item $tmp -Force
    Ok "Base respaldada en $dumpUsb"

    git add -A
    if (git status --porcelain) {
        git commit -q -m ("Sincronizacion {0} desde {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm'), $env:COMPUTERNAME)
        Ok 'Cambios guardados (commit).'
    } else { Info 'No habia cambios de codigo nuevos.' }
    git push -q usb "HEAD:$rama"
    if ($LASTEXITCODE -ne 0) { Fallo 'No se pudo subir el codigo a la USB.' }
    Ok 'Codigo subido a la USB. Ya puedes sacar la USB.'
    exit 0
}

if (git status --porcelain) {
    Fallo "Hay cambios sin guardar en esta PC. Corre primero 'GUARDAR Y LLEVAR A USB' para no perderlos."
}
$existe = git rev-parse --verify --quiet "usb/$rama"
if (-not $existe) { Fallo 'La USB aun no tiene codigo. Corre primero GUARDAR en la otra PC.' }
git pull -q --ff-only usb $rama
if ($LASTEXITCODE -ne 0) { Fallo 'No se pudo actualizar el codigo (git pull). Revisa que esta PC no tenga commits que la USB no tenga.' }
Ok 'Codigo actualizado desde la USB.'

if (-not (Test-Path $dumpUsb)) { Info 'La USB no trae respaldo de base; no se importa nada.'; exit 0 }

New-Item -ItemType Directory -Force -Path $respaldos | Out-Null
$fechaDump = (Get-Item $dumpUsb).LastWriteTimeUtc.Ticks.ToString()
if ((Test-Path $marca) -and ((Get-Content $marca -Raw).Trim() -eq $fechaDump)) {
    Ok 'La base de esta PC ya esta al dia con la de la USB.'
    exit 0
}

Write-Host ''
Write-Host 'La USB trae una base mas nueva (usuarios, pedidos, precios y stock).' -ForegroundColor Yellow
Write-Host 'Importarla REEMPLAZA la base dat de esta PC. Antes se guarda un respaldo local.' -ForegroundColor Yellow
if ($Auto) { $resp = 'S' } else { $resp = Read-Host 'Importar la base de la USB? (S/N)' }
if ($resp -notmatch '^[sS]') { Info 'Base NO importada. El codigo si se actualizo.'; exit 0 }

$hayDat = & $mysql -u root -N -e "select count(*) from information_schema.schemata where schema_name='dat';"
if ($hayDat -eq '1') {
    $antes = Join-Path $respaldos ("dat_antes_{0}.sql" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
    & $mysqldump -u root --databases dat --add-drop-database --routines --default-character-set=utf8mb4 --result-file=$antes
    if ($LASTEXITCODE -ne 0) { Fallo 'No se pudo respaldar la base actual; no se importa nada.' }
    Ok "Respaldo local previo: $antes"
}

cmd /c "`"$mysql`" -u root --default-character-set=utf8mb4 < `"$dumpUsb`""
if ($LASTEXITCODE -ne 0) { Fallo 'La importacion fallo. Tu respaldo previo esta en la carpeta de respaldos.' }
Set-Content -Path $marca -Value $fechaDump -Encoding ASCII
Ok 'Base importada desde la USB.'
