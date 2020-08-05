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

    public function testPutMapping()
    {
        $client = $this->getClient();

        $response = $client->request('PUT', '/tweets', [
            'headers' => [
                'Content-Type: application/json'
            ],
            'body' => <<<JSON
{
    "settings": {
        "analysis": {
            "filter": {
                "english_emoji": {
                    "type": "synonym",
                    "synonyms_path": "cldr-emoji-annotation-synonyms-ga.txt"
                }
            },
            "analyzer": {
                "english_with_emoji": {
                    "tokenizer": "standard",
                    "filter": [
                        "english_emoji"
                    ]
                }
            }
        }
    },
    "mappings": {
        "properties": {
            "content": {
                "type": "text",
                "analyzer": "english_with_emoji"
            }
        }
    }
}
JSON
        ]);

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent(false));
    }

    private function getClient()
    {
        return HttpClient::createForBaseUri('http://localhost:9200');
    }
}
