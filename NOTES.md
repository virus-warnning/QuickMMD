### Save once, trigger three times.

```
[10:28:18] trigger render()
MediaWiki\Extension\QuickMMD\Hook -> getCleanStack() at /var/www/html/extensions/QuickMMD/src/Hook.php:92
MediaWiki\Extension\QuickMMD\Hook -> render() at /var/www/html/includes/parser/Parser.php:4019
Parser -> extensionSubstitution() at /var/www/html/includes/parser/PPFrame_Hash.php:352
PPFrame_Hash -> expand() at /var/www/html/includes/parser/Parser.php:2953
Parser -> replaceVariables() at /var/www/html/includes/parser/Parser.php:1609
Parser -> internalParse() at /var/www/html/includes/parser/Parser.php:723
Parser -> parse() at /var/www/html/includes/content/WikitextContentHandler.php:301
WikitextContentHandler -> fillParserOutput() at /var/www/html/includes/content/ContentHandler.php:1720
ContentHandler -> getParserOutput() at /var/www/html/includes/content/Renderer/ContentRenderer.php:47
MediaWiki\Content\Renderer\ContentRenderer -> getParserOutput() at /var/www/html/includes/Revision/RenderedRevision.php:265
MediaWiki\Revision\RenderedRevision -> getSlotParserOutputUncached() at /var/www/html/includes/Revision/RenderedRevision.php:237
MediaWiki\Revision\RenderedRevision -> getSlotParserOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:221
MediaWiki\Revision\RevisionRenderer -> combineSlotOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:158
Global -> call_user_func() at /var/www/html/includes/Revision/RenderedRevision.php:199
MediaWiki\Revision\RenderedRevision -> getRevisionParserOutput() at /var/www/html/includes/Storage/DerivedPageDataUpdater.php:1438
MediaWiki\Storage\DerivedPageDataUpdater -> getCanonicalParserOutput() at /var/www/html/includes/Storage/DerivedPageDataUpdater.php:1817
MediaWiki\Storage\DerivedPageDataUpdater -> doParserCacheUpdate() at /var/www/html/includes/Storage/DerivedPageDataUpdater.php:1715
MediaWiki\Storage\DerivedPageDataUpdater -> triggerParserCacheUpdate() at /var/www/html/includes/Storage/DerivedPageDataUpdater.php:1568
MediaWiki\Storage\DerivedPageDataUpdater -> doUpdates() at /var/www/html/includes/Storage/PageUpdater.php:1643
MediaWiki\Storage\PageUpdater -> MediaWiki\Storage\{closure}() at /var/www/html/includes/libs/rdbms/database/Database.php:2753
Wikimedia\Rdbms\Database -> doAtomicSection() at /var/www/html/includes/libs/rdbms/database/DBConnRef.php:103
Wikimedia\Rdbms\DBConnRef -> __call() at /var/www/html/includes/libs/rdbms/database/DBConnRef.php:665
Wikimedia\Rdbms\DBConnRef -> doAtomicSection() at /var/www/html/includes/deferred/AtomicSectionUpdate.php:39
AtomicSectionUpdate -> doUpdate() at /var/www/html/includes/deferred/DeferredUpdates.php:474
DeferredUpdates -> attemptUpdate() at /var/www/html/includes/deferred/DeferredUpdates.php:399
DeferredUpdates -> run() at /var/www/html/includes/deferred/DeferredUpdates.php:214
DeferredUpdates -> {closure}() at /var/www/html/includes/deferred/DeferredUpdatesScope.php:267
DeferredUpdatesScope -> processStageQueue() at /var/www/html/includes/deferred/DeferredUpdatesScope.php:196
DeferredUpdatesScope -> processUpdates() at /var/www/html/includes/deferred/DeferredUpdates.php:206
DeferredUpdates -> doUpdates() at /var/www/html/includes/MediaWiki.php:675
MediaWiki -> preOutputCommit() at /var/www/html/includes/MediaWiki.php:640
MediaWiki -> doPreOutputCommit() at /var/www/html/includes/MediaWiki.php:917
MediaWiki -> main() at /var/www/html/includes/MediaWiki.php:562
MediaWiki -> run() at /var/www/html/index.php:50
Global -> wfIndexMain() at /var/www/html/index.php:46

[10:28:22] trigger render()
MediaWiki\Extension\QuickMMD\Hook -> getCleanStack() at /var/www/html/extensions/QuickMMD/src/Hook.php:92
MediaWiki\Extension\QuickMMD\Hook -> render() at /var/www/html/includes/parser/Parser.php:4019
Parser -> extensionSubstitution() at /var/www/html/includes/parser/PPFrame_Hash.php:352
PPFrame_Hash -> expand() at /var/www/html/includes/parser/Parser.php:2953
Parser -> replaceVariables() at /var/www/html/includes/parser/Parser.php:1609
Parser -> internalParse() at /var/www/html/includes/parser/Parser.php:723
Parser -> parse() at /var/www/html/includes/content/WikitextContentHandler.php:301
WikitextContentHandler -> fillParserOutput() at /var/www/html/includes/content/ContentHandler.php:1720
ContentHandler -> getParserOutput() at /var/www/html/includes/content/Renderer/ContentRenderer.php:47
MediaWiki\Content\Renderer\ContentRenderer -> getParserOutput() at /var/www/html/includes/Revision/RenderedRevision.php:265
MediaWiki\Revision\RenderedRevision -> getSlotParserOutputUncached() at /var/www/html/includes/Revision/RenderedRevision.php:237
MediaWiki\Revision\RenderedRevision -> getSlotParserOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:221
MediaWiki\Revision\RevisionRenderer -> combineSlotOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:158
Global -> call_user_func() at /var/www/html/includes/Revision/RenderedRevision.php:199
MediaWiki\Revision\RenderedRevision -> getRevisionParserOutput() at /var/www/html/includes/Storage/DerivedPageDataUpdater.php:1438
MediaWiki\Storage\DerivedPageDataUpdater -> getCanonicalParserOutput() at /var/www/html/includes/Storage/PageEditStash.php:167
MediaWiki\Storage\PageEditStash -> parseAndCache() at /var/www/html/includes/api/ApiStashEdit.php:200
ApiStashEdit -> execute() at /var/www/html/includes/api/ApiMain.php:1903
ApiMain -> executeAction() at /var/www/html/includes/api/ApiMain.php:878
ApiMain -> executeActionWithErrorHandling() at /var/www/html/includes/api/ApiMain.php:849
ApiMain -> execute() at /var/www/html/api.php:90
Global -> wfApiMain() at /var/www/html/api.php:45

[10:28:24] trigger render()
MediaWiki\Extension\QuickMMD\Hook -> getCleanStack() at /var/www/html/extensions/QuickMMD/src/Hook.php:92
MediaWiki\Extension\QuickMMD\Hook -> render() at /var/www/html/includes/parser/Parser.php:4019
Parser -> extensionSubstitution() at /var/www/html/includes/parser/PPFrame_Hash.php:352
PPFrame_Hash -> expand() at /var/www/html/includes/parser/Parser.php:2953
Parser -> replaceVariables() at /var/www/html/includes/parser/Parser.php:1609
Parser -> internalParse() at /var/www/html/includes/parser/Parser.php:723
Parser -> parse() at /var/www/html/includes/content/WikitextContentHandler.php:301
WikitextContentHandler -> fillParserOutput() at /var/www/html/includes/content/ContentHandler.php:1720
ContentHandler -> getParserOutput() at /var/www/html/includes/content/Renderer/ContentRenderer.php:47
MediaWiki\Content\Renderer\ContentRenderer -> getParserOutput() at /var/www/html/includes/Revision/RenderedRevision.php:265
MediaWiki\Revision\RenderedRevision -> getSlotParserOutputUncached() at /var/www/html/includes/Revision/RenderedRevision.php:237
MediaWiki\Revision\RenderedRevision -> getSlotParserOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:221
MediaWiki\Revision\RevisionRenderer -> combineSlotOutput() at /var/www/html/includes/Revision/RevisionRenderer.php:158
Global -> call_user_func() at /var/www/html/includes/Revision/RenderedRevision.php:199
MediaWiki\Revision\RenderedRevision -> getRevisionParserOutput() at /var/www/html/includes/poolcounter/PoolWorkArticleView.php:91
PoolWorkArticleView -> renderRevision() at /var/www/html/includes/poolcounter/PoolWorkArticleViewCurrent.php:97
PoolWorkArticleViewCurrent -> doWork() at /var/www/html/includes/poolcounter/PoolCounterWork.php:162
PoolCounterWork -> execute() at /var/www/html/includes/page/ParserOutputAccess.php:299
MediaWiki\Page\ParserOutputAccess -> getParserOutput() at /var/www/html/includes/page/Article.php:713
Article -> generateContentOutput() at /var/www/html/includes/page/Article.php:528
Article -> view() at /var/www/html/includes/actions/ViewAction.php:78
ViewAction -> show() at /var/www/html/includes/MediaWiki.php:542
MediaWiki -> performAction() at /var/www/html/includes/MediaWiki.php:322
MediaWiki -> performRequest() at /var/www/html/includes/MediaWiki.php:904
MediaWiki -> main() at /var/www/html/includes/MediaWiki.php:562
MediaWiki -> run() at /var/www/html/index.php:50
Global -> wfIndexMain() at /var/www/html/index.php:46
```