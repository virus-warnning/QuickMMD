<?php
/**
 * Mermaid Syntax 快速製圖外掛
 * @author Raymond Wu https://github.com/virus-warnning
 * 
 * 重構
 * TODO: 開始畫之前, 啟動一個 instance 處理, 簡化暫存值
 * TODO: 需要區分 warning 和 error, 讓除錯和改善變簡單些
 * TODO: 需要簡化 showError(), 也許用不到 addError()
 * TODO: showError() 需要標題摘要訊息內容, 並且放在紅框外面方便除錯
 * TODO: 生圖過程值, 可能需要依 id 存放, 確保併發安全
 * 
 * 除錯機制
 * TODO: 各類開發用除錯檔開關
 * 
 * Bug
 * TODO: 檔案更新時間
 * TODO: 轉圖時間
 * TODO: MD5 摘要
 * 
 * UX
 * TODO: 命名必要檢查
 * TODO: 同頁重複命名檢查
 * TODO: theme 容錯與警示
 * TODO: 背景色
 * TODO: 自定義樣式
 * TODO: 滑鼠縮放
 */
class QuickMMD {

	/* mermaid 原始碼內容上限 (10M) */
	const MAX_INPUTSIZE = 1048576;

	/* 自定義 mmdc 路徑 */
	const MMDC_PATH = '';

	/* 自定義 php 路徑 */
	const PHP_PATH = '';

	/* 錯誤訊息暫存區 */
	private static $errmsgs = array();

	/* 版本字串 */
	private static $version = '0.0.0';

	/* mmdc 指令路徑 */
	private static $mmdc_cmd = '';

	/* mmdc 版本 */
	private static $mmdc_ver = '';

	/**
	 * 掛載點設定 (由 MediaWiki 觸發)
	 *
	 * @since 0.1.0
	 * @param $parser MediaWiki 的語法處理器
	 */
	public static function init(&$parser) {
		// 取得版本字串
		self::$version = ExtensionRegistry::getInstance()->getAllThings()['QuickMMD']['version'];

		// 取得 mmdc 指令路徑
		self::$mmdc_cmd = self::findExecutable('mmdc', self::MMDC_PATH);

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
	 * @since 0.1.0
	 * @param $in     MediaWiki 寫的語法內文
	 * @param $param  標籤內的參數
	 * @param $parser MediaWiki 的語法處理器
	 * @param $frame  不知道是啥小
	 */
	public static function render($in, $param=array(), $parser=null, $frame=false) {
		/*
		wfDebugLog( 'mytag', 'Input received: ' . $in );
    	wfDebugLog( 'mytag', 'Args: ' . json_encode( $param ) );

		$parser->getOutput()->updateCacheExpiry(0);

		$out = file_get_contents(__DIR__ . '/stderr.txt');
		$classes = [
			'mw-message-box-error',
			'mw-message-box'
		];
		$styles = [
			'display:inline-block;',
			'margin:0;',
			'overflow:scroll;',
			'max-width:60%;',
			'max-height:250px;',
			'white-space:pre;',
			'border-radius:5px;'
		];
		$msg = sprintf(
			'<pre class="%s" style="%s">%s%s</pre>',
			implode(' ', $classes),
			implode(' ', $styles),
			htmlspecialchars($out),
			"\n" . date("at H:i:s")
		);
		return [ $msg, 'noparse' => true, 'isHTML' => true ];
		*/

		global $IP;           // 檔案系統路徑
		global $wgScriptPath; // HTTP 路徑

		// 計時開始，效能計算用
		$beg_time = microtime(true);

		// 參數檢查
		self::validateParam($param);
		if (count(self::$errmsgs)>0) {
			return self::showError();
		}

		// mmdc 環境檢查
		if (self::$mmdc_cmd=='') return self::showError();

		// PHP 環境檢查
		$phpcmd = self::findExecutable('php', self::PHP_PATH);
		if ($phpcmd=='') return self::showError();

		// $in 上限管制
		if (strlen($in)>self::MAX_INPUTSIZE) {
			$msg = sprintf('Input data exceed %s.', self::getFriendlySize(self::MAX_INPUTSIZE));
			return self::showError($msg);
		}

		// 計算新的摘要，快取處理用
		$sum_curr = md5(json_encode($param).$in);

		// 讀取參數，或是預設值
		$gname = self::getParam($param, 'name' , 'G');
		$theme = self::getParam($param, 'theme', 'default');
		$prefix = $parser->mTitle;
		$prefix = str_replace(array('\\','/',' '), '_', $prefix); // TODO: 搬去 self::getSafeName()

		$imgdir = sprintf('%s/images/quickmmd', $IP);
		if (!is_dir($imgdir)) mkdir($imgdir);

		$fn = self::getSafeName(sprintf('%s-%s', $prefix, $gname));
		$metafile = sprintf('%s/images/quickmmd/%s-meta.json', $IP, $fn);
		$svgfile  = sprintf('%s/images/quickmmd/%s.svg', $IP, $fn);
		$svgurl   = sprintf('%s/images/quickmmd/%s.svg', $wgScriptPath, $fn);
		$html = '';

		// 生成 SVG
		$mtime = microtime(true);
		[$retval, $err] = self::genSvg($in, $svgfile);
		if ($retval === 0) {
			// TODO: 比例不好需要縮放
			$html = sprintf('<div style="display:inline-block;"><img src="%s?t=%d" style="display:block; width:min-content; height:auto; border:1px solid #777;" /></div>', $svgurl, $mtime);
		} else {
			// TODO: 如果能夠變色更好
			self::addError($err);
			return [ self::showError(), 'noparse' => true, 'isHTML' => true ];
		}

		// 生成摘要資訊
		$dump_env = self::getParam($param, 'dump-env', 'false');
		if ($dump_env==='true') {
			$html .= self::genHtmlForEnv($svgfile, $svgurl);
		}

		// 生成原始碼
		$dump_mmd = self::getParam($param, 'dump-mmd' , 'false');
		if ($dump_mmd==='true') {
			if (strlen(trim($in))) $html .= self::genHtmlForSource($in);
		}

		return trim($html);
	}

	/**
	 * 增加錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息
	 */
	private static function addError($msg) {
		self::$errmsgs[] = $msg;
	}

	/**
	 * 顯示錯誤訊息
	 *
	 * @since 0.1.1
	 * @param $msg 錯誤訊息，如果沒有提供，會使用 addError 增加的錯誤訊息
	 */
	private static function showError($msg='') {
		if ($msg==='') {
			if (count(self::$errmsgs)>0) {
				$html = '';
				foreach (self::$errmsgs as $cached_msg) {
					$html .= htmlspecialchars($cached_msg);
				}

				// Clear messages, or graphs after this one will broken.
				self::$errmsgs = array();
			} else {
				$html = "Test";
			}
		} else {
			$html = $msg;
		}

		$classes = [
			'mw-message-box-error',
			'mw-message-box'
		];
		$styles = [
			'display:inline-block;',
			'margin:0;',
			'overflow:scroll;',
			'max-width:60%;',
			'max-height:250px;',
			'white-space:pre;',
			'border-radius:5px;'
		];
		$sucks = sprintf(
			'<pre class="%s" style="%s">%s%s</pre>',
			implode(' ', $classes),
			implode(' ', $styles),
			htmlspecialchars($html),
			"\n" . date("at H:i:s")
		);
		// die($sucks);
		file_put_contents(__DIR__ . '/htmlerr.txt', $sucks);

		return $sucks;
	}

	/**
	 * 檢查參數
	 *
	 * @param $params 設定值組
	 */
	private static function validateParam(&$params) {
		// 正向表列格式清單
		$patterns = array(
			'bool' => '/^(true|false)$/',
			'name' => '/^[\\w_]+$/u',      // 防止符號字元，而且支援中文
		);

		// 驗證失敗時的錯誤訊息
		$descs = array(
			'bool' => 'true or false',
			'name' => 'word characters or underscore',
		);

		// 驗證欄位與格式對應
		$formats = array(
			'name' => 'name',
			'showdot' => 'bool',
			'showmeta' => 'bool',
		);

		foreach ($formats as $prmk => $patk) {
			if (isset($params[$prmk])) {
				$param = $params[$prmk];
				$pattern = $patterns[$patk];
				if (!preg_match($pattern,$param)) {
					// TODO: 之後需要翻譯一下
					$msg = sprintf('Attribute %s=\"%s\" needs %s.', $prmk, $param, $descs[$patk]);
					self::addError($msg);
				}
			}
		}
	}

	/**
	 * 搜尋程式的完整路徑
	 * 如果沒有自定義路徑，使用 which 或是 where 指令搜尋程式完整路徑，
	 * 如果有自定義路徑，則使用自定義路徑，不進行自動搜尋。
	 *
	 * @since  0.2.0
	 * @param  $exec_name   程式名稱
	 * @param  $exec_custom 自定義程式路徑
	 * @return 程式完整路徑
	 */
	private static function findExecutable($exec_name, $exec_custom) {
		if ($exec_custom==='') {
			if (PHP_OS!=='WINNT') {
				$exec_path = exec("which $exec_name");
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
			} else {
				$exec_path = $exec_custom;
			}

			if ($exec_path==='' || !file_exists($exec_path)) {
				if ($exec_name==='dot') $exec_name = 'Graphviz';
				self::addError("$exec_name is not installed.");

				// How to install graphviz
				$os = PHP_OS;
				switch ($os) {
					case 'Darwin':
						$url = 'http://brew.sh';
						self::addError('Run the command to install:');
						self::addError('<blockquote>brew install graphviz</blockquote>');
						self::addError(sprintf('If you didn\'t install Homebrew yet, see <a href=\"%1$s\">%1$s</a>.', $url));
						break;
					case 'WINNT':
						$url = 'http://www.graphviz.org/Download_windows.php';
						self::addError(sprintf('Click here to download installer: <a href=\"%1$s\">%1$s</a>', $url));
						break;
					case 'Linux':
						self::addError('For CentOS users, run the command to install:');
						self::addError('<blockquote>yum install graphviz</blockquote>');
						self::addError('For Ubuntu or Debian users, run the command to install:');
						self::addError('<blockquote>sudo apt-get install graphviz</blockquote>');
						break;
					case 'FreeBSD':
						self::addError('Run the command to install:');
						self::addError('<blockquote>pkg_add -r graphviz</blockquote>');
						break;
				}

				return '';
			}

			if (!is_executable($exec_path)) {
				self::addError("$exec_path is not executable.");
				return '';
			}

			return $exec_path;
		}

		if ($exec_path==='' || !file_exists($exec_path)) {
			if ($exec_name==='dot') $exec_name = 'Graphviz';
			self::addError("$exec_name is not installed.");

			// How to install graphviz
			$os = PHP_OS;
			switch ($os) {
				case 'Darwin':
					$url = 'http://brew.sh';
					self::addError('Run the command to install:');
					self::addError('<blockquote>brew install graphviz</blockquote>');
					self::addError(sprintf('If you didn\'t install Homebrew yet, see <a href=\"%1$s\">%1$s</a>.', $url));
					break;
				case 'WINNT':
					$url = 'http://www.graphviz.org/Download_windows.php';
					self::addError(sprintf('Click here to download installer: <a href=\"%1$s\">%1$s</a>', $url));
					break;
				case 'Linux':
					self::addError('For CentOS users, run the command to install:');
					self::addError('<blockquote>yum install graphviz</blockquote>');
					self::addError('For Ubuntu or Debian users, run the command to install:');
					self::addError('<blockquote>sudo apt-get install graphviz</blockquote>');
					break;
				case 'FreeBSD':
					self::addError('Run the command to install:');
					self::addError('<blockquote>pkg_add -r graphviz</blockquote>');
					break;
			}

			return '';
		}

		if (!is_executable($exec_path)) {
			self::addError("$exec_path is not executable.");
			return '';
		}

		return $exec_path;
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

	/**
	 * shell 執行程式
	 *
	 * @since 0.2.0
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

	private static function genSvg($mmd_syntax, $svgfile)
	{
		// PHP 環境檢查
		$php_cmd = self::findExecutable('php', self::PHP_PATH);
		if ($php_cmd=='') return [-1, self::showError('Cannot find php executable.')];

		// 執行 php, 產生 dot 語法
		$mmd_tpl = sprintf('%s/QuickMMD.template.php', __DIR__);
		$cmd = sprintf(
			'%s %s %s',
			escapeshellarg($php_cmd), // php
			escapeshellarg($mmd_tpl), // $argv[0]
			'forest'
		);
		$retval = self::pipeExec($cmd, $mmd_syntax, $done_syntax, $err, 'utf-8');
		file_put_contents(__DIR__ . '/debug.mmd', $done_syntax);
		if ($retval != 0) return [$retval, $err];

		// 轉檔設定
		$pfile = sprintf('%s/config-puppeteer.json', __DIR__);

		// 執行 mmdc, 產生 svg 圖檔
		$cmd = sprintf('%s -i - -p %s -o %s',
			escapeshellarg(self::$mmdc_cmd),
			escapeshellarg($pfile),
			escapeshellarg($svgfile)
		);
		$retval = self::pipeExec($cmd, $done_syntax, $out, $err, 'utf-8');
		file_put_contents(__DIR__ . '/stdout.txt', $out);
		file_put_contents(__DIR__ . '/stderr.txt', $err);
		return [$retval, $err];
	}

	/**
	 * 生成系統資訊表, dump-env="true" 的時候使用
	 */
	private static function genHtmlForEnv($svgfile, $svguri)
	{
		$size = self::getFriendlySize($svgfile);
		$mtime = filemtime($svgfile);
		$elapsed = 0; // 可能要放上層再傳入
		$sum_curr = md5_file($svgfile); // 可能要放上層再傳入
		$syntax_ref = 'https://mermaid.js.org/intro/syntax-reference.html';
		$about = sprintf(
			'%s - <a href="https://www.mediawiki.org/wiki/Extension:QuickMMD">%s</a>',
			self::$version,
			wfMessage('quickmmd-about')->plain()
		);

		// 表格內資料
		// - 其實忘了為什麼要加 ->plain()
		$table_data = [
			[ 'label' => wfMessage('filepath')->plain(), 'data' => $svguri ],
			[ 'label' => wfMessage('filesize')->plain(), 'data' => $size ],
			[ 'label' => wfMessage('filemtime')->plain(), 'data' => date('Y-m-d H:i:s',$mtime) ],
			[ 'label' => wfMessage('exectime')->plain(), 'data' => $elapsed ],
			[ 'label' => wfMessage('md5sum')->plain(), 'data' => $sum_curr ],
			[ 'label' => wfMessage('mermaid-cli-path')->plain(), 'data' => self::$mmdc_cmd ],
			[ 'label' => wfMessage('mermaid-cli-ver')->plain(), 'data' => self::$mmdc_ver ],
			[ 'label' => wfMessage('mermaid-syntax-ref')->plain(), 'data' => $syntax_ref ],
			[ 'label' => wfMessage('quickmmd-ver')->plain(), 'data' => $about ],
		];

		// 每列生成
		$th_style = 'white-space:nowrap;';
		$td_style = 'text-align:left;';
		$trs = array();
		foreach ($table_data as $entry)	{
			$trs[] = sprintf(
				'<tr><th style="%s">%s</th><td style="%s">%s</td></tr>',
				$th_style, $entry['label'],
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

	private static function genHtmlForSource($mermaid_syntax)
	{
		// 用 <code> 會出問題, 原因還不清楚
	    return sprintf('<pre>%s</pre>', htmlspecialchars($mermaid_syntax));
	}
}
