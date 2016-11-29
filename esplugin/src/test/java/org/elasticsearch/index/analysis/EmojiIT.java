package org.elasticsearch.index.analysis;

import org.apache.lucene.analysis.icu.segmentation.ICUTokenizer;
import org.elasticsearch.Version;
import org.elasticsearch.cluster.metadata.IndexMetaData;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.env.Environment;
import org.elasticsearch.index.Index;
import org.elasticsearch.test.ESTestCase;
import org.elasticsearch.test.IndexSettingsModule;

import java.io.IOException;
import java.io.Reader;
import java.io.StringReader;

import static org.apache.lucene.analysis.BaseTokenStreamTestCase.assertTokenStreamContents;

public class EmojiIT extends ESTestCase {
    public void testSimpleIcuTokenizer() throws IOException {
        Settings indexSettings = Settings.builder()
                .put(IndexMetaData.SETTING_VERSION_CREATED, Version.CURRENT)
                .build();

        Settings nodeSettings = Settings.builder()
                .put(Environment.PATH_HOME_SETTING.getKey(), createTempDir())
                .build();

        Environment env = new Environment(nodeSettings);

        // Build the Tokenizer
        TokenizerFactory tokenizerFactory = new EmojiTokenizerFactory(
                IndexSettingsModule.newIndexSettings(new Index("test", "_na_"), indexSettings),
                env,
                "emoji_tokenizer",
                Settings.EMPTY
        );
        ICUTokenizer tokenizer = (ICUTokenizer) tokenizerFactory.create();

        // Real tests
        Reader reader = new StringReader("向日葵, one-two");
        tokenizer.setReader(reader);
        assertTokenStreamContents(tokenizer, new String[]{"向日葵", "one", "two"});

        Reader reader2 = new StringReader("Simple: \uD83D\uDE02, Modified: \uD83D\uDC66\uD83C\uDFFD " +
                "and composed rainbow: \uD83C\uDFF3️\u200D\uD83C\uDF08 and \uD83C\uDDF8\uD83C\uDDEA Sweden flag.");
        tokenizer.setReader(reader2);

        assertTokenStreamContents(tokenizer, new String[]{
            "Simple",
            "\uD83D\uDE02",
            "Modified",
            "\uD83D\uDC66\uD83C\uDFFD",
            "and",
            "composed",
            "rainbow",
            "\uD83C\uDFF3️\u200D\uD83C\uDF08",
            "and",
            "\uD83C\uDDF8\uD83C\uDDEA",
            "Sweden",
            "flag",
        });
    }
}
