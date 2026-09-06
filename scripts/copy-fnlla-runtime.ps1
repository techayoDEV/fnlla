Set-StrictMode -Version 3.0
$ErrorActionPreference = 'Stop'

function Read-FnllaSvgDocument {
    param([string]$Path)
    $settings = New-Object System.Xml.XmlReaderSettings
    $settings.DtdProcessing = [System.Xml.DtdProcessing]::Prohibit
    $settings.XmlResolver = $null
    $reader = [System.Xml.XmlReader]::Create($Path, $settings)
    try {
        $document = New-Object System.Xml.XmlDocument
        $document.XmlResolver = $null
        $document.Load($reader)
    } finally { $reader.Dispose() }
    if ($document.DocumentElement.LocalName -ne 'svg' -or $document.DocumentElement.NamespaceURI -ne 'http://www.w3.org/2000/svg') {
        throw "Invalid SVG document: $Path"
    }
    return ,$document
}

function Write-FnllaIconSprite {
    param([string]$IconsPath, [string]$OutputPath)

    $svgFiles = @(Get-ChildItem -LiteralPath $IconsPath -File -Filter '*.svg' | Where-Object Name -ne 'sprite.svg' | Sort-Object Name)
    if ($svgFiles.Count -eq 0) {
        $existingSprite = Read-FnllaSvgDocument -Path (Join-Path $IconsPath 'sprite.svg')
        if ($existingSprite.SelectNodes('//*[local-name()="symbol"]').Count -eq 0) { throw 'Runtime sprite has no symbols.' }
        Copy-Item -LiteralPath (Join-Path $IconsPath 'sprite.svg') -Destination $OutputPath
        return
    }

    $spriteDocument = New-Object System.Xml.XmlDocument
    $spriteDocument.XmlResolver = $null
    $svgNamespace = 'http://www.w3.org/2000/svg'
    $svgRoot = $spriteDocument.CreateElement('svg', $svgNamespace)
    $null = $spriteDocument.AppendChild($svgRoot)
    $geometries = @{}
    foreach ($svgFile in $svgFiles) {
        $iconDocument = Read-FnllaSvgDocument -Path $svgFile.FullName
        $iconRoot = $iconDocument.DocumentElement
        if ($iconRoot.LocalName -ne 'svg' -or $iconRoot.NamespaceURI -ne $svgNamespace) {
            throw "Invalid SVG icon: $($svgFile.Name)"
        }
        $symbol = $spriteDocument.CreateElement('symbol', $svgNamespace)
        $symbol.SetAttribute('id', $svgFile.BaseName)
        foreach ($attribute in $iconRoot.Attributes) {
            if ($attribute.Name -notin @('xmlns', 'id', 'width', 'height', 'class')) {
                $null = $symbol.Attributes.Append($spriteDocument.ImportNode($attribute, $true))
            }
        }
        foreach ($child in $iconRoot.ChildNodes) {
            $null = $symbol.AppendChild($spriteDocument.ImportNode($child, $true))
        }
        # Exact duplicate symbols retain their public IDs but share geometry.
        $comparison = $symbol.CloneNode($true)
        $comparison.RemoveAttribute('id')
        $geometry = $comparison.OuterXml
        if ($geometries.ContainsKey($geometry)) {
            while ($symbol.HasChildNodes) { $null = $symbol.RemoveChild($symbol.FirstChild) }
            $reference = $spriteDocument.CreateElement('use', $svgNamespace)
            $reference.SetAttribute('href', '#' + $geometries[$geometry])
            $null = $symbol.AppendChild($reference)
        } else {
            $geometries[$geometry] = $svgFile.BaseName
        }
        $null = $svgRoot.AppendChild($symbol)
    }
    $writerSettings = New-Object System.Xml.XmlWriterSettings
    $writerSettings.Indent = $false
    $writerSettings.OmitXmlDeclaration = $true
    $writerSettings.Encoding = New-Object System.Text.UTF8Encoding($false)
    $writer = [System.Xml.XmlWriter]::Create($OutputPath, $writerSettings)
    try { $spriteDocument.Save($writer) } finally { $writer.Dispose() }
}

function Copy-FnllaUiRuntime {
    param([string]$SourceRuntimePath, [string]$ProjectRoot)

    $resolvedProject = [System.IO.Path]::GetFullPath($ProjectRoot).TrimEnd('\', '/')
    $destination = [System.IO.Path]::GetFullPath((Join-Path $resolvedProject 'public/vendor/fnlla-runtime'))
    $vendorDirectory = Split-Path -Path $destination -Parent
    $profilePath = Join-Path $resolvedProject '.fnlla/ui-distribution'
    $profile = if (Test-Path -LiteralPath $profilePath) { (Get-Content -LiteralPath $profilePath -Raw).Trim() } else { 'full' }
    if ($profile -notin @('sprite', 'full')) { throw "Unknown FNLLA UI distribution: $profile" }
    if (-not $destination.StartsWith($resolvedProject + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw 'Runtime destination is outside the project.'
    }
    foreach ($checkPath in @($resolvedProject, (Join-Path $resolvedProject 'public'), $vendorDirectory, $destination)) {
        $checkItem = Get-Item -LiteralPath $checkPath -Force -ErrorAction SilentlyContinue
        if ($null -ne $checkItem -and ($checkItem.Attributes -band [System.IO.FileAttributes]::ReparsePoint)) {
            throw "Runtime path must not be a reparse point: $checkPath"
        }
    }
    $null = New-Item -ItemType Directory -Path $vendorDirectory -Force
    $stage = Join-Path $vendorDirectory ('fnlla-runtime-stage-' + [guid]::NewGuid().ToString('N'))
    $backup = Join-Path $vendorDirectory ('fnlla-runtime-backup-' + [guid]::NewGuid().ToString('N'))
    $installed = $false
    $null = New-Item -ItemType Directory -Path $stage
    try {
        if ($profile -eq 'full') {
            Get-ChildItem -LiteralPath $SourceRuntimePath -Force | ForEach-Object {
                Copy-Item -LiteralPath $_.FullName -Destination $stage -Recurse -Force
            }
        } else {
            $files = @('LICENSE.md', 'MANIFEST.json', 'README.md', 'SUPPORT.md', 'TRADEMARKS.md', 'VERSION',
                'assets/css/fnlla-runtime.css', 'assets/js/fnlla-runtime.js',
                'assets/icons/LICENSE', 'assets/icons/NOTICE.md', 'assets/icons/README.md')
            foreach ($relativePath in $files) {
                $targetFile = Join-Path $stage $relativePath
                $null = New-Item -ItemType Directory -Path (Split-Path $targetFile -Parent) -Force
                Copy-Item -LiteralPath (Join-Path $SourceRuntimePath $relativePath) -Destination $targetFile
            }
            Write-FnllaIconSprite -IconsPath (Join-Path $SourceRuntimePath 'assets/icons') -OutputPath (Join-Path $stage 'assets/icons/sprite.svg')
        }
        foreach ($required in @('VERSION', 'MANIFEST.json', 'assets/css/fnlla-runtime.css', 'assets/js/fnlla-runtime.js', 'assets/icons/LICENSE')) {
            if (-not (Test-Path -LiteralPath (Join-Path $stage $required) -PathType Leaf)) { throw "Incomplete UI runtime: $required" }
        }
        if (Test-Path -LiteralPath $destination) { Move-Item -LiteralPath $destination -Destination $backup }
        try {
            Move-Item -LiteralPath $stage -Destination $destination
            $installed = $true
        } catch {
            if (Test-Path -LiteralPath $backup) { Move-Item -LiteralPath $backup -Destination $destination }
            throw
        }
    } finally {
        foreach ($temporaryPath in @($stage, $backup)) {
            if ($temporaryPath -eq $backup -and -not $installed) { continue }
            $resolvedTemporary = [System.IO.Path]::GetFullPath($temporaryPath)
            if ((Split-Path $resolvedTemporary -Parent) -ne $vendorDirectory) { throw 'Invalid runtime staging path.' }
            if (Test-Path -LiteralPath $resolvedTemporary) { Remove-Item -LiteralPath $resolvedTemporary -Recurse -Force }
        }
    }
}
