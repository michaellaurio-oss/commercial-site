<#
Deploy the site to the web host over FTPS (encrypted FTP).

  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1            upload changed files in public\
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -DryRun    list what would be uploaded
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Full      upload everything again
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Backup    download the live database + photos to backups\
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Restore 20261103-1130
        put the live site's data back exactly as it was in backups\20261103-1130\: database, listing
        photos, home page pictures, stock photos (files added since are deleted). Takes a
        "before-restore" backup of the current state first and asks you to type RESTORE.
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -InitDb    first deploy only: upload deploy\live-initial.db
  powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Config    upload deploy\config.local.php (admin password etc.)

Never uploads public\data\ (the live database), public\photos\ (uploaded photos) or
public\app\config.local.php. Reads FTP_HOST / FTP_USER / FTP_PASSWORD from .env.
#>
param(
    [switch]$DryRun,
    [switch]$Full,
    [switch]$Backup,
    [switch]$InitDb,
    [switch]$Config,
    # A folder name under backups\ (or a full path) made by -Backup.
    [string]$Restore,
    # The FTP server's TLS certificate is issued to this name (same server as ftp.the-laurios.com).
    [string]$TlsHost = 'svr201.fastwebhost.com'
)
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$public = Join-Path $root 'public'
$state = Join-Path $PSScriptRoot '.deploy-state.json'
$curl = Join-Path $env:SystemRoot 'System32\curl.exe'

# ---- credentials (.env) -> temporary netrc so the password never appears on a command line ----
$envFile = Join-Path $root '.env'
if (-not (Test-Path $envFile)) { throw '.env not found' }
$cfg = @{}
foreach ($line in Get-Content $envFile) {
    if ($line -match '^\s*([A-Z_]+)\s*=\s*(.*?)\s*$') { $cfg[$matches[1]] = $matches[2].Trim('"') }
}
foreach ($k in 'FTP_USER', 'FTP_PASSWORD') { if (-not $cfg[$k]) { throw "$k missing from .env" } }
$netrc = Join-Path $env:TEMP ('gunsite-' + [guid]::NewGuid().ToString('N') + '.netrc')
Set-Content -Path $netrc -Encoding ascii -Value ("machine $TlsHost login " + $cfg['FTP_USER'] + ' password ' + $cfg['FTP_PASSWORD'])
$base = "ftp://$TlsHost/"
# --speed-limit/--speed-time: a transfer that moves nothing for 60 s is dropped and retried. Pure-FTPd
# sometimes leaves a data connection hanging, and without this curl waits forever (2026-11-03).
# --retry-all-errors: also retry when the host resets the connection (curl exit 56), which --retry alone doesn't.
$common = @('--ssl-reqd', '--netrc-file', $netrc, '--silent', '--show-error', '--retry', '3', '--retry-delay', '3', '--retry-all-errors', '--connect-timeout', '30', '--speed-limit', '1', '--speed-time', '60')

function Invoke-Curl([string[]]$curlArgs) {
    & $curl @common @curlArgs
    if ($LASTEXITCODE -ne 0) { throw "curl failed (exit $LASTEXITCODE)" }
}
function Get-RemoteList([string]$dir) {
    $out = & $curl @common '--list-only' ($base + $dir) 2>$null
    if ($LASTEXITCODE -ne 0) { return @() }
    return @($out | Where-Object { $_ -and $_ -ne '.' -and $_ -ne '..' })
}

# Name -> size of the files (and folders, with -Dirs) in a remote folder, from the FTP LIST output.
function Get-RemoteSizes([string]$dir, [switch]$Dirs) {
    $out = & $curl @common ($base + $dir) 2>$null
    $map = @{}
    if ($LASTEXITCODE -ne 0) { return $map }
    foreach ($line in $out) {
        if ($line -match '^(?<t>[-dl])\S*\s+\d+\s+\S+\s+\S+\s+(?<size>\d+)\s+\w{3}\s+\d+\s+[\d:]+\s+(?<name>.+)$') {
            $name = $matches['name']
            if ($name -eq '.' -or $name -eq '..' -or $name -like '.*') { continue }
            if (($matches['t'] -eq 'd') -eq [bool]$Dirs) { $map[$name] = [long]$matches['size'] }
        }
    }
    return $map
}
# Upload one file and check the size on the server afterwards (3 tries, like the normal deploy).
function Send-File([string]$localPath, [string]$rel) {
    $size = (Get-Item -LiteralPath $localPath -Force).Length
    for ($try = 1; $try -le 3; $try++) {
        & $curl @common '--ftp-create-dirs' '-T' $localPath ($base + $rel) 2>$null
        if ($LASTEXITCODE -eq 0) {
            $head = & $curl @common '--head' ($base + $rel) 2>$null
            if ($head | Where-Object { $_ -match "^Content-Length:\s*$size\s*$" }) { return }
        }
        Start-Sleep -Seconds 2
    }
    throw "Upload failed: $rel. Run the restore again."
}
# Delete remote files (paths from the FTP home), 30 per connection.
function Remove-Remote([string[]]$paths, [string]$cmd = 'DELE') {
    for ($i = 0; $i -lt $paths.Count; $i += 30) {
        $q = @()
        foreach ($p in $paths[$i..([Math]::Min($i + 29, $paths.Count - 1))]) { $q += @('-Q', "$cmd $p") }
        Invoke-Curl (@('--list-only', '-o', 'NUL') + $q + @($base))
    }
}
# Make a remote folder hold exactly the files of a local folder (dot files such as .htaccess are left alone).
function Sync-Folder([string]$localDir, [string]$remoteDir) {
    $remote = Get-RemoteSizes $remoteDir
    $local = @{}
    if (Test-Path $localDir) { Get-ChildItem -LiteralPath $localDir -File | Where-Object { $_.Name -notlike '.*' } | ForEach-Object { $local[$_.Name] = $_ } }
    $gone = @($remote.Keys | Where-Object { -not $local.ContainsKey($_) } | ForEach-Object { $remoteDir + $_ })
    if ($gone.Count) { Remove-Remote $gone; Write-Host ("  {0}: deleted {1} file(s) added since the backup" -f $remoteDir, $gone.Count) }
    $sent = 0
    foreach ($name in $local.Keys) {
        if ($remote[$name] -ne $local[$name].Length) { Send-File $local[$name].FullName ($remoteDir + $name); $sent++ }
    }
    if ($sent) { Write-Host ("  {0}: put back {1} file(s)" -f $remoteDir, $sent) }
}

function Save-Backup([string]$dest) {
        New-Item -ItemType Directory -Force (Join-Path $dest 'photos') | Out-Null
        Write-Host "Backing up to $dest"
        Invoke-Curl @('-o', (Join-Path $dest 'gun_specs.db'), ($base + 'data/gun_specs.db'))
        $n = 0
        foreach ($folder in Get-RemoteList 'photos/') {
            if ($folder -like '.*') { continue }
            $local = Join-Path $dest "photos\$folder"
            New-Item -ItemType Directory -Force $local | Out-Null
            $pairs = @()
            foreach ($f in Get-RemoteList "photos/$folder/") { $pairs += @('-o', (Join-Path $local $f), ($base + "photos/$folder/$f")); $n++ }
            if ($pairs.Count) { Invoke-Curl $pairs }
        }
        # Home page pictures (Admin > Home page): not in git, so they are backed up here too.
        $heroDir = Join-Path $dest 'hero'
        New-Item -ItemType Directory -Force $heroDir | Out-Null
        $pairs = @()
        foreach ($f in Get-RemoteList 'assets/hero/') { if ($f -notlike '.*') { $pairs += @('-o', (Join-Path $heroDir $f), ($base + "assets/hero/$f")); $n++ } }
        if ($pairs.Count) { Invoke-Curl $pairs }
        # Manufacturer stock photos (not in git).
        $stockDir = Join-Path $dest 'stock-photos'
        New-Item -ItemType Directory -Force $stockDir | Out-Null
        # In batches of 30 files: hundreds of -o arguments at once exceed the Windows command-line length.
        $pairs = @()
        foreach ($f in Get-RemoteList 'stock-photos/') {
            if ($f -like '.*') { continue }
            $pairs += @('-o', (Join-Path $stockDir $f), ($base + "stock-photos/$f")); $n++
            if ($pairs.Count -ge 60) { Invoke-Curl $pairs; $pairs = @() }
        }
        if ($pairs.Count) { Invoke-Curl $pairs }
        # -Restore only accepts a backup that got this far (a stalled or cancelled one has no marker).
        Set-Content -Path (Join-Path $dest 'backup-complete.txt') -Encoding ascii -Value ("Finished " + (Get-Date -Format 's') + ", $n files")
        Write-Host "Done: database + $n files (listing photos, home page pictures, stock photos)."
}

try {
    if ($Backup) {
        Save-Backup (Join-Path $root ('backups\' + (Get-Date -Format 'yyyyMMdd-HHmm')))
        return
    }

    if ($Restore) {
        $src = $Restore
        if (-not [System.IO.Path]::IsPathRooted($src)) { $src = Join-Path $root ('backups\' + $src) }
        $db = Join-Path $src 'gun_specs.db'
        if (-not (Test-Path $db)) { throw "No gun_specs.db in $src" }
        $fs = [System.IO.File]::OpenRead($db); $hdr = New-Object byte[] 16; [void]$fs.Read($hdr, 0, 16); $fs.Close()
        if ([System.Text.Encoding]::ASCII.GetString($hdr, 0, 15) -ne 'SQLite format 3') { throw "$db is not a SQLite database" }
        if (-not (Test-Path (Join-Path $src 'backup-complete.txt'))) { throw "$src is not a finished backup (no backup-complete.txt). Pick another, or run -Backup again." }
        Write-Host "This puts the LIVE site's database, listing photos, home page pictures and stock photos"
        Write-Host "back to $src. Everything changed in the admin since then is lost (listings, accounts,"
        Write-Host "messages, activity log). A backup of the current state is taken first."
        if ((Read-Host 'Type RESTORE to go ahead') -cne 'RESTORE') { Write-Host 'Cancelled.'; return }
        # As in the normal deploy: with 'Stop', curl's stderr would abort Windows PowerShell 5.1
        # instead of retrying. Failures are still caught through curl's exit code.
        $ErrorActionPreference = 'Continue'

        Save-Backup (Join-Path $root ('backups\' + (Get-Date -Format 'yyyyMMdd-HHmm') + '-before-restore'))

        # Database: upload under a temporary name, then rename over the live file so no visitor ever
        # reads a half-uploaded database. A leftover rollback journal would be applied to the restored
        # file and corrupt it, so it is removed too.
        Write-Host 'Restoring the database...'
        Send-File $db 'data/gun_specs.restoring'
        & $curl @common '--list-only' '-o' 'NUL' '-Q' 'RNFR data/gun_specs.restoring' '-Q' 'RNTO data/gun_specs.db' $base 2>$null
        if ($LASTEXITCODE -ne 0) {
            # The server would not rename over an existing file: upload straight over it instead.
            Send-File $db 'data/gun_specs.db'
            Remove-Remote @('data/gun_specs.restoring')
        }
        & $curl @common '--list-only' '-o' 'NUL' '-Q' '*DELE data/gun_specs.db-journal' $base 2>$null
        $head = & $curl @common '--head' ($base + 'data/gun_specs.db') 2>$null
        $size = (Get-Item -LiteralPath $db).Length
        if (-not ($head | Where-Object { $_ -match "^Content-Length:\s*$size\s*$" })) { throw 'The live database size does not match the backup. Run the restore again.' }

        Write-Host 'Restoring listing photos...'
        $localPhotos = Join-Path $src 'photos'
        $keepDirs = @{}
        Get-ChildItem -LiteralPath $localPhotos -Directory | ForEach-Object { $keepDirs[$_.Name] = $true }
        foreach ($dir in (Get-RemoteSizes 'photos/' -Dirs).Keys) {
            if ($keepDirs.ContainsKey($dir)) { continue }
            $files = @((Get-RemoteSizes "photos/$dir/").Keys | ForEach-Object { "photos/$dir/$_" })
            if ($files.Count) { Remove-Remote $files }
            Remove-Remote @("photos/$dir") 'RMD'
            Write-Host "  photos/: removed $dir (added since the backup)"
        }
        foreach ($dir in $keepDirs.Keys) { Sync-Folder (Join-Path $localPhotos $dir) "photos/$dir/" }

        Write-Host 'Restoring home page pictures...'
        Sync-Folder (Join-Path $src 'hero') 'assets/hero/'
        Write-Host 'Restoring stock photos...'
        Sync-Folder (Join-Path $src 'stock-photos') 'stock-photos/'
        Write-Host "Restore complete. Anyone still signed in to the admin with an account that no longer exists is signed out."
        return
    }

    if ($InitDb) {
        $db = Join-Path $PSScriptRoot 'live-initial.db'
        if (-not (Test-Path $db)) { throw 'deploy\live-initial.db not found. Build it with: php dev/build_db.php real deploy/live-initial.db' }
        if ((Get-RemoteList 'data/') -contains 'gun_specs.db') { throw 'The server already has data/gun_specs.db. Refusing to overwrite the live database.' }
        Invoke-Curl @('--ftp-create-dirs', '-T', $db, ($base + 'data/gun_specs.db'))
        Write-Host 'Uploaded the initial database.'
        return
    }

    if ($Config) {
        $file = Join-Path $PSScriptRoot 'config.local.php'
        if (-not (Test-Path $file)) { throw 'deploy\config.local.php not found. Create it with: php -S 127.0.0.1:8765 deploy/password-tool.php' }
        Invoke-Curl @('--ftp-create-dirs', '-T', $file, ($base + 'app/config.local.php'))
        Write-Host 'Uploaded app/config.local.php.'
        return
    }

    # ---- normal deploy: changed files only ----
    $old = @{}
    if ((Test-Path $state) -and -not $Full) {
        (Get-Content $state -Raw | ConvertFrom-Json).PSObject.Properties | ForEach-Object { $old[$_.Name] = $_.Value }
    }
    $new = @{}
    $todo = @()
    foreach ($f in Get-ChildItem $public -Recurse -File -Force) {
        $rel = $f.FullName.Substring($public.Length + 1).Replace('\', '/')
        if ($rel -match '^data/' -and $rel -ne 'data/.htaccess') { continue }
        if (($rel -match '^photos/' -and $rel -ne 'photos/.htaccess') -or $rel -match '^assets/demo/') { continue }
        if ($rel -eq 'app/config.local.php' -or $rel -match '^zz-') { continue }
        $hash = (Get-FileHash $f.FullName -Algorithm SHA256).Hash
        $new[$rel] = $hash
        if ($old[$rel] -ne $hash) { $todo += , @($f.FullName, $rel) }
    }
    if (-not $todo.Count) { Write-Host 'Nothing changed.'; return }
    Write-Host ("{0} file(s) to upload:" -f $todo.Count)
    $todo | ForEach-Object { Write-Host ('  ' + $_[1]) }
    if ($DryRun) { return }

    # One file per curl call so every upload is checked; the server size must match afterwards.
    # 'Continue' here: with 'Stop', curl's stderr (e.g. a timeout) aborted the whole deploy in
    # Windows PowerShell 5.1 instead of retrying, and the progress below wasn't saved.
    $ErrorActionPreference = 'Continue'
    $failed = @()
    foreach ($t in $todo) {
        $size = (Get-Item -LiteralPath $t[0] -Force).Length
        $ok = $false
        for ($try = 1; $try -le 3 -and -not $ok; $try++) {
            & $curl @common '--ftp-create-dirs' '-T' $t[0] ($base + $t[1]) 2>$null
            if ($LASTEXITCODE -eq 0) {
                $head = & $curl @common '--head' ($base + $t[1]) 2>$null
                $ok = [bool]($head | Where-Object { $_ -match "^Content-Length:\s*$size\s*$" })
            }
            if (-not $ok) { Start-Sleep -Seconds 2 }
        }
        if ($ok) { $old[$t[1]] = $new[$t[1]]; Write-Host ('  uploaded ' + $t[1]) }
        else { $failed += $t[1]; Write-Host ('  FAILED   ' + $t[1]) -ForegroundColor Red }
    }
    # Remember what's on the server so the next run only sends changes (failed files are retried).
    $keep = @{}
    foreach ($k in $new.Keys) { if ($old.ContainsKey($k)) { $keep[$k] = $old[$k] } }
    $keep | ConvertTo-Json | Set-Content -Path $state -Encoding ascii
    if ($failed.Count) { throw ("{0} file(s) failed to upload. Run the deploy again." -f $failed.Count) }
    Write-Host 'Deploy complete.'
}
finally {
    Remove-Item $netrc -Force -ErrorAction SilentlyContinue
}
