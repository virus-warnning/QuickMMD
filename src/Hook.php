<?php
/**
 * Mermaid Syntax 快速製圖外掛
 * 
 * @author Raymond Wu https://github.com/virus-warnning
 *
 * !!! 開發注意事項 !!!
 * - 存檔 1 次實際上會觸發 3 次 render(), 所以必須把生成資訊存檔才能在顯示階段觀察
 * 
 * UX
 * TODO: 紀錄轉檔當下資訊
 * TODO: 語言包校正
 * 
 * Refactoring
 * TODO: 消化 __construct 的 TODO 事項
 * 
 * ---------------------------------
 * 
 * Refactoring
 * TODO: $sys_errors 會被 Hook 讀, 被 FileSystemUtils 寫, 需要改善可讀性
 * TODO: 導入 composer.json
 * TODO: 導入 phplint
 * TODO: PSR-4, PSR-12
 * 
 * UX
 * TODO: 同頁重複命名檢查
 * TODO: 背景色
 * TODO: 自定義樣式
 * TODO: 滑鼠縮放
 * TODO: AI 輔助編輯
 */

namespace MediaWiki\Extension\QuickMMD;

use ExtensionRegistry;

class Hook {

    /* 系統環境錯誤訊息 */
    public static $sys_errors = [];

    /* 版本字串 */
    private static $version = '0.0.0';

    /* php 指令路徑 */
    private static $php_cmd = '';

    /* mmdc 指令路徑 */
    private static $mmdc_cmd = '';

    /* mmdc 版本 */
    private static $mmdc_ver = '';

    /**
     * 掛載點設定 (由 MediaWiki 觸發)
     *
     * @param $parser MediaWiki 的語法處理器
     */
    public static function init(&$parser) {
        // 取得版本字串
        self::$version = ExtensionRegistry::getInstance()->getAllThings()['QuickMMD']['version'];

        // 取得 php 指令路徑
        self::$php_cmd = FileSystemUtils::findExecutable('php');

        // 取得 mmdc 指令路徑
        self::$mmdc_cmd = FileSystemUtils::findExecutable('mmdc');

        // 取 mermaid-cli 版本資訊
        // (stdout) 11.16.0
        $cmd = sprintf('%s -V', escapeshellarg(self::$mmdc_cmd));
        self::pipeExec($cmd, '', $out, $err);
        self::$mmdc_ver = $out;

        // 設定函數鉤
        $parser->setHook('quickmmd', [self::class, 'render']);

        return true;
    }

    /**
     * 製圖 (由 MediaWiki 觸發)
     *
     * @param $mmd_syntax MediaWiki 寫的語法內文
     * @param $props      標籤內的屬性
     * @param $parser     MediaWiki 的語法分析器
     * @param $frame      不知道是啥小
     */
    public static function render($mmd_syntax, $props=array(), $parser=null, $frame=false) {
        // 用來檢查存檔一次會觸發幾次 render(), 平常可以關掉
        // $action = sprintf('[%s] trigger render()', date('H:i:s'));
        // FileSystemUtils::dumpDebugFile('actions.txt', $action, FILE_APPEND);
        // FileSystemUtils::dumpDebugFile('actions.txt', self::getCleanStack(), FILE_APPEND);
        // FileSystemUtils::dumpDebugFile('actions.txt', '', FILE_APPEND);

        $hook = new Hook($mmd_syntax, $props, $parser, $frame);
        return [
            $hook->genFullHtml(),
            'noparse' => true,
            'isHTML' => true
        ];
    }

    private static function getCleanStack() {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $cleanStack = [];
        
        foreach ($stack as $trace) {
            // 過濾掉 PHP 內建函數的堆疊，只保留使用者定義的檔案
            if (isset($trace['file']) && strpos($trace['file'], '/vendor/') === false) {
                $cleanStack[] = sprintf(
                    "%s -> %s() at %s:%d",
                    $trace['class'] ?? 'Global',
                    $trace['function'] ?? 'unknown',
                    $trace['file'] ?? 'unknown',
                    $trace['line'] ?? 0
                );
            }
        }
        return implode("\n", $cleanStack);
    }

    /**
     * 生成 Parser Hook <quickmmd>
     */
    public function __construct(
        // <quickmmd>...</quickmmd> 裡面的內容
        private string $mmd_syntax,
        // <quickmmd> 定義的屬性
        private array $props,
        // MediaWiki parser
        private object $parser,
        // 不知道
        private object $frame,
        // 主題
        private string $theme = 'default',
        // SVG 檔案路徑
        private string $svg_file = '',
        // MD5 檔案路徑
        private string $md5_file = '',
        // SVG 檔案修改時間
        private int $svg_mtime = 0,
        // SVG HTTP 絕對路徑 (不含 protocol、server name)
        private string $svg_uri = '',
        // SVG 轉檔消耗時間
        private float $elapsed = 0.0,
        // SVG 開始轉檔時間
        private float $svg_begin = 0.0,
        // SVG 結束轉檔時間
        private float $svg_end = 0.0,
        // SVG 是否成功生成
        private bool $successful = false,
        // 處理過程警示訊息
        private array $warnings = [],
        // 處理過程錯誤訊息
        private array $errors = [],
        //
        private bool $dumpEnv = false,
        //
        private bool $dumpMmd = false,
        //
        private bool $hitCache = false,
        //
        private string $md5_incoming = '',
        //
        private string $md5_existed = '',
        //
        private string $summary_file = '',
    ) {
        global $IP, $wgScriptPath;

        // 暫時測試用
        // $this->warnings = [
        // 	'模擬警示1',
        // 	'模擬警示2',
        // ];
        // $this->errors = [
        // 	'模擬錯誤1',
        // 	'模擬錯誤2',
        // ];

        // 系統環境異常時忽略後續作業
        if (count(self::$sys_errors) > 0) return;

        // 輸入值檢查
        $props['syntax'] = $mmd_syntax;
        $results = Validator::validate($props);
        if (!$results['Passed']) {
            foreach ($results['Fields'] as $fieldResult) {
                if ($fieldResult->ErrorMessage !== '') {
                    $this->errors[] = $fieldResult->ErrorMessage;
                }
            }
            return;
        }

        // 讀取校正值與生成警示訊息
        // TODO: 需要改成 key => value 回傳值, 才能省略 switch
        $gname = 'sucks';
        foreach ($results['Fields'] as $fieldResult) {
            switch($fieldResult->Field) {
                case 'name':
                    $gname = $fieldResult->FilteredValue;
                    break;
                case 'theme':
                    $this->theme = $fieldResult->FilteredValue;
                    break;
                case 'dump-env':
                    $this->dumpEnv = $fieldResult->FilteredValue;
                    break;
                case 'dump-mmd':
                    $this->dumpMmd = $fieldResult->FilteredValue;
                    break;
            }
            if ($fieldResult->WarningMessage !== '') {
                $this->warnings[] = $fieldResult->WarningMessage;
            }
        }

        // 前置作業
        // TODO: 切出另一個 function 處理
        $this->md5_incoming = md5(json_encode($props).$mmd_syntax);
        $prefix = str_replace(array('\\','/',' '), '_', $parser->mTitle); // TODO: 搬去 self::getSafeName()
        $fn = FileSystemUtils::getSafeName(sprintf('%s-%s', $prefix, $gname));
        $this->svg_file = sprintf('%s/images/quickmmd/%s.svg', $IP, $fn);
        $this->md5_file = sprintf('%s/images/quickmmd/%s.md5', $IP, $fn);
        $this->svg_uri  = sprintf('%s/images/quickmmd/%s.svg', $wgScriptPath, $fn);
        $this->summary_file = sprintf('%s/images/quickmmd/%s.json', $IP, $fn);

        // 快取作業
        $this->svg_begin = microtime(true);
        if (ExtensionConstants::ENABLE_SVG_CACHE) {
            // TODO: 重寫 md5 讀取方式
            $this->md5_existed = is_file($this->md5_file) ? file_get_contents($this->md5_file) : '';
            if ($this->md5_incoming === $this->md5_existed) {
                $this->hitCache = true;
                $this->successful = true;
                $this->svg_mtime = filemtime($this->svg_file);
            }
        }

        // SVG 生成
        if (!$this->hitCache) {
            $this->genSvg();
        }
        $this->svg_end = microtime(true);
        $this->elapsed = $this->svg_end - $this->svg_begin;
    }

    /**
     * 輸出 HTML
     */
    public function genFullHtml() {
        // 顯示主要內容
        // - 轉檔成功: 警示訊息 + SVG 
        // - 轉檔失敗: 錯誤訊息
        if ($this->successful) {
            $html = $this->genHtmlOfWarnings();
            FileSystemUtils::dumpDebugFile('html-warnings.txt', $html);
            $html .= sprintf(
                '<img src="%s?t=%d" style="%s" />',
                $this->svg_uri,
                $this->svg_mtime,
                join('', ExtensionConstants::IMG_STYLES),
            );
        } else {
            $html = $this->genHtmlOfErrors();
            FileSystemUtils::dumpDebugFile('html-errors.txt', $html);
        }

        // 顯示環境資訊
        if ($this->dumpEnv) {
            $html .= $this->genHtmlOfEnv();
        }

        // 顯示原始碼
        if ($this->dumpMmd) {
            $html .= $this->genHtmlOfSyntax();
        }

        return $html;
    }

    /**
     * 生成 SVG 圖
     * 
     * - step 1: 組合 Mermaid 語法
     * - step 2: 組合後語法轉換 SVG 圖
     * - 過程中有問題會寫入 $this->errors[]
     */
    private function genSvg() {
        // 執行 php, 產生 dot 語法
        $mmd_tpl = sprintf('%s/../templates/mmd-builder.php', __DIR__);
        $cmd = sprintf(
            '%s %s %s',
            escapeshellarg(self::$php_cmd), // php
            escapeshellarg($mmd_tpl),       // ./QuickMMD.template.php
            $this->theme                    // theme
        );
        $retval = self::pipeExec($cmd, $this->mmd_syntax, $done_syntax, $err, 'utf-8');
        if ($retval !== 0) {
            $this->errors[] = 'Cannot compose mermaid syntax.';
            $this->errors[] = $err;
            return;
        }

        // 寫入語法合併除錯檔
        FileSystemUtils::dumpDebugFile('merged.mmd', $done_syntax);

        // 轉檔設定
        $pfile = sprintf('%s/../config-puppeteer.json', __DIR__);

        // 執行 mmdc, 產生 svg 圖檔
        $cmd = sprintf('%s -q -i - -p %s -o %s',
            escapeshellarg(self::$mmdc_cmd),
            escapeshellarg($pfile),
            escapeshellarg($this->svg_file)
        );
        $retval = self::pipeExec($cmd, $done_syntax, $out, $err, 'utf-8');
        if ($retval !== 0) {
            // 先把 stdout 的 call stack 過濾掉, 完整 stderr 長這樣
            //
            // Error: Parse error on line 4:
            // ...chart  A1 -- B231
            // --------------------^
            // Expecting 'LINK', 'UNICODE_TEXT', 'EDGE_TEXT', got '1'
            // Parser.parseError (https://mermaid-cli-intercept.invalid/ ...
            //     at #evaluate (file:///usr/lib/node_modules/@mermaid-js/ ...
            //     at async ExecutionContext.evaluate (file:///usr/lib/ ...
            $stack_pos = strpos($err, 'Parser.parseError (https://');
            $key_message = trim(substr($err, 0, $stack_pos));
            $this->errors[] = sprintf('Cannot convert SVG by mmdc. (retval=%d)', $retval);
            $this->errors[] = sprintf('Shell command: %s', $cmd);
            $this->errors[] = $key_message;
            return;
        }

        // 成功時寫入摘要檔
        // TODO: 補充要寫入的東西
        $summary = [
            'md5' => $this->md5_incomoing,
            'elapsed' => $this->elapsed,
        ];
        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $summary_ser = json_encode($summary, $json_options);
        file_put_contents($this->summary_file, $summary_ser);

        // 寫入 mmdc 執行結果除錯檔
        FileSystemUtils::dumpDebugFile('mmdc-out.mmd', $out);
        FileSystemUtils::dumpDebugFile('mmdc-err.mmd', $err);

        $this->svg_mtime = filemtime($this->svg_file);
        $this->successful = ($retval === 0);
    }

    /**
     * 生成系統資訊表, dump-env="true" 的時候使用
     * 
     * @return string 系統資訊表的 HTML 原始碼
     */
    private function genHtmlOfEnv()
    {
        // 資料格式處理
        $size = FileSystemUtils::getFriendlySize($this->svg_file);
        $elapsed = sprintf('%.2f %s', $this->elapsed, wfMessage('quickmmd-sec')->plain());
        $syntax_ref = 'https://mermaid.js.org/intro/syntax-reference.html';
        $about = sprintf(
            '%s - <a href="https://www.mediawiki.org/wiki/Extension:QuickMMD">%s</a>',
            self::$version,
            wfMessage('quickmmd-about')->plain()
        );

        $mtime_str = 'N/A';
        if ($this->svg_mtime > 0) {
            $time_diff = microtime(true) - $this->svg_mtime;
            $mtime_str = ($time_diff > 86400) ? date('Y-m-d', $this->svg_mtime): date('H:i:s', $this->svg_mtime);
        }

        // 表格內資料
        $table_data = [
            [ 'label' => 'filepath'          , 'data' => $this->svg_uri ],
            [ 'label' => 'filesize'          , 'data' => $size ],
            [ 'label' => 'filemtime'         , 'data' => $mtime_str ],
            // TODO: 加了快取機制這裡很容易變成 0, 原因不明
            [ 'label' => 'exectime'          , 'data' => $elapsed ],
            [ 'label' => 'exectime'          , 'data' => $this->svg_begin ],
            [ 'label' => 'exectime'          , 'data' => $this->svg_end ],
            [ 'label' => 'hit-cache'         , 'data' => $this->hitCache ? 'Yes' : 'No'],
            [ 'label' => 'md5-path'          , 'data' => $this->md5_file ],
            [ 'label' => 'md5-incoming'      , 'data' => $this->md5_incoming ],
            [ 'label' => 'md5-existed'       , 'data' => $this->md5_existed ],
            [ 'label' => 'mermaid-cli-path'  , 'data' => self::$mmdc_cmd ],
            [ 'label' => 'mermaid-cli-ver'   , 'data' => self::$mmdc_ver ],
            [ 'label' => 'mermaid-syntax-ref', 'data' => $syntax_ref ],
            [ 'label' => 'quickmmd-ver'      , 'data' => $about ],
        ];

        // 每列生成
        $th_style = 'white-space:nowrap;';
        $td_style = 'text-align:left;';
        $trs = array();
        foreach ($table_data as $entry)	{
            $trs[] = sprintf(
                '<tr><th style="%s">%s</th><td style="%s">%s</td></tr>',
                $th_style, wfMessage($entry['label'])->plain(),
                $td_style, $entry['data']
            );
        }
        
        // 表格生成
        $table_html = sprintf(
            '<table class="wikitable" style="width:600px; margin:5px 0 0 0;"><tbody>%s</tbody></table>',
            implode("\n", $trs)
        );
        return $table_html;
    }

    /**
     * 生成語法顯示訊息, 會顯示合併後的完整 Mermaid 語法
     * 
     * @return string 語法顯示訊息的 HTML 原始碼
     */
    private function genHtmlOfSyntax()
    {
        $trimmed = trim($this->mmd_syntax);
        if ($trimmed !== '') {
            // 目前 Pygments 還不相容 mermaid, 等以後可以了再解開
            // $wikiText = sprintf('<syntaxhighlight lang="mermaid">%s</syntaxhighlight>', $trimmed);
            // return $this->parser->recursiveTagParseFully($wikiText);

            // 先用 lang="text" 頂著
            $wikiText = sprintf('<syntaxhighlight lang="text" line>%s</syntaxhighlight>', $trimmed);
            return $this->parser->recursiveTagParseFully($wikiText);
        } else {
            return '';
        }
    }

    /**
     * 生成錯誤訊息, 僅生成失敗時會出現
     * 
     * @return string 生成錯誤/警示訊息的 HTML 原始碼
     */
    private function genHtmlOfErrors() {
        $errors = array_merge(self::$sys_errors, $this->errors);
        return $this->genHtmlOfLogs('error', $errors);
    }

    /**
     * 生成警示訊息, 僅生成成功時會出現在 SVG 上方
     * 
     * @return string 生成錯誤/警示訊息的 HTML 原始碼
     */
    private function genHtmlOfWarnings() {
        return $this->genHtmlOfLogs('warning', $this->warnings);
    }

    /**
     * 生成錯誤/警示訊息的共用部分
     * 
     * @param  string $level error | warning
     * @param  array  $logs  錯誤或警示訊息
     * @return string 生成錯誤/警示訊息的 HTML 原始碼
     */
    private static function genHtmlOfLogs($level, $logs) {
        if (count($logs) === 0) return '';
        $classes = [
            "mw-message-box-$level",
            'mw-message-box'
        ];
        return sprintf(
            '<pre class="%s" style="%s">%s</pre>',
            join(' ', $classes),
            join(' ', ExtensionConstants::LOGGING_STYLES),
            htmlspecialchars(join("\n", $logs))
        );
    }

    /**
     * shell 執行程式
     *
     * @param  string $cmd      執行的 shell 指令
     * @param  string $stdin    輸入給指令的內容
     * @param  string $stdout   指令標準輸出內容
     * @param  string $stderr   指令標準錯誤內容
     * @param  string $encoding 指令標準輸出/標準錯誤的文字編碼, 預設自動偵測
     * @return int    回傳錯誤碼, 0 表示正常結束
     */
    private static function pipeExec($cmd, $stdin='', &$stdout='', &$stderr='', $encoding='sys') {
        static $sys_encoding = '';

        if ($encoding==='sys') {
            // detect system encoding once
            if ($sys_encoding==='') {
                if (PHP_OS==='WINNT') {
                    // for Windows
                    $lastln = exec('chcp', $stdout, $retval);
                    if ($retval===0) {
                        $ok = preg_match('/: (\d+)$/', $lastln, $matches);
                        if ($ok===1) $sys_encoding = sprintf('cp%d', (int)$matches[1]);
                    }
                } else {
                    // for Linux / OSX / BSD
                    // TODO: ...
                }

                if ($sys_encoding==='') $sys_encoding = 'utf-8';
            }

            // apply system encoding
            $encoding = $sys_encoding;
        }

        // pipe all streams
        $desc = array(
            array('pipe', 'r'), // stdin
            array('pipe', 'w'), // stdout
            array('pipe', 'w')  // stderr
        );

        // run the command
        if (PHP_OS==='WINNT') $cmd = sprintf('"%s"', $cmd); // hack for windows
        $proc = proc_open($cmd, $desc, $pipes);
        if (is_resource($proc)) {
            $encoding = strtolower($encoding);

            // feed stdin
            if ($encoding!=='utf-8') {
                $stdin = iconv('utf-8', $encoding, $stdin);
            }
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);

            // read stdout
            $stdout = stream_get_contents($pipes[1]);
            if ($encoding!=='utf-8') {
                $stdout = iconv($encoding, 'utf-8', $stdout);
            }
            fclose($pipes[1]);

            // read stderr
            $stderr = stream_get_contents($pipes[2]);
            if ($encoding!=='utf-8') {
                $stderr = iconv($encoding, 'utf-8', $stderr);
            }
            fclose($pipes[2]);

            $retval = proc_close($proc);
        } else {
            $retval = -1;
        }

        return $retval;
    }
    
}
