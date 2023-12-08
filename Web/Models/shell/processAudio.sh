ovkRoot=$1
storageDir=$2
fileHash=$3
hashPart=$(echo $fileHash | cut -c1-2)
filename=$4
audioFile=$(mktemp)
temp=$(mktemp -d)

keyID=$5
key=$6
token=$7
seg=$8

trap 'rm -f "$temp" "$audioFile"' EXIT

mkdir -p "$temp/$fileHash"_fragments
mkdir -p "$storageDir/$hashPart/$fileHash"_fragments
cd "$temp"

mv "$filename" "$audioFile"
ffmpeg -i "$audioFile" -f dash -encryption_scheme cenc-aes-ctr -encryption_key "$key" \
    -encryption_kid "$keyID" -map 0 -vn -c:a aac -ar 44100 -seg_duration "$seg" \
    -use_timeline 1 -use_template 1 -init_seg_name "$fileHash"_fragments/0_0."\$ext\$" \
    -media_seg_name "$fileHash"_fragments/chunk"\$Number"%06d\$_"\$RepresentationID\$"."\$ext\$" -adaptation_sets 'id=0,streams=a' \
    "$fileHash.mpd"

ffmpeg -i "$audioFile" -vn -ar 44100 "original_$token.mp3"
mv "original_$token.mp3" "$fileHash"_fragments

mv "$fileHash"_fragments "$storageDir/$hashPart"
mv "$fileHash.mpd" "$storageDir/$hashPart"

cd ..
rm -rf "$temp"
rm -f "$audioFile"
