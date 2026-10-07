<?php
namespace MediaWiki\Extension\QuickMMD;

use MediaWiki\MediaWikiServices;

class Command {

	/**
	 * 在 ShellBox 內執行程式
	 *
	 * See:
	 * - https://www.mediawiki.org/wiki/Manual:BoxedCommand
	 * - https://doc.wikimedia.org/mediawiki-core/master/php/classMediaWiki_1_1MediaWikiServices.html
	 * - https://doc.wikimedia.org/mediawiki-core/master/php/classMediaWiki_1_1Shell_1_1CommandFactory.html
	 *
	 * @param array ...$arguments TODO
	 * @return TODO
	 */
	public static function execute( ...$arguments ) {
		$routeName = sprintf( 'quickmmd-%s', basename( $arguments[0] ) );
		return MediaWikiServices::getInstance()
			->getShellCommandFactory()
			->createBoxed( 'quickmmd' )
			->disableNetwork()
			->firejailDefaultSeccomp()
			->routeName( $routeName )
			->params( $arguments )
			->execute();
	}

	/**
	 * Shell 執行程式
	 *
	 * @param string $arguments 執行的 shell 指令
	 * @param string $stdin 輸入給指令的內容
	 * @param string &$stdout 指令標準輸出內容
	 * @param string &$stderr 指令標準錯誤內容
	 * @param string $encoding 指令標準輸出/標準錯誤的文字編碼, 預設自動偵測
	 * @return int 回傳錯誤碼, 0 表示正常結束
	 */
	public static function pipeExec( $arguments, $stdin = '', &$stdout = '', &$stderr = '', $encoding = 'sys' ) {
		FileSystemUtils::dumpDebugFile( 'stdin.txt', $stdin );
		$routeName = sprintf( 'quickmmd-%s', basename( $arguments[0] ) );
		$result = MediaWikiServices::getInstance()
			->getShellCommandFactory()
			->createBoxed( 'quickmmd' )
			->disableNetwork()
			->firejailDefaultSeccomp()
			->routeName( $routeName )
			->params( $arguments )
			->stdin( $stdin )
			->execute();
		$stdout = $result->getStdout();
		$stderr = $result->getStderr();
		return $result->getExitCode();
	}

	/**
	 * 搜尋程式的完整路徑
	 * - 只會在 init() 時呼叫
	 * - Windows 以外的系統用 which 找
	 * - Windows 待研究
	 *
	 * @param string $exec_name 程式名稱
	 * @return string 程式完整路徑
	 */
	public static function findExecutable( $exec_name ) {
		// 先嘗試用 which 找看看
		$result = self::execute( 'which', $exec_name );
		if ( $result->getExitCode() === 0 ) {
			// 這裡的輸出會包含換行字元, 需要過濾
			$exec_path = trim( $result->getStdout() );
		} else {
			$exec_path = '';
		}

		// 不行再去特定 bin 目錄找
		if ( $exec_path === '' ) {
			$search_dirs = [
				'/usr/bin',
				'/usr/local/bin'
			];
			foreach ( $search_dirs as $dir ) {
				$p = sprintf( '%s/%s', $dir, $exec_name );
				if ( file_exists( $p ) ) {
					$exec_path = $p;
					break;
				}
			}
		}

		// 產生沒找到的錯誤訊息
		if ( $exec_path === '' ) {
			$user = posix_getpwuid( posix_geteuid() );
			Hook::$sys_errors[] = sprintf( '%s not found. (user: %s)', $exec_name, $user['name'] );
			return '';
		}

		// 產生有找到但不能執行的錯誤訊息
		if ( !is_executable( $exec_path ) ) {
			Hook::$sys_errors[] = sprintf( '%s (%s) is not executable.', $exec_name, $exec_path );
			return '';
		}

		return $exec_path;
	}

}
