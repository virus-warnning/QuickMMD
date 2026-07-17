# QuickMMD

## 🚀 Project Overview
- **Purpose**: A MediaWiki extension to create chart by Mermaid syntax.
- **Key Features**: QuickMMD 讓 MediaWiki 能夠畫出 Mermaid Chart, QuickGV 讓 MediaWiki 能夠畫出 Graphviz

## 🛠 Tech Stack
- **Infrastructure**: MediaWiki extension
- **Programming Languages**: PHP 8+, SVG, MMD
- **External APIs**: TODO

## 📂 Directory Structure
- `/i18n`: terms definition
- `config-mmdc.json`: config file for mermaid-cli
- `config-puppeteer.json`: config file for puppeteer
- `extension.json`: information of this extension
- `QuickMMD.body.php`: parser hook
- `QuickMMD.i18n.php`: language loader
- `QuickMMD.template.php`: mermaid syntax composer

## 🔄 Key Workflows
- **MediaWiki would invoke QuickMMD::init() on <quickmmd> element detected.**: 

## ⚠️ Constraints & Preferences
- **Model Preference**: Use local model
- **Language**: 溝通與文件說明優先使用中文。
- **API Usage**: 整合外部 API 時需考慮限流與正確的錯誤處理。
