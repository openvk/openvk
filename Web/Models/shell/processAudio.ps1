$ovkRoot    = $args[0]
$storageDir = $args[1]
$fileHash   = $args[2]
$hashPart   = $fileHash.substring(0, 2)
$filename   = $args[3]
$audioFile  = [System.IO.Path]::GetTempFileName()
$temp       = [System.IO.Path]::GetTempFileName()

$keyID = $args[4]
$key   = $args[5]
$token = $args[6]
$seg   = $args[7]

$shell = Get-WmiObject Win32_process -filter "ProcessId = $PID"
$shell.SetPriority(16384) # because there's no "nice" program in Windows we just set a lower priority for entire tree

Remove-Item $temp
Remove-Item $audioFile
New-Item -ItemType "directory" $temp
New-Item -ItemType "directory" ("$temp/$fileHash" + '_fragments')
New-Item -ItemType "directory" ("$storageDir/$hashPart/$fileHash" + '_fragments')
Set-Location -Path $temp

Move-Item $filename $audioFile
ffmpeg -i $audioFile -f dash -encryption_scheme cenc-aes-ctr -encryption_key $key `
    -encryption_kid $keyID -map 0:a -vn -c:a aac -ar 44100 -seg_duration $seg `
    -use_timeline 1 -use_template 1 -init_seg_name ($fileHash + '_fragments/0_0.$ext$') `
    -media_seg_name ($fileHash + '_fragments/chunk$Number%06d$_$RepresentationID$.$ext$') -adaptation_sets 'id=0,streams=a' `
    "$fileHash.mpd"

ffmpeg -i $audioFile -vn -ar 44100 "original_$token.mp3"
Move-Item "original_$token.mp3" ($fileHash + '_fragments')

Get-ChildItem -Path ($fileHash + '_fragments/*') | Move-Item -Destination ("$storageDir/$hashPart/$fileHash" + '_fragments')
Move-Item -Path ("$fileHash.mpd") -Destination "$storageDir/$hashPart"

cd ..
Remove-Item -Recurse $temp
Remove-Item $audioFile
