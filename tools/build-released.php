<?php

use Symfony\Component\HttpClient\HttpClient;

include "vendor/autoload.php";

// http://cldr.unicode.org/
// http://site.icu-project.org/download
$version = "46.1";

$zipDir = sprintf('%s/tmp', __DIR__);
$zipFile = sprintf('%s/core-%s.zip', $zipDir, $version);
$extractDir = sprintf('%s/core-%s', $zipDir, $version);
$synonymsDir = realpath(sprintf('%s/../../synonyms/', $zipDir));

// Get the ZIP
if (!file_exists($zipFile)) {
    $url = sprintf("http://unicode.org/Public/cldr/%s/core.zip", $version);
    echo "Download $url\n";
    if (!is_dir($zipDir)) {
        mkdir($zipDir);
    }

    $zipResource = fopen($zipFile, "w");

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 900);
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

function filterSynonyms($synonyms): array
{
    // Remove the obvious
    $synonyms = array_filter($synonyms, function ($synonym) {
        if (in_array($synonym, COMPLETELY_ELIMINATED_BY_ANALYZER)) {
            //echo "Bypass synonym $synonym, spotted as COMPLETELY_ELIMINATED_BY_ANALYZER.\n";
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
            //echo "Edit synonym $synonym, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
            $synonym = $cleanString;
        }

        $withSpaceAfter = array_map(function ($a) {
            return $a.' ';
        }, COMPLETELY_ELIMINATED_BY_ANALYZER);

        $cleanString = str_replace($withSpaceAfter, ' ', $synonym);
        if ($cleanString !== $synonym) {
            //echo "Edit synonym $synonym, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
            $synonym = $cleanString;
        }
    });

    return $synonyms;
}

function filterEmoji($emoji): bool
{
    if (empty($emoji)) {
        return true;
    }

    $cleanString = str_replace(COMPLETELY_ELIMINATED_BY_ANALYZER, '', $emoji);
    if ($cleanString !== $emoji) {
        //echo "Bypass line $emoji, spotted a COMPLETELY_ELIMINATED_BY_ANALYZER char.\n";
        return true;
    }

    return false;
}

function annotationXmlToSynonyms(SimpleXMLElement $xml): string
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

function derivedAnnotationXmlToSynonyms(SimpleXMLElement $xml): string
{
    $synonymsContent = '';

    foreach ($xml->annotations->children() as $annotation) {
        $codePoint = mb_ord((string) $annotation['cp']);
        $hasBlackFlag = mb_strpos((string) $annotation['cp'], "\u{1F3F4}") !== false;
        $isFlag = ($codePoint <= 127487 && $codePoint >= 127462) || $hasBlackFlag;
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

        $synonymsContent .= $emoji." => ".$emoji.", ". implode(', ', $synonyms);
        $synonymsContent .= "\n";
    }

    return $synonymsContent;
}

// Remove old versions
echo "Delete old files\n";
foreach(glob($synonymsDir.'/*.txt') as $file) {
    unlink($file);
}

// Build the Blacklist, based on Elasticsearch behavior
echo "Build list of ALL the terms and emoji\n";
$masterList = [];
foreach (glob($extractDir."/common/annotations/*.xml") as $filename) {
    $lang = basename($filename, ".xml");
    if ($lang === 'root') {
        continue;
    }

    $xml = simplexml_load_file($filename);

    // Empty file.
    if ($xml->annotations->children() === null) {
        continue;
    }

    foreach ($xml->annotations->children() as $annotation) {
        if ((string) $annotation['type'] === 'tts') { // Ignore Text To Speech
            continue;
        }
        $synonyms = array_filter(array_map('trim', explode('|', (string) $annotation)));

        $masterList[] = (string) $annotation['cp'];

        // also inspect the annotations! huge impact.
        foreach ($synonyms as $synonym) {
            $masterList[] = $synonym;
        }
    }
}
foreach (glob($extractDir."/common/annotationsDerived/*.xml") as $filename) {
    $lang = basename($filename, ".xml");
    if ($lang === 'root') {
        continue;
    }
    $xml = simplexml_load_file($filename);

    // Empty file.
    if ($xml->annotations->children() === null) {
        continue;
    }

    foreach ($xml->annotations->children() as $annotation) {
        if ((string) $annotation['type'] === 'tts') { // Ignore Text To Speech
            continue;
        }

        $synonyms = array_filter(array_map('trim', explode('|', (string) $annotation)));

        $masterList[] = (string) $annotation['cp'];

        // also inspect the annotations! huge impact.
        foreach ($synonyms as $synonym) {
            $masterList[] = $synonym;
        }
    }
}

$masterList = array_unique($masterList);

// Ask Elasticsearch for each token
$blackList = [];
$client = HttpClient::createForBaseUri('http://localhost:9200');

echo "Send each emoji / term to Lucene. Rejected: \n";
foreach ($masterList as $emoji) {
    if (preg_match('/[a-zA-Z]{1,}/', $emoji) === 1) {
        continue; // this is some simple text, allow automatically
    }

    $response = $client->request('GET', '/_analyze', [
        'json' => [
            'tokenizer' => 'icu_tokenizer', // more restrictive than standard! O_O
            'text' => $emoji,
        ]
    ]);

    $tokens = $response->toArray(false);
    if (empty($tokens['tokens'])) {
        echo $emoji.", ";
        $blackList[] = $emoji;
    }
}

define('COMPLETELY_ELIMINATED_BY_ANALYZER', $blackList);

echo "\n";
echo "Build emoji for each lang\n";
// Read emoji annotations
foreach (glob($extractDir."/common/annotations/*.xml") as $filename) {
    //echo "Read $filename\n";

    $lang = basename($filename, ".xml");

    if ($lang === 'root') {
        continue;
    }

    // Todo: retry me with another version of CLDR. In v44 it breaks.
    if ($lang === 'rhg') {
        continue;
    }

    $xml = simplexml_load_file($filename);

    // Empty file.
    if ($xml->annotations->children() === null) {
        continue;
    }

    $synonymsContent = annotationXmlToSynonyms($xml);

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent);
}

echo "Build emoji derived (compositions) for each lang\n";
// Read emoji derived annotations
foreach (glob($extractDir."/common/annotationsDerived/*.xml") as $filename) {
    //echo "Read $filename\n";

    $lang = basename($filename, ".xml");
    if (!file_exists($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt')) {
        //echo "No annotations for $lang, skip derived.\n";
        continue;
    }

    $xml = simplexml_load_file($filename);

    // Empty file.
    if ($xml->annotations->children() === null) {
        continue;
    }

    $synonymsContent = derivedAnnotationXmlToSynonyms($xml);

    file_put_contents($synonymsDir.'/cldr-emoji-annotation-synonyms-'.$lang.'.txt', $synonymsContent, FILE_APPEND);
}

echo "All done! ✔️\n";
