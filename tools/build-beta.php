<?php

$version = "release-29-beta-1";

$zipDir = sprintf('%s/tmp', __DIR__);
$zipFile = sprintf('%s/core-%s.zip', $zipDir, $version);
$extractDir = sprintf('%s/core-%s', $zipDir, $version);
$synonymsDir = realpath(sprintf('%s/../../', $zipDir));

// Get the tag
if (!file_exists($extractDir.'/annotations/root.xml')) {
    passthru('svn checkout http://unicode.org/repos/cldr/tags/'.$version.'/common '.$extractDir);
}

// Read
foreach (glob($extractDir."/annotations/*.xml") as $filename) {
    echo "Read $filename\n";

    $xml = simplexml_load_file($filename);
    $synonymsContent = '';
    $lang = (string) $xml->identity->language['type'];

    if ($lang === 'root') {
        continue;
    }

    foreach ($xml->annotations->children() as $annotation) {
        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $annotation = str_replace(': ', '; ', (string) $annotation); // See #6

        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode(';', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}

$regionalIndicatorSource = [
    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
];
$regionalIndicatorSymbol = [
    '🇦', '🇧', '🇨', '🇩', '🇪', '🇫', '🇬', '🇭', '🇮', '🇯', '🇰', '🇱', '🇲', '🇳', '🇴', '🇵', '🇶', '🇷', '🇸', '🇹', '🇺', '🇻', '🇼', '🇽', '🇾', '🇿'
];

// Add flags
foreach (glob($extractDir."/main/*.xml") as $filename) {
    echo "Read $filename\n";

    $lang = str_replace('.xml', '', basename($filename));
    if (!file_exists($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt')) {
        echo "No annotations for $lang, skip flags.\n";
        continue;
    }

    echo "Add flags for $lang.\n";

    $xml = simplexml_load_file($filename);

    $territories = [];

    foreach ($xml->localeDisplayNames->territories->children() as $territory) {
        $key = (string) $territory['type'];

        if ($territory['alt']) {
            continue;
        }

        if (is_numeric($key)) {
            continue; // No REGIONAL INDICATOR SYMBOL for numbers?! :(
        }

        $key = str_ireplace($regionalIndicatorSource, $regionalIndicatorSymbol, $key);

        $territories[$key] = $key . ' => '. $key .', '.mb_strtolower((string) $territory);
    }

    if (!file_exists($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt')) {
        echo "No annotations for $lang, skip flags.\n";
        continue;
    }

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', implode("\n", $territories), FILE_APPEND);
}

// Update license
passthru('svn export --force http://unicode.org/repos/cldr/tags/'.$version.'/unicode-license.txt '.$synonymsDir);