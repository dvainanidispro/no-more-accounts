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

	$zipPath = Join-Path $distDir ("$pluginSlug.zip")
	if (Test-Path $zipPath) {
		Remove-Item $zipPath -Force
	}

	# Δεν χρησιμοποιούμε Compress-Archive: στο Windows PowerShell 5.1 γράφει τα
	# entries με backslash ('admin\notices.php'), οπότε το zip αποσυμπιέζεται
	# λάθος σε Linux (όλα flat, με το '\' μέσα στο όνομα αρχείου). Φτιάχνουμε
	# το zip με το .NET ZipArchive και ρητά forward slashes στα ονόματα.
	Add-Type -AssemblyName System.IO.Compression
	Add-Type -AssemblyName System.IO.Compression.FileSystem

	# Το $env:TEMP μπορεί να είναι σε μορφή short path (π.χ. VAINAN~1), ενώ το
	# Get-ChildItem επιστρέφει πλήρη ονόματα - παίρνουμε το resolved path ώστε
	# το Substring παρακάτω να κόβει στο σωστό σημείο.
	$stageRootResolved = (Get-Item -LiteralPath $stageRoot).FullName

	$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
	try {
		Get-ChildItem -Path $stageDir -Recurse -File | ForEach-Object {
			# Διαδρομή σχετική ως προς το stageRoot, ώστε τα αρχεία να μπουν
			# μέσα σε φάκελο "no-more-accounts/" όπως τον περιμένει το WordPress.
			$entryName = $_.FullName.Substring($stageRootResolved.Length + 1) -replace '\\', '/'
			[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
				$zip, $_.FullName, $entryName,
				[System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
		}
	}
	finally {
		$zip.Dispose()
	}

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
