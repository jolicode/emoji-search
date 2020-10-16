<?php

// http://cldr.unicode.org/
// http://site.icu-project.org/download
// Should match the ICU plugin version
$version = "35";

$zipDir = sprintf('%s/tmp', __DIR__);
$zipFile = sprintf('%s/core-%s.zip', $zipDir, $version);
$extractDir = sprintf('%s/core-%s', $zipDir, $version);
$synonymsDir = realpath(sprintf('%s/../../synonyms/', $zipDir));

CONST COMPLETELY_ELIMINATED_BY_ANALYZER = [
    '002A' => '*',
    'FF03' => '＃',
    '002B' => '+',
    '002F' => '/',
    '002D' => '-',
    '2212' => '−',
    '2013' => '–',
    '00F7' => '÷',
    '0021' => '!',
    'FF01' => '！',
    'FF1F' => '？',
    '0021_double' => '!!',
    'FF01_double' => '！！',
    '003F' => '?',
    '0021_003F' => '!?',
    'FF01_FF1F' => '！？',
    '061F' => '؟',
    '061F_0021' => '؟!',
    '204A_0025' => '⁊%',
    '2713' => '✓',
    '00D7' => '×',
    // removed with icu_tokenizer
    '303D' => '〽',
    '3030' => '〰',
    '00A9' => '©',
    '00AE' => '®',
    '2122' => '™',
];

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

function filterSynonyms($synonyms)
{
    // Remove the obvious
    $synonyms = array_filter($synonyms, function ($synonym) {
        if (in_array($synonym, COMPLETELY_ELIMINATED_BY_ANALYZER)) {
            echo "Bypass synonym $synonym, spotted as COMPLETELY_ELIMINATED_BY_ANALYZER.\n";
            return false;
        }

        return true;
    });

    // More cleaning for complex string like "sleutelbordtoets: *"
    // Or "* réiltín"
    array_walk($synonyms, function(&$synonym) {
        // Replace 202F NARROW NO-BREAK SPACE
        $synonym = str_replace(" ", ' ', $synonym);

        $withSpaceBefore = array_map(function ($a) {
            return ' '.$a;
        }, COMPLETELY_ELIMINATED_BY_ANALYZER);

        $cleanString = str_replace($withSpaceBefore, ' ', $synonym);
        if ($cleanString !== $synonym) {
            echo "Edit synonym $synonym, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
            $synonym = $cleanString;
        }

        $withSpaceAfter = array_map(function ($a) {
            return $a.' ';
        }, COMPLETELY_ELIMINATED_BY_ANALYZER);

        $cleanString = str_replace($withSpaceAfter, ' ', $synonym);
        if ($cleanString !== $synonym) {
            echo "Edit synonym $synonym, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
            $synonym = $cleanString;
        }
    });

    return $synonyms;
}

function filterEmoji($emoji)
{

    $cleanString = str_replace(COMPLETELY_ELIMINATED_BY_ANALYZER, '', $emoji);
    if ($cleanString !== $emoji) {
        echo "Bypass line $emoji, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
        return true;
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

        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);

        if (filterEmoji($emoji)) {
            continue;
        }

        $synonyms = array_filter(array_map('trim', explode('|', (string) $annotation)));
        $synonyms = filterSynonyms($synonyms);

        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', $synonyms);
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

        $emoji = str_replace(['[', ']', '{', '}'], '', (string) $annotation['cp']);

        if (filterEmoji($emoji)) {
            continue;
        }

        $synonyms = array_filter(array_map('trim', explode('|', (string) $annotation)));
        $synonyms = filterSynonyms($synonyms);

        if (strpos(implode(', ', $synonyms), '*')) {
            var_dump($synonyms, $emoji);die();
        }

        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', $synonyms);
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
