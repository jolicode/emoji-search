package org.elasticsearch.index.analysis;

import org.apache.lucene.analysis.Tokenizer;
import org.elasticsearch.ElasticsearchException;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.env.Environment;
import org.elasticsearch.index.IndexSettings;

import org.apache.lucene.analysis.Tokenizer;
import org.apache.lucene.analysis.standard.StandardAnalyzer;
import org.apache.lucene.analysis.standard.StandardTokenizer;

public class EmojiTokenizerFactory extends AbstractTokenizerFactory {

    private final int maxTokenLength;

    public EmojiTokenizerFactory(IndexSettings indexSettings, Environment environment, String name, Settings settings) {
        super(indexSettings, name, settings);
        maxTokenLength = settings.getAsInt("max_token_length", StandardAnalyzer.DEFAULT_MAX_TOKEN_LENGTH);
    }

    @Override
    public Tokenizer create() {
        StandardTokenizer tokenizer = new StandardTokenizer();
        tokenizer.setMaxTokenLength(maxTokenLength);
        return tokenizer;
    }
}
