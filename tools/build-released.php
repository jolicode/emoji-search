<?php

// http://cldr.unicode.org/
// http://site.icu-project.org/download
// Should match the ICU plugin version
$version = "35";

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

function isEmojiNRK(SimpleXMLElement $annotation)
{
    // To remove as not understood by ES.
    foreach (["\u{3012}", "\u{00A9}", "\u{00AE}", "\u{2122}", "\u{3030}", "\u{303D}",] as $emoji) {
        if (mb_strpos((string) $annotation . (string) $annotation['cp'], $emoji) !== false) {
            return true;
        }
    }

    return false;
}

function annotationXmlToSynonyms(SimpleXMLElement $xml)
{
    $synonymsContent = '';

    foreach ($xml->annotations->children() as $annotation) {
        if ((string) $annotation['type'] === 'tts') { // Ignore Text To Speech
            continue;
        }

        if (isEmojiNRK($annotation)) {
            echo "Bypass ".(string) $annotation['cp'].", spotted as NRK.\n";
            continue;
        }

        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode('|', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    return $synonymsContent;
}

function derivedAnnotationXmlToSynonyms(SimpleXMLElement $xml)
{
    $synonymsContent = '';

    foreach ($xml->annotations->children() as $annotation) {
        $codePoint = mb_ord((string) $annotation['cp']);
        $isFlag = $codePoint <= 127487 && $codePoint >= 127462;
        $isKeycap = mb_strpos((string) $annotation['cp'], "\u{20E3}") !== false;

        //echo (string) $annotation['cp'];
        //echo $isFlag ? ' is flag': ' is no';
        //echo "\n";

        // TTS but not a flag or keycap, skip
        if ((string) $annotation['type'] === 'tts' && !$isFlag && !$isKeycap) {
            continue;
        }

        if (($isFlag || $isKeycap) && (string) $annotation['type'] !== 'tts') {
            continue;
        }

        if (isEmojiNRK($annotation)) {
            echo "Bypass ".(string) $annotation['cp'].", spotted as NRK.\n";
            continue;
        }

        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode('|', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    return $synonymsContent;
}

// Remove old versions
foreach(glob($synonymsDir.'/*.txt') as $file) {
    unlink($file);
}

// Read emoji annotations
foreach (glob($extractDir."/common/annotations/*.xml") as $filename) {
    echo "Read $filename\n";

    $lang = basename($filename, ".xml");

    if ($lang === 'root') {
        continue;
    }

    $synonymsContent = annotationXmlToSynonyms(simplexml_load_file($filename));

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}

// Read emoji derived annotations
foreach (glob($extractDir."/common/annotationsDerived/*.xml") as $filename) {
    echo "Read $filename\n";

    $lang = basename($filename, ".xml");
    if (!file_exists($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt')) {
        echo "No annotations for $lang, skip derived.\n";
        continue;
    }

    $synonymsContent = derivedAnnotationXmlToSynonyms(simplexml_load_file($filename));

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent, FILE_APPEND);
}
