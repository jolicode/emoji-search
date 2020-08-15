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
            $mapping = '"_doc": { "properties": { "content": { "type": "text", "analyzer": "with_emoji" } } }';
        } else {
            $mapping = '"properties": { "content": { "type": "text", "analyzer": "with_emoji" } }';
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
                }
            },
            "analyzer": {
                "with_emoji": {
                    "tokenizer": "standard",
                    "filter": [
                        "custom_emoji"
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

    private function getClient()
    {
        return HttpClient::createForBaseUri('http://localhost:9200');
    }

}
