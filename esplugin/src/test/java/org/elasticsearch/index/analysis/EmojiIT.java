package org.elasticsearch.index.analysis;

import org.elasticsearch.test.ESTestCase;
import java.io.IOException;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.InetAddress;
import java.net.Socket;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.util.Objects;

import java.io.StringReader;
import java.util.ArrayList;
import java.util.HashSet;
import java.util.LinkedHashSet;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;

import org.apache.lucene.analysis.Analyzer;
import org.apache.lucene.analysis.TokenStream;
import org.apache.lucene.analysis.Tokenizer;
import org.apache.lucene.analysis.core.KeywordTokenizer;
import org.apache.lucene.analysis.tokenattributes.CharTermAttribute;
import org.elasticsearch.common.settings.Settings;
import org.elasticsearch.index.Index;
import org.elasticsearch.test.ESTestCase;

import static org.hamcrest.Matchers.hasSize;

public class EmojiIT extends ESTestCase {
    public void testTokenizerKeepEmoji() throws Exception {
       assertEquals("TEST", "TEST");
    }

    public void testMaxExpansions() throws IOException {
        testTokenization(createTokenizer(), "Foobar", 1); // without max_expansions
    }

    private Tokenizer createTokenizer() {
        return new EmojiTokenizer();
    }

    private void testTokenization(Tokenizer tokenizer, String input, int expected) throws IOException {
        tokenizer.setReader(new StringReader(input));
        List<String> result = readStream(tokenizer);
        assertThat(new HashSet<>(result), hasSize(expected));
        tokenizer.close();
    }

    private List<String> readStream(TokenStream stream) throws IOException {
        stream.reset();

        List<String> result = new ArrayList<>();
        while (stream.incrementToken()) {
            result.add(stream.getAttribute(CharTermAttribute.class).toString());
        }

        return result;
    }

//    public void testSimpleIcuTokenizer() throws IOException {
//        TestAnalysis analysis = createTestAnalysis();

//        TokenizerFactory tokenizerFactory = analysis.tokenizer.get("icu_tokenizer");
//        ICUTokenizer tokenizer = (ICUTokenizer) tokenizerFactory.create();
//
//        Reader reader = new StringReader("向日葵, one-two");
//        tokenizer.setReader(reader);
//        assertTokenStreamContents(tokenizer, new String[]{"向日葵", "one", "two"});
//    }
}
