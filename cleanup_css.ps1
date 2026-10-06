$file = 'c:\xampp\htdocs\edusuivi\css\style.css'
$content = [System.IO.File]::ReadAllText($file)
$marker = '/* end of styles */'
$idx = $content.IndexOf($marker)
if ($idx -ge 0) { $content = $content.Substring(0, $idx) }
[System.IO.File]::WriteAllText($file, $content)
Write-Host "Done. File size: $($content.Length) chars"
