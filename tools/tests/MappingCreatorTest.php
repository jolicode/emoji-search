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
    "mappings": {
        "properties": {
            "content": {
                "type": "text",
                "analyzer": "with_emoji"
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
