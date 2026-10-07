## Requirements

### Runtime

- PHP 8.1
- MediaWiki 1.43
- mmdc or kroki

### Development

- PHP 8.1
- ast (for phan)
- composer
- docker

## File Structure

```
|-- extension.json              # 📋 MediaWiki extension registration
|-- composer.json               # 📦 Composer dependencies
|-- phpcs.xml                   # ✅ PHPCS config (MediaWiki standard)
|-- config-puppeteer.json       # 🤖 Puppeteer config (for mmdc)
|-- .phan/
|   |-- config.php              # 🔍 Phan static analysis config
|-- src/
|   |-- ExtensionConstants.php  # 🏷️ Extension name, version constants
|   |-- Hook.php                # 🪝 MediaWiki hook registration & main logic
|   |-- FieldResult.php         # 📝 Form field result wrapper
|   |-- FileSystemUtils.php     # 📁 File system utilities
|   |-- Validator.php           # ✔️ MMD syntax validation
|-- templates/
|   |-- mmd-builder.php         # 🏗️ mmdc execution template (stdin -> SVG)
|-- i18n/
|   |-- en.json                 # 🇬🇧 English translations
|   |-- zh-hant.json            # 🇹🇼 Traditional Chinese translations
|-- debug/                      # 🐛 Debug files (not in VCS)
```
