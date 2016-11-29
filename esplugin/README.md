# Elasticsearch analysis-emoji plugin

This plugin create a new Tokenizer called `emoji_tokenizer` based on `icu_tokenizer` and the latest (58.1) ICU data.

## Installation

Make sure the change the version number in the download URL, or just copy and paste the latest zip full URL from the [release page](https://github.com/jolicode/emoji-search/releases). 

The plugin version must match your Elasticsearch version.

```
bin/elasticsearch-plugin install URL

# For 5.0.0:
bin/elasticsearch-plugin install https://github.com/jolicode/emoji-search/releases/download/5.0.0/analysis-emoji-5.0.0.zip
```

## Versions

analysis-emoji version and ES version  | Install URL
-----------|-----------
5.0.0 | https://github.com/jolicode/emoji-search/releases/download/5.0.0/analysis-emoji-5.0.0.zip

## How to use

Build your own analyzer and use the new tokenizer. Look at the main [README](../README.md) for more informations.

Download the emoji and emoticon file you want from this repository and store them in `PATH_ES/config/analysis`.

```
config
├── analysis
│   ├── cldr-emoji-annotation-synonyms-en.txt
│   └── emoticons.txt
├── elasticsearch.yml
...
```

And build an analyzer:

```json
PUT /en-emoji
{
  "settings": {
    "analysis": {
      "char_filter": {
        "emoticons_char_filter": {
          "type": "mapping",
          "mappings_path": "analysis/emoticons.txt"
        }
      },
      "filter": {
        "english_emoji": {
          "type": "synonym",
          "synonyms_path": "analysis/cldr-emoji-annotation-synonyms-en.txt" 
        }
      },
      "analyzer": {
        "english_with_emoji": {
          "char_filter": ["emoticons_char_filter"],
          "tokenizer": "emoji_tokenizer",
          "filter": [
            "lowercase",
            "english_emoji"
          ]
        }
      }
    }
  }
}
```
Try it:

```json
GET /en-emoji/_analyze?analyzer=english_with_emoji
{
  "text": "I live in 🇫🇷 and I'm 👩‍🚀"
}
# Result: i live in 🇫🇷 france and i'm 👩‍🚀 astronaut rocket woman

GET /en-emoji/_analyze?analyzer=english_with_emoji
{
  "text": "Hi mom :)"
}
# Result:  hi mom 😃 face mouth open smile
```
