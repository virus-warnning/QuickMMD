# QuickMMD

## 🚀 Project Overview
- **Purpose**: A MediaWiki extension to create chart by Mermaid syntax.
- **Key Features**: QuickMMD 讓 MediaWiki 能夠畫出 Mermaid Chart, QuickGV 讓 MediaWiki 能夠畫出 Graphviz

## 🛠 Tech Stack
- **Infrastructure**: MediaWiki extension
- **Programming Languages**: PHP 8+, SVG, MMD
- **External APIs**: TODO

## 📂 Directory Structure
- `i18n/`: terms definition
- `config-puppeteer.json`: config file for puppeteer
- `extension.json`: information of this extension
- `src/ExtensioConstants.php`: global constants for this extension
- `src/FieldResult.php`: the result of single field validation
- `src/FileSystemUtils.php`: static functions for file system handling
- `src/Hook.php`: parser hook
- `src/Validator.php`: validator for properties of parser hook
- `templates/mmd-builder.php`: merge user mermaid syntax & default mermaid syntax

## 🔄 Key Workflows
- **MediaWiki would invoke QuickMMD::init() on <quickmmd> element detected.**: 

## ⚠️ Constraints & Preferences
- **Model Preference**: Use local model
- **Language**: 溝通與文件說明優先使用中文。
- **API Usage**: 整合外部 API 時需考慮限流與正確的錯誤處理。
