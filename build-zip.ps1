# build-zip.ps1
#
# Δημιουργεί το zip εγκατάστασης του plugin για το WordPress στον υποφάκελο dist/.
# Εκτέλεση: δεξί κλικ -> "Run with PowerShell" (ή: powershell -File build-zip.ps1 -NoPause)
#
# Όλες οι διαδρομές βασίζονται στο $PSScriptRoot (τον φάκελο του script),
# ώστε να μην έχει σημασία το working directory με το οποίο θα τρέξει.

param(
	[switch]$NoPause
)

$ErrorActionPreference = 'Stop'

$pluginSlug = 'no-more-accounts'
$root       = $PSScriptRoot
$distDir    = Join-Path $root 'dist'
$stageRoot  = Join-Path $env:TEMP ("$pluginSlug-build-" + [guid]::NewGuid().ToString('N'))
$stageDir   = Join-Path $stageRoot $pluginSlug

# Μόνο ό,τι χρειάζεται το εγκατεστημένο plugin (whitelist).
$include = @(
	'no-more-accounts.php',
	'uninstall.php',
	'README.md',
	'documentation.md',
	'includes',
	'admin'
)

try {
	# Διαβάζουμε την έκδοση από το header του κύριου αρχείου για το όνομα του zip.
	$mainFile = Join-Path $root 'no-more-accounts.php'
	$version  = 'unknown'
	$match    = Select-String -Path $mainFile -Pattern 'Version:\s*([0-9][^\s]*)' | Select-Object -First 1
	if ($match) {
		$version = $match.Matches[0].Groups[1].Value
	}

	# dist/ (δημιουργείται αν δεν υπάρχει - είναι στο .gitignore).
	if (-not (Test-Path $distDir)) {
		New-Item -ItemType Directory -Path $distDir | Out-Null
	}

	# Προσωρινός φάκελος staging: το zip πρέπει να περιέχει τα αρχεία
	# μέσα σε φάκελο "no-more-accounts/", όπως τον περιμένει το WordPress.
	New-Item -ItemType Directory -Path $stageDir -Force | Out-Null

	foreach ($item in $include) {
		$source = Join-Path $root $item
		if (-not (Test-Path $source)) {
			throw "Δεν βρέθηκε το '$item' - ματαίωση."
		}
		Copy-Item -Path $source -Destination $stageDir -Recurse
	}

	$zipPath = Join-Path $distDir ("$pluginSlug-$version.zip")
	if (Test-Path $zipPath) {
		Remove-Item $zipPath -Force
	}

	Compress-Archive -Path $stageDir -DestinationPath $zipPath

	Write-Host ''
	Write-Host "OK - Δημιουργήθηκε: $zipPath" -ForegroundColor Green
}
catch {
	Write-Host ''
	Write-Host "Σφάλμα: $_" -ForegroundColor Red
}
finally {
	# Καθαρισμός του staging φακέλου.
	if (Test-Path $stageRoot) {
		Remove-Item $stageRoot -Recurse -Force
	}
}

if (-not $NoPause) {
	Read-Host 'Πατήστε Enter για κλείσιμο'
}
