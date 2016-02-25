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
        //echo $annotation;
        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);
        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', array_filter(array_map('trim', explode(';', (string) $annotation))));
        $synonymsContent .= "\n";
    }

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}