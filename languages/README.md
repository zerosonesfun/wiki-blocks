# Wiki Blocks Translations

This directory contains translation files for the Wiki Blocks plugin.

## Available Languages

- **Spanish (es_ES)**: `wiki-blocks-es_ES.po` / `wiki-blocks-es_ES.mo`
- **French (fr_FR)**: `wiki-blocks-fr_FR.po` / `wiki-blocks-fr_FR.mo`
- **Italian (it_IT)**: `wiki-blocks-it_IT.po` / `wiki-blocks-it_IT.mo`
- **Japanese (ja)**: `wiki-blocks-ja.po` / `wiki-blocks-ja.mo`

## File Types

- **`.po` files**: Portable Object files containing the original English text and translations
- **`.mo` files**: Machine Object files (compiled translations) used by WordPress
- **`.pot` file**: Portable Object Template containing all translatable strings

## How Translations Work

1. The plugin automatically loads translations based on the WordPress site language
2. If a translation exists for the current language, it will be used instead of English
3. The plugin uses the text domain `wiki-blocks` and loads translations from `/languages/`

## Adding New Translations

To add a new language:

1. Copy `wiki-blocks.pot` to `wiki-blocks-{locale}.po`
2. Translate all the strings in the `.po` file
3. Compile the `.po` file to `.mo` using:
   ```bash
   msgfmt wiki-blocks-{locale}.po -o wiki-blocks-{locale}.mo
   ```

## Updating Translations

When new strings are added to the plugin:

1. Update the `.pot` file with new strings
2. Update each `.po` file with the new translations
3. Recompile all `.mo` files

## WordPress Language Codes

Common language codes:
- `es_ES` - Spanish (Spain)
- `fr_FR` - French (France)
- `it_IT` - Italian (Italy)
- `ja` - Japanese
- `de_DE` - German (Germany)
- `pt_BR` - Portuguese (Brazil)
- `ru_RU` - Russian (Russia)
- `zh_CN` - Chinese (Simplified)

## Contributing Translations

If you'd like to contribute a translation:

1. Create a new `.po` file for your language
2. Translate all strings
3. Test the translation in a WordPress installation
4. Submit the translation files

## Technical Notes

- The plugin uses WordPress's built-in internationalization functions (`__()`, `_e()`, etc.)
- All user-facing strings are translatable
- JavaScript strings are also translatable through PHP localization
- The plugin follows WordPress coding standards for internationalization 