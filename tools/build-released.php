<?php

$version = "28"; // Version <= 28 is not good... don't use it

$zipDir = sprintf('%s/tmp', __DIR__);
$zipFile = sprintf('%s/core-%s.zip', $zipDir, $version);
$extractDir = sprintf('%s/core-%s', $zipDir, $version);
$synonymsDir = realpath(sprintf('%s/../../', $zipDir));

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

// Read
foreach (glob($extractDir."/common/annotations/*.xml") as $filename) {
    echo "Read $filename\n";

    $xml = simplexml_load_file($filename);
    $synonymsContent = '';
    $lang = (string) $xml->identity->language['type'];

    if ($lang === 'root') {
        continue;
    }

    foreach ($xml->annotations->children() as $annotation) {
        //echo $annotation;
        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode(';', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}