<?php
namespace MediaWiki\Extension\QuickMMD;

class ExtensionConstants {

    /* 是否啟用除錯過程檔 */
    const DUMP_DEBUG_FILES = true;

    /* 是否啟用 SVG 快取 */
    const ENABLE_SVG_CACHE = true;

    /* 插入 SVG 的 <img> 元素樣式 */
    const IMG_STYLES = [
        'width: min-content;',
        'height: auto;',
        'border: 1px solid #aaa;',
        'border-radius: 5px;',
        'padding: 5px;',
    ];

    /* 警示訊息與錯誤訊息的 <pre> 元素樣式 */
    const LOGGING_STYLES = [
        'display: inline-block;',
        'margin: 0;',
        'overflow: scroll;',
        'max-width: 60%;',
        'max-height: 250px;',
        'white-space: pre;',
        'border-radius: 5px;',
        'padding: 10px 15px;',
    ];

}
