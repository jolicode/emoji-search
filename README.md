# Emoji synonyms and analyzer for Elasticsearch
> Add support for emoji in any Solr compatible search engine!

## What is this

This repository host synonym files in Solr format, based on [CLDR data set](http://cldr.unicode.org/).

**Learn more about this in our [blog post](TODO).**

Current version is based on 29β.

## Installation in Elasticsearch

### Elasticsearch mapping with emoji support

Download the emoji and emoticon file you want and store them in `PATH_ES/config/analysis`.

Then create an analyzer (called `english_with_emoji` here):

```json
PUT /en-emoji
{
  "settings": {
    "analysis": {
      "filter": {
        "english_emoji": {
          "type": "synonym",
          "synonyms_path": "analysis/cldr-emoji-annotation-synonyms-en.txt" 
        },
        "punctuation_filter": {
          "type": "pattern_replace",
          "pattern": "\\p{Punct}",
          "replace": ""
        },
        "remove_empty_filter": {
          "type": "length",
          "min": 1
        }
      },
      "char_filter": {
        "emoticons_char_filter": {
          "type": "mapping",
          "mappings_path": "analysis/emoticons.txt"
        }
      },
      "analyzer": {
        "english_with_emoji": {
          "char_filter": ["emoticons_char_filter"],
          "tokenizer": "whitespace",
          "filter": [
            "lowercase",
            "punctuation_filter",
            "remove_empty_filter",
            "english_emoji"
          ]
        }
      }
    }
  }
}
```

Trying it:

```json
GET /en-emoji/_analyze?analyzer=english_with_emoji
{
  "text": "I love 🍩"
}
# Result: i, love, 🍩, dessert, donut, sweet
```

## How to contribute

### Build from CLDR SVN

You will need:

- php cli
- svn

Edit the tag in `tools/build-beta.php` and run `php tools/build-beta.php`.

### Update emoticons

Run `php tools/build-emoticon.php`.

## Licenses

Emoji data courtesy of CLDR. See [unicode-license.txt](unicode-license.txt) for details. Some modifications are done on the data, [see here](https://github.com/jolicode/emoji-search/issues/6).
Emoticon data based on [https://github.com/wooorm/emoticon/](https://github.com/wooorm/emoticon/) (MIT).

This repository in distributed under [MIT License](LICENSE). Feel free to use and contribute as you please!