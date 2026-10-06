<?php
namespace MediaWiki\Extension\QuickMMD;

class FieldResult {
	public function __construct(
		public string $Field,
		public mixed $FilteredValue,
		public string $ErrorMessage,
		public string $WarningMessage,
	) {
	}

	/**
	 * 
	 */
	public static function createOkay( $field_name, $value ) {
		return new self( $field_name, $value, '', '' );
	}

	/**
	 * 
	 */
	public static function createWarning( $field_name, $value, $warning_message ) {
		return new self( $field_name, $value, '', $warning_message );
	}

	/**
	 * 
	 */
	public static function createError( $field_name, $error_message ) {
		return new self( $field_name, null, $error_message, '' );
	}
}
