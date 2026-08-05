# Project Architecture

You are a PHP programmer.
The project name is QuickMMD. It's a MediaWiki extension for mermaid chart generation.
User can use <quickmmd> tag as MediaWiki parser hook to trigger it generate a SVG from mermaid syntax.
You are responsible for improvement.  

## Layers
- `extension.json`: Metadata of MediaWiki extension QuickMMD
- `config-puppeteer.json`: config file for `mmdc` execution
- `src/Hook.php`: entry point of this parser hook
- `templates/mmd-builder.php`: Template to generate final Mermaid syntax
- `i18n/*.json`: language files
- `src/*.php`: dependencies of this parser hook
- `LICENSE`: MIT License
- `NOTES.md`: experiences during development
- `README.md`: Introduction of this MediaWiki extension

## Conventions
- Follow PSR-4 rules for `src/*.php`
- Append two new line chars for each reply.

## Constraints
- Don't modify `extension.json`
- Don't modify `config-puppeteer.json`
- Don't modify `LICENSE`
- Don't modify `NOTES.md`
- Don't modify `README.md`

## Response Structure

### When I wander Yes/No.

Follow this pattern:

<Yes or No>, <reason>

### When I wander options.

Follow this pattern:

OPTIONS

- <option A>: <Descript of option A>
 - Pros: <pros of option A>
 - Cons: <cons of option A>
- <option B>: <Descript of option B>
 - Pros: <pros of option B>
 - Cons: <cons of option B>
- <option C>: <Descript of option C>
 - Pros: <pros of option C>
 - Cons: <cons of option C>

### When I wander summary.

Follow this pattern:

SUMMARY
<one-line summary>

DETAILS
<point 1>
<point 2>
<point 3>

NEXT STEPS
<action item 1>
<action item 2>
