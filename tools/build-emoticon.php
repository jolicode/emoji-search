<?php

// Get the data
$data = json_decode(file_get_contents('https://github.com/wooorm/emoticon/raw/master/data/emoticons.json'), true);


$char_filters = [];

foreach($data as $emoticon) {
    foreach ($emoticon['emoticons'] as $face) {
        if (strpos($face, '=>')) {
            continue;
        }

        $char_filters[] = str_replace('\\', '\\\\', $face).'=>'.$emoticon['emoji'];
    }
}

echo json_encode($char_filters, JSON_UNESCAPED_UNICODE);