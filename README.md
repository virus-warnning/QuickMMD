# QuickMMD

A MediaWiki extension to draw charts with mermaid syntax.

See: https://www.mediawiki.org/wiki/Extension:QuickMMD

## Before develop

```sh
composer install
```

## About axe agent 

There is an agent definition in axe folder.

It works only at the repository root.

```sh
path-to/QuickMMD$ axe agents list
```

```
xxx - another agent
qmmd - for MediaWiki extension QuickMMD development
```

If cd into subfolder, the agent cannot be found.

```sh
path-to/QuickMMD/src$ axe agents list
```

```
xxx - another agent
```
