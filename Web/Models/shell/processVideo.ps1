$ovkRoot = $args[0]
$file    = $args[1]
$dir     = $args[2]
$hash    = $args[3]
$hashT   = $hash.substring(0, 2)
$temp    = [System.IO.Path]::GetTempFileName()
$temp2   = [System.IO.Path]::GetTempFileName()

$shell = Get-WmiObject Win32_process -filter "ProcessId = $PID"
$shell.SetPriority(16384)

Remove-Item $temp
Move-Item $file $temp

# video stub logic was implicitly deprecated, so we start processing at once
ffmpeg -i $temp -ss 00:00:01.000 -y -vframes 1 "$dir$hashT/$hash.gif"
ffmpeg -i $temp -c:v libx264 -f mp4 -q:v 7 -c:a libmp3lame -q:a 4 -tune zerolatency -vf "scale=640:480:force_original_aspect_ratio=decrease,pad=640:480:(ow-iw)/2:(oh-ih)/2,setsar=1" -y $temp2

Move-Item $temp2 "$dir$hashT/$hash.mp4" -Force
Remove-Item $temp2
