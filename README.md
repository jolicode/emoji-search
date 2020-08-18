# Emoji, flags and emoticons support for Elasticsearch

> Add support for emoji and flags in any Lucene compatible search engine!

If you wish to search `🍩` to find **donuts** in your documents, you came to the right place. This project offer synonym files ready for usage in Elasticsearch analyzer.

![Test all synonym files on a real Elasticsearch](https://github.com/jolicode/emoji-search/workflows/Test%20all%20synonym%20files%20on%20a%20real%20Elasticsearch/badge.svg)

## Requirements to index emoji in Elasticsearch

| Version | Requirements | 
|----------|:-------------:|
| Elasticsearch >= 6.7 | The standard tokenizer now understand Emoji 🎉 thanks to [Lucene 7.7.0](https://github.com/apache/lucene-solr/commit/283b19a8da6ab9e0b7e9a75b132d3067218d5502) - no plugin needed ! |
| Elasticsearch >= 6.4 and < 6.7 | You need to install the official [ICU Plugin](https://www.elastic.co/guide/en/elasticsearch/plugins/current/analysis-icu.html). See our [blog post about this change](https://jolicode.com/blog/elasticsearch-icu-now-understands-emoji). |
| Elasticsearch < 6.4 | You need our [custom ICU Tokenizer Plugin](/esplugin), see our [blog post](http://jolicode.com/blog/search-for-emoji-with-elasticsearch) (2016). |

Run the following test to verify that you get 4 EMOJI tokens:

```json
GET _analyze
{
  "text": ["🍩 🇫🇷 👩‍🚒 🚣🏾‍♀"]
}
```

## The Synonyms, flags and emoticons

What you need to search with emoji is a way to expand them to words that can match searches and documents, in **your language**. 
That's the goal of the [synonym dictionaries](/synonyms).

We build Solr / Lucene compatible synonyms files in all languages supported by [Unicode CLDR](http://cldr.unicode.org/) so you can set them up in an analyzer. It looks like this:

```
👩‍🚒 => 👩‍🚒, firefighter, firetruck, woman
👩‍✈ => 👩‍✈, pilot, plane, woman
🥓 => 🥓, bacon, meat, food
🥔 => 🥔, potato, vegetable, food
😅 => 😅, cold, face, open, smile, sweat
😆 => 😆, face, laugh, mouth, open, satisfied, smile
🚎 => 🚎, bus, tram, trolley
🇫🇷 => 🇫🇷, france
🇬🇧 => 🇬🇧, united kingdom
```

For emoticons, use [this mapping](emoticons.txt) with a char_filter to replace emoticons by emoji.

### Installation

Download the emoji and emoticon file you want from this repository and store them in `PATH_ES/config/analysis` (_or anywhere Elasticsearch can read_).

```
config
├── analysis
│   ├── cldr-emoji-annotation-synonyms-en.txt
│   └── emoticons.txt
├── elasticsearch.yml
...
```

Use them like this (this is a complete _english_ example with Elasticsearch >= 6.7):

```json
PUT /tweets
{
  "settings": {
    "analysis": {
      "filter": {
        "english_emoji": {
          "type": "synonym",
          "synonyms_path": "analysis/cldr-emoji-annotation-synonyms-en.txt" 
        },
        "english_stop": {
          "type":       "stop",
          "stopwords":  "_english_"
        },
        "english_keywords": {
          "type":       "keyword_marker",
          "keywords":   ["example"]
        },
        "english_stemmer": {
          "type":       "stemmer",
          "language":   "english"
        },
        "english_possessive_stemmer": {
          "type":       "stemmer",
          "language":   "possessive_english"
        }
      },
      "analyzer": {
        "english_with_emoji": {
          "tokenizer": "standard",
          "filter": [
            "english_possessive_stemmer",
            "lowercase",
            "english_emoji",
            "english_stop",
            "english_keywords",
            "english_stemmer"
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
```

You can now test the result with:

```json
GET tweets/_analyze
{
  "field": "content",
  "text": "🍩 🇫🇷 👩‍🚒 🚣🏾‍♀"
}
```

## How to contribute

### Build from CLDR SVN

You will need:

- php cli
- php zip and curl extensions

Edit the tag in `tools/build-released.php` and run `php tools/build-released.php`.

### Update emoticons

Run `php tools/build-emoticon.php`.

## Licenses

Emoji data courtesy of CLDR. See [unicode-license.txt](unicode-license.txt) for details. Some modifications are done on the data, [see here](https://github.com/jolicode/emoji-search/issues/6).
Emoticon data based on [https://github.com/wooorm/emoticon/](https://github.com/wooorm/emoticon/) (MIT).

This repository in distributed under [MIT License](LICENSE). Feel free to use and contribute as you please!
