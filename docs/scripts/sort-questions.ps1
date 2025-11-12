# Sort siemens-questions.csv by Priority (Very High, High, Medium, Low) then Category
$csvPath = "docs\siemens-questions.csv"
if (-not (Test-Path $csvPath)) { Write-Error "File not found: $csvPath"; exit 1 }
# Import CSV (handles quoted fields)
$rows = Import-Csv -Path $csvPath -Encoding UTF8
# Normalize priority values (accept common variants) and map to sort order
$priorityOrder = @{
    'very high' = 1
    'very_high' = 1
    'very-high' = 1
    'high'      = 2
    'medium'    = 3
    'med'       = 3
    'm'         = 3
    'low'       = 4
}
# Add computed sort key (normalize input before lookup)
$rows | ForEach-Object {
    $p = $_.Priority -as [string]
    if ($p) {
        $pNorm = $p.Trim().ToLower().Replace(' ', '_').Replace('-', '_')
    }
    else {
        $pNorm = ''
    }
    if (-not $pNorm -or -not $priorityOrder.ContainsKey($pNorm)) { $rank = 99 } else { $rank = $priorityOrder[$pNorm] }
    $_ | Add-Member -NotePropertyName __SortPriority -NotePropertyValue $rank -Force
}
# Sort by priority then category
$sorted = $rows | Sort-Object -Property @{Expression = { $_.__SortPriority }; Ascending = $true }, @{Expression = { $_.Category }; Ascending = $true }
# Remove helper property
$sorted | ForEach-Object { $_.PSObject.Properties.Remove('__SortPriority') }
# Export back to CSV (preserve header order from original file)
$headerLine = Get-Content -Path $csvPath -TotalCount 1
# Export-Csv will write headers; use same headers as Import-Csv
$sorted | Export-Csv -Path $csvPath -NoTypeInformation -Encoding UTF8
Write-Output "Sorted $csvPath (by Priority then Category)."
