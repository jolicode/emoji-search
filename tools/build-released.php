<?php

$version = "31.0.1";

$zipDir = sprintf('%s/tmp', __DIR__);
$zipFile = sprintf('%s/core-%s.zip', $zipDir, $version);
$extractDir = sprintf('%s/core-%s', $zipDir, $version);
$synonymsDir = realpath(sprintf('%s/../../synonyms/', $zipDir));

// Get the ZIP
if (!file_exists($zipFile)) {
    echo "Download\n";
    $url = sprintf("http://unicode.org/Public/cldr/%s/core.zip", $version);
    if (!is_dir($zipDir)) {
        mkdir($zipDir);
    }

    $zipResource = fopen($zipFile, "w");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_FILE, $zipResource);

    $page = curl_exec($ch);
    if(!$page) {
        throw new \Exception(curl_error($ch));
    }
    curl_close($ch);
    fclose($zipResource);
}

// Extract
if (!file_exists($extractDir.'/apache-license.txt')) {
    echo "Extract\n";
    $zip = new ZipArchive();
    if($zip->open($zipFile) !== true) {
        throw new \Exception("Error while opening the Zip.");
    }
    $zip->extractTo($extractDir);
    $zip->close();
}

// Read emoji
foreach (glob($extractDir."/common/annotations/*.xml") as $filename) {
    echo "Read $filename\n";

    $xml = simplexml_load_file($filename);
    $synonymsContent = '';
    $lang = (string) $xml->identity->language['type'];

    if ($lang === 'root') {
        continue;
    }

    // For now, ignore the territory improvements (FIXME)
    if ((string) $xml->identity->territory['type']) {
        continue;
    }

    foreach ($xml->annotations->children() as $annotation) {
        if ((string) $annotation['type'] === 'tts') {
            continue;
        }

        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode('|', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}

// Build flags
$regionalIndicatorSource = [
    'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'
];
$regionalIndicatorSymbol = [
    '🇦', '🇧', '🇨', '🇩', '🇪', '🇫', '🇬', '🇭', '🇮', '🇯', '🇰', '🇱', '🇲', '🇳', '🇴', '🇵', '🇶', '🇷', '🇸', '🇹', '🇺', '🇻', '🇼', '🇽', '🇾', '🇿'
];

// Add flags
foreach (glob($extractDir."/common/main/*.xml") as $filename) {
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
