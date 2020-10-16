<?php

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

class MappingCreatorTest extends TestCase
{
    public function testServerIsRunning()
    {
        $client = $this->getClient();

        $response = $client->request('GET', '/');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function synonymFileProvider()
    {
        $files = scandir(__DIR__.'/../../synonyms/');
        $files = array_filter($files, function ($file) {
            return $file !== '..' && $file !== '.';
        });

        return array_map(function ($file) {
            return [$file];
        }, $files);
    }

    /**
     * @dataProvider synonymFileProvider
     */
    public function testPutMapping($file)
    {
        $client = $this->getClient();

        $client->request('DELETE', '/test_put_mapping')->getStatusCode();

        if (version_compare($_SERVER['TARGET'], '7.0.0', '<')) {
            $mapping = '"_doc": { "properties": { "content": { "type": "text", "analyzer": "with_emoji" }, "content_icu": { "type": "text", "analyzer": "with_emoji_and_icu" } } }';
        } else {
            $mapping = '"properties": { "content": { "type": "text", "analyzer": "with_emoji" }, "content_icu": { "type": "text", "analyzer": "with_emoji_and_icu" } }';
        }

        $response = $client->request('PUT', '/test_put_mapping', [
            'headers' => [
                'Content-Type: application/json'
            ],
            'body' => <<<JSON
{
    "settings": {
        "analysis": {
            "filter": {
                "custom_emoji": {
                    "type": "synonym",
                    "synonyms_path": "$file"
                },
                "emoji_variation_selector_filter": {
                    "type": "pattern_replace",
                    "pattern": "\\uFE0E|\\uFE0F",
                    "replace": ""
                }
            },
            "analyzer": {
                "with_emoji": {
                    "tokenizer": "standard",
                    "filter": [
                        "custom_emoji",
                        "emoji_variation_selector_filter"
                    ]
                },
                "with_emoji_and_icu": {
                    "tokenizer": "icu_tokenizer",
                    "filter": [
                        "custom_emoji",
                        "emoji_variation_selector_filter"
                    ]
                }
            }
        }
    },
    "mappings": { $mapping }
}
JSON
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
    }

    public function analyzerProvider()
    {
        return [
            ['Pizza', ['Pizza']],
            ['⛹🏿‍♂', ['homme', 'ballon', 'peau']],
            ['🇸🇳', ['🇸🇳', 'drapeau', 'Sénégal']],
            ['🐧', ['🐧', 'animal', 'oiseau', 'pingouin']],
            ['👩🏼‍🚀', ['👩🏼‍🚀', 'astronaute', 'espace', 'femme']],
            ['🏴‍☠', ['🏴‍☠', 'pirate']],
        ];
    }

    /**
     * @dataProvider analyzerProvider
     */
    public function testAnalyzer($text, $expectedTokens)
    {
        $this->testPutMapping('cldr-emoji-annotation-synonyms-fr.txt');

        $client = $this->getClient();
        $response = $client->request('GET', '/test_put_mapping/_analyze', [
            'json' => [
                'analyzer' => 'with_emoji',
                'text' => $text
            ]
        ]);

        $tokens = $response->toArray();
        $tokens = array_map(function ($a) {
            return $a['token'];
        }, $tokens['tokens']);

        foreach ($expectedTokens as $token) {
            $this->assertContains($token, $tokens);
        }
    }

    public function testIssue26VariationSelector()
    {
        $this->testPutMapping('cldr-emoji-annotation-synonyms-nl.txt');
        $client = $this->getClient();

        /**
         *   1F34F ├─ 🍏		├─ GREEN APPLE
         *      ---- ├┬ 🥑️️️		├┬ Composition
         *      1F951 │├─ 🥑		│├─ AVOCADO
         *      FE0F │├─ VS16	│├─ VARIATION SELECTOR-16
         *      FE0F │├─ VS16	│├─ VARIATION SELECTOR-16
         *      FE0F │└─ VS16	│└─ VARIATION SELECTOR-16
         */
        $vs16 = "\xEF\xB8\x8F";
        $compromisedText = '🍏🥑'.$vs16.$vs16.$vs16;

        $response = $client->request('GET', '/test_put_mapping/_analyze', [
            'json' => [
                'tokenizer' => 'standard',
                'text' => $compromisedText
            ]
        ]);

        $tokens = $response->toArray();
        $tokens = array_map(function ($a) {
            return $a['token'];
        }, $tokens['tokens']);

        $this->assertCount(2, $tokens);
        $this->assertSame('🍏', $tokens[0]);
        $this->assertSame('🥑'.$vs16, $tokens[1]);

        $response = $client->request('GET', '/test_put_mapping/_analyze', [
            'json' => [
                'tokenizer' => 'standard',
                'filter' => [
                    [
                        'type' => 'pattern_replace',
                        'pattern' => '\\uFE0E|\\uFE0F',
                        'replace' => ''
                    ],
                    'custom_emoji'
                ],
                'text' => $compromisedText
            ]
        ]);

        $tokens = $response->toArray();
        $tokens = array_map(function ($a) {
            return $a['token'];
        }, $tokens['tokens']);

        $this->assertGreaterThan(2, $tokens);
        $this->assertContains('🍏', $tokens);
        $this->assertContains('🥑', $tokens);
        $this->assertContains('avocado', $tokens);
        $this->assertContains('appel', $tokens);
    }

    private function getClient()
    {
        return HttpClient::createForBaseUri('http://localhost:9200');
    }
}
