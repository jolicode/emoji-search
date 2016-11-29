<?php

// Get the data
$data = json_decode(file_get_contents('https://github.com/wooorm/emoticon/raw/master/index.json'), true);

$outputDir = realpath(sprintf('%s/../', __DIR__));

$char_filters = [];

foreach($data as $emoticon) {
    foreach ($emoticon['emoticons'] as $face) {
        if (strpos($face, '=>')) {
            continue;
        }

        $char_filters[] = str_replace('\\', '\\\\', $face).'=>'.$emoticon['emoji'];
    }
}

file_put_contents($outputDir.'/emoticons.txt', implode("\n", $char_filters));

echo "emoticons.txt updated.\n";
