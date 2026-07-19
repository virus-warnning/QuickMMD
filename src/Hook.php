<?php
/**
 * Mermaid Syntax 快速製圖外掛
 * @author Raymond Wu https://github.com/virus-warnning
 * 
 * Features
 * TODO: 輸入值合理性檢查後的錯誤訊息整理與輸出
 * TODO: 可自動修復項目標註 warnings
 * 
 * Refactoring
 * TODO: 非核心邏輯分離, 簡化 Hook.php
 * TODO: function 註解
 * 
 * UX
 * TODO: 檔案更新時間 (非當日顯示日期)
 * TODO: 檔案更新時間 (當日只顯示時間)
 * TODO: 命名必要性檢查
 * TODO: theme 容錯與警示
 * 
 * ---------------------------------
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

	/* 是否啟用除錯過程檔 */
	const DUMP_DEBUG_FILES = true;

	/* mermaid 原始碼內容上限 (10M) */
	const MAX_INPUTSIZE = 1048576;

	/* 資料格式檢查方式 */
	const PROPERTIES_DESCRIPTOR = [
		'name' => [
			'type' => 'text',      // HTMLForm 的基本文字輸入使用 'text'
			'required' => true,    // 標記為必要欄位，沒傳或傳空值 validate() 就會失敗
		],
		'theme' => [
			'type' => 'select',    // 下拉選單或嚴格選項驗證使用 'select'
			'options' => [ 
				// 格式為：'顯示名稱 (Label)' => '實際值 (Value)'
				'預設 (Default)' => 'default',
				'深色 (Dark)'   => 'dark',
				'森林 (Forest)' => 'forest',
				'中性 (Neutral)'=> 'neutral',
				'基礎 (Base)'   => 'base',
			],
			'default' => 'default',
		],
		'dump-env' => [
			'type' => 'bool',
			'default' => false
		],
		'dump-mmd' => [
			'type' => 'bool',
			'default' => false
		],
	];

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

	/* 系統環境錯誤訊息 */
	private static $sys_errors = [];

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
		self::$php_cmd = self::findExecutable('php');

		// 取得 mmdc 指令路徑
		self::$mmdc_cmd = self::findExecutable('mmdc');

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
		// TODO: 這段斟酌解開
		/*
		wfDebugLog( 'mytag', 'Input received: ' . $mmd_syntax );
    	wfDebugLog( 'mytag', 'Args: ' . json_encode( $props ) );
		$parser->getOutput()->updateCacheExpiry(0);
		*/
		return [
			new self($mmd_syntax, $props, $parser, $frame),
			'noparse' => true,
			'isHTML' => true
		];
	}

	/**
	 * 
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
		// SVG 檔案修改時間
		private int $svg_mtime = 0,
		// SVG HTTP 絕對路徑 (不含 protocol、server name)
		private string $svg_uri = '',
		// SVG 轉檔消耗時間
		private float $elapsed = 0.0,
		// SVG 是否成功生成
		private bool $successful = false,
		// MD5 checksum 檢查是否改過
		private string $checksum = '',
		// 處理過程警示訊息
		private array $warnings = [],
		// 處理過程錯誤訊息
		private array $errors = [],
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
		// TODO: 切出另一個 function 處理
		// $mmd_syntax 上限管制
		if (strlen($mmd_syntax) > self::MAX_INPUTSIZE) {
			$msg = sprintf('Input data exceed %s.', self::getFriendlySize(self::MAX_INPUTSIZE));
			return self::showError($msg);
		}
		Validator::validate($props);

		// 前置作業
		// TODO: 切出另一個 function 處理
		$this->checksum = md5(json_encode($props).$mmd_syntax);
		$this->theme = 'forest';
		$prefix = str_replace(array('\\','/',' '), '_', $parser->mTitle); // TODO: 搬去 self::getSafeName()
		$gname = 'sucks';
		$fn = self::getSafeName(sprintf('%s-%s', $prefix, $gname));
		$this->svg_file = sprintf('%s/images/quickmmd/%s.svg', $IP, $fn);
		$this->svg_uri  = sprintf('%s/images/quickmmd/%s.svg', $wgScriptPath, $fn);

		// 快取作業
		// TODO

		// SVG 生成
		$this->genSvg();
	}

	/**
	 * 顯示內容控制
	 */
	public function __toString()
	{
		// 顯示主要內容
		// - 轉檔成功: 警示訊息 + SVG 
		// - 轉檔失敗: 錯誤訊息
		if ($this->successful) {
			$html = $this->genHtmlOfWarnings();
			self::dumpDebugFile('html-warnings.txt', $html);
			$html .= sprintf(
				'<img src="%s?t=%d" style="%s" />',
				$this->svg_uri,
				$this->svg_mtime,
				join('', self::IMG_STYLES),
			);
		} else {
			$html = $this->genHtmlOfErrors();
			self::dumpDebugFile('html-errors.txt', $html);
		}

		// 顯示環境資訊
		$dump_env = self::getParam($this->props, 'dump-env', 'false');
		if ($dump_env==='true') {
			$html .= $this->genHtmlOfEnv();
		}

		// 顯示原始碼
		$dump_mmd = self::getParam($this->props, 'dump-mmd' , 'false');
		if ($dump_mmd==='true') {
			$html .= $this->genHtmlOfSyntax();
		}

		return $html;
	}

	private function genSvg() {
		$begin = microtime(true);

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
		self::dumpDebugFile('merged.mmd', $done_syntax);

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

		// 寫入 mmdc 執行結果除錯檔
		self::dumpDebugFile('mmdc-out.mmd', $out);
		self::dumpDebugFile('mmdc-err.mmd', $err);

		$this->elapsed = microtime(true) - $begin;
		$this->svg_mtime = filemtime($this->svg_file);
		$this->successful = ($retval === 0);
	}

	/**
	 * 生成系統資訊表, dump-env="true" 的時候使用
	 */
	private function genHtmlOfEnv()
	{
		// 資料格式處理
		$size = self::getFriendlySize($this->svg_file);
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
			[ 'label' => 'exectime'          , 'data' => $elapsed ],
			[ 'label' => 'md5sum'            , 'data' => $this->checksum ],
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

	private function genHtmlOfSyntax()
	{
		// 用 <code> 會出問題, 原因還不清楚
		$trimmed = trim($this->mmd_syntax);
		if ($trimmed !== '') {
			return sprintf('<pre>%s</pre>', htmlspecialchars($trimmed));
		} else {
			return '';
		}
	}

	private function genHtmlOfWarnings() {
		return $this->genHtmlOfLogs('warning', $this->warnings);
	}

	private function genHtmlOfErrors() {
		$errors = array_merge(self::$sys_errors, $this->errors);
		return $this->genHtmlOfLogs('error', $errors);
	}

	private static function genHtmlOfLogs($level, $logs) {
		if (count($logs) === 0) return '';
		$classes = [
			"mw-message-box-$level",
			'mw-message-box'
		];
		return sprintf(
			'<pre class="%s" style="%s">%s</pre>',
			join(' ', $classes),
			join(' ', self::LOGGING_STYLES),
			htmlspecialchars(join("\n", $logs))
		);
	}

	/**
	 * 取得設定值，如果沒提供就使用預設值
	 *
	 * @param  $params  設定值組
	 * @param  $key     設定值名稱
	 * @param  $default 預設值
	 * @return 預期結果
	 */
	private static function getParam(&$params, $key, $default='') {
		if (isset($params[$key])) {
			if (trim($params[$key])!=='') return $params[$key];
		}
		return $default;
	}

	/**
	 * 取得人性化的檔案大小
	 *
	 * @param $size 位元組數
	 */
	private static function getFriendlySize($svgfile) {
		static $unit_ch = array('B','KB','MB');

		$size = file_exists($svgfile) ? filesize($svgfile) : 0;
		$unit_lv = 0;
		while ($size>=1024 && $unit_lv<=2) {
			$size /= 1024;
			$unit_lv++;
		}

		if ($unit_lv==0) {
			return sprintf('%d %s', $size, $unit_ch[$unit_lv]);
		} else {
			return sprintf('%.2f %s', $size, $unit_ch[$unit_lv]);
		}
	}

	/**
	 * shell 執行程式
	 *
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

	/**
	 * 搜尋程式的完整路徑
	 * - 只會在 init() 時呼叫
	 * - Windows 以外的系統用 which 找
	 * - Windows 待研究
	 *
	 * @param  $exec_name 程式名稱
	 * @return 程式完整路徑
	 */
	private static function findExecutable($exec_name) {
		if (PHP_OS !== 'WINNT') {
			// 先嘗試用 which 找看看
			$exec_path = exec("which $exec_name");
			// 不行再去特定 bin 目錄找
			if ($exec_path==='') {
				$search_dirs = array(
					'/usr/bin',
					'/usr/local/bin'
				);
				foreach ($search_dirs as $dir) {
					$p = sprintf('%s/%s',$dir,$exec_name);
					if (file_exists($p)) {
						$exec_path = $p;
						break;
					}
				}
			}
		}

		// 產生沒找到的錯誤訊息
		if ($exec_path === '') {
			self::$sys_errors[] = sprintf('%s not found.', $exec_name);
			return '';
		}

		// 產生有找到但不能執行的錯誤訊息
		if (!is_executable($exec_path)) {
			self::$sys_errors[] = sprintf('%s is not executable.', $exec_name);
			return '';
		}

		return $exec_path;
	}

	private static function dumpDebugFile($file_name, $file_content) {
		if (!self::DUMP_DEBUG_FILES) return;

		$debug_dir = sprintf('%s/../debug', __DIR__);
		if (!is_dir($debug_dir)) {
			mkdir($debug_dir);
		}
		
		$target_file = sprintf('%s/%s', $debug_dir, $file_name);
		file_put_contents($target_file, $file_content);
	}

	/**
	 * 檔名迴避 Windows 不接受的字元
	 *
	 * @param $unsafename
	 */
	private static function getSafeName($unsafename) {
		$safename = '';
		$slen = strlen($unsafename);

		// escape non-ascii chars
		for($i=0;$i<$slen;$i++) {
			$ch = $unsafename[$i];
			$cc = ord($ch);
			if ($cc<32 || $cc>127) {
				$safename .= sprintf('x%02x',$cc);
			} else {
				$safename .= $ch;
			}
		}

		return $safename;
	}
	
}
