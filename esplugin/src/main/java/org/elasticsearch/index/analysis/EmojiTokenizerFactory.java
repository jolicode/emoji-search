package org.elasticsearch.index.analysis;

import com.ibm.icu.text.BreakIterator;
import com.ibm.icu.text.RuleBasedBreakIterator;
import com.ibm.icu.util.ULocale;
import org.apache.lucene.analysis.Tokenizer;
import org.apache.lucene.analysis.icu.segmentation.DefaultICUTokenizerConfig;
import org.apache.lucene.analysis.icu.segmentation.ICUTokenizer;
import org.apache.lucene.analysis.icu.segmentation.ICUTokenizerConfig;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.env.Environment;
import org.elasticsearch.index.IndexSettings;

/**
 * Build an ICU Tokenizer using the latest ICU and a customized RuleSet for emoji status 200
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
                        BreakIterator.getWordInstance(ULocale.getDefault());
                String defaultRules = rbbi.toString();

                // Customize the rules to add EmojiNRK as first class word
                defaultRules = defaultRules.replace(
                    "!!forward;",
                    "!!forward;$EmojiNRK {200};"
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
    }
}
