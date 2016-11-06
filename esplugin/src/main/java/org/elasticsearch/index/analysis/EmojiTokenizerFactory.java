package org.elasticsearch.index.analysis;

import com.ibm.icu.text.BreakIterator;
import com.ibm.icu.text.RuleBasedBreakIterator;
import org.apache.lucene.analysis.Tokenizer;
import org.apache.lucene.analysis.icu.segmentation.DefaultICUTokenizerConfig;
import org.apache.lucene.analysis.icu.segmentation.ICUTokenizer;
import org.apache.lucene.analysis.icu.segmentation.ICUTokenizerConfig;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.env.Environment;
import org.elasticsearch.index.IndexSettings;

import java.util.Locale;

/**
 * Build an ICU Tokenizer using the latests ICU and a customized RuleSet for emoji
 */
public class EmojiTokenizerFactory extends AbstractTokenizerFactory {

    private final ICUTokenizerConfig config;

    public EmojiTokenizerFactory(IndexSettings indexSettings, Environment environment, String name, Settings settings) {
        super(indexSettings, name, settings);

        config = new DefaultICUTokenizerConfig(true, true) {
            @Override
            public BreakIterator getBreakIterator(int script) {
                // Load the ICU default rules
                RuleBasedBreakIterator rbbi = (RuleBasedBreakIterator)
                        BreakIterator.getWordInstance(Locale.getDefault());
                String defaultRules = rbbi.toString();

                defaultRules = defaultRules.replace(
                    "!!forward;",
                    "!!forward;\n$EmojiNRK {200};"
                );

                defaultRules = defaultRules.replace(
                    "| $ZWJ)*;",
                    "| $ZWJ)* {200};"
                );

                return new RuleBasedBreakIterator(defaultRules);
            }
        };
    }

    @Override
    public Tokenizer create() {
        return new ICUTokenizer(config);

        /*StandardTokenizer tokenizer = new StandardTokenizer();
        tokenizer.setMaxTokenLength(maxTokenLength);
        return tokenizer;*/
    }
}
