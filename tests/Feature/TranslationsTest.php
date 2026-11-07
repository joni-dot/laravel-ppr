<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

/**
 * Every text is written in English and passed through __() in PHP, or $t() and trans() in Vue.
 * lang/en.json lists all of them, so a translator can copy it to start a new language.
 */
class TranslationsTest extends TestCase
{
    /**
     * Folders with code that shows text to people. Generated code is left out.
     */
    private const array SOURCE_FOLDERS = ['app', 'routes', 'resources/views', 'resources/js'];

    private const array GENERATED_FOLDERS = ['resources/js/actions', 'resources/js/routes', 'resources/js/wayfinder'];

    /**
     * __('...'), trans('...') and $t('...') with a quoted text, not $this->trans() or Lang::trans().
     */
    private const string TRANSLATION_CALL = '/(?<![\w$.>:])(?:__|trans|\$t)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/s';

    /**
     * Attributes whose text people read or hear. A bound attribute (":title") is code, so it is skipped.
     */
    private const string TEXT_ATTRIBUTE = '/(?<![\w:@.-])(placeholder|title|aria-label|alt|label|description|tooltip|hint|confirm-label)="([^"]*)"/';

    /**
     * Template text that is not language, so it needs no translation.
     */
    private const array LANGUAGE_NEUTRAL_TEXT = ['email@example.com'];

    public function test_every_text_in_the_code_is_listed_in_the_english_language_file()
    {
        $missing = array_values(array_diff($this->textsInCode(), array_keys($this->languageFile('en'))));

        $this->assertSame([], $missing, 'Add these texts to lang/en.json, with the same text as key and value.');
    }

    public function test_the_english_language_file_lists_no_texts_the_code_no_longer_uses()
    {
        $unused = array_values(array_diff(array_keys($this->languageFile('en')), $this->textsInCode()));

        $this->assertSame([], $unused, 'Remove these texts from lang/en.json and every other language file.');
    }

    public function test_every_language_translates_exactly_the_english_texts()
    {
        $english = array_keys($this->languageFile('en'));
        $problems = [];

        foreach ($this->otherLanguages() as $language) {
            $texts = array_keys($this->languageFile($language));

            foreach (array_diff($english, $texts) as $text) {
                $problems[] = "lang/{$language}.json is missing: {$text}";
            }

            foreach (array_diff($texts, $english) as $text) {
                $problems[] = "lang/{$language}.json has a text the code does not use: {$text}";
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_translations_keep_the_placeholders_of_the_english_text()
    {
        $problems = [];

        foreach ($this->otherLanguages() as $language) {
            foreach ($this->languageFile($language) as $english => $translation) {
                if ($this->placeholders($english) !== $this->placeholders($translation)) {
                    $problems[] = "lang/{$language}.json changes the placeholders of: {$english}";
                }
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_vue_templates_have_no_text_that_skips_translation()
    {
        $untranslated = [];

        foreach ($this->sourceFiles('vue') as $file) {
            foreach ($this->untranslatedTemplateText($file->getContents()) as $text) {
                $untranslated[] = "{$file->getRelativePathname()}: {$text}";
            }
        }

        $this->assertSame([], $untranslated, 'Wrap these texts in $t(), for example {{ $t(\'Save\') }} or :title="$t(\'Save\')".');
    }

    /**
     * @return list<string>
     */
    private function textsInCode(): array
    {
        $texts = [];

        foreach ($this->sourceFiles('php', 'vue', 'ts') as $file) {
            preg_match_all(self::TRANSLATION_CALL, $file->getContents(), $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $texts[] = $this->unescape($match[2] ?? $match[1]);
            }
        }

        $jsonTexts = array_filter($texts, fn (string $text) => ! $this->isLaravelLanguageFileKey($text));

        return array_values(array_unique($jsonTexts));
    }

    /**
     * Keys like "validation.unique" point to Laravel's own PHP language files, not to lang/*.json.
     */
    private function isLaravelLanguageFileKey(string $text): bool
    {
        return preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $text) === 1;
    }

    private function unescape(string $quoted): string
    {
        return preg_replace('/\\\\(.)/s', '$1', $quoted);
    }

    /**
     * @return list<string>
     */
    private function untranslatedTemplateText(string $vueFile): array
    {
        $template = $this->template($vueFile);
        $template = preg_replace('/<!--.*?-->/s', '', $template);
        $template = preg_replace('/\{\{.*?\}\}/s', '', $template);

        preg_match_all(self::TEXT_ATTRIBUTE, $template, $attributes);

        // A tag can contain ">" inside a quoted attribute, such as @click="() => open()".
        $text = preg_replace('/<(?:[^>"\']|"[^"]*"|\'[^\']*\')*>/', "\n", $template);
        $text = preg_replace('/&\w+;/', '', $text);

        $found = [...$attributes[2], ...array_map(trim(...), explode("\n", $text))];

        return array_values(array_filter(
            $found,
            fn (string $text) => preg_match('/\p{L}/u', $text) === 1 && ! in_array($text, self::LANGUAGE_NEUTRAL_TEXT, true),
        ));
    }

    private function template(string $vueFile): string
    {
        $start = strpos($vueFile, '<template');
        $end = strrpos($vueFile, '</template>');

        return $start === false || $end === false ? '' : substr($vueFile, $start, $end - $start);
    }

    /**
     * @return array<string, string>
     */
    private function languageFile(string $language): array
    {
        return json_decode(File::get(lang_path("{$language}.json")), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function otherLanguages(): array
    {
        $languages = array_map(fn (string $path) => basename($path, '.json'), File::glob(lang_path('*.json')));

        return array_values(array_diff($languages, ['en']));
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $text): array
    {
        preg_match_all('/:[a-zA-Z_]+/', $text, $matches);
        $placeholders = array_map(strtolower(...), $matches[0]);
        sort($placeholders);

        return $placeholders;
    }

    /**
     * @return list<SplFileInfo>
     */
    private function sourceFiles(string ...$extensions): array
    {
        $files = [];

        foreach (self::SOURCE_FOLDERS as $folder) {
            foreach (File::allFiles(base_path($folder)) as $file) {
                if (in_array($file->getExtension(), $extensions, true) && ! $this->isGenerated($file)) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    private function isGenerated(SplFileInfo $file): bool
    {
        foreach (self::GENERATED_FOLDERS as $folder) {
            if (str_starts_with($file->getPathname(), base_path($folder).DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }
}
