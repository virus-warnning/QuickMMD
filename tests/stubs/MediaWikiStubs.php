<?php
/**
 * MediaWiki core stubs for PHPStan and unit testing.
 * These are minimal definitions to satisfy the type checker
 * without requiring a full MediaWiki installation.
 */

namespace MediaWiki\Extension {

	class ExtensionRegistry {
		/** @var array<string, array> */
		public static $extensions = [];

		public static function loadFromExtension( string $name ): void {
			// stub
		}

		public static function getLoadedExtensions(): array {
			return self::$extensions;
		}
	}
}

namespace MediaWiki\HTMLForm\Field {

	abstract class HTMLFormField {
		/** @var array */
		protected $attributes;

		public function __construct( array $attributes = [], $parent = null ) {
			$this->attributes = $attributes;
		}

		public function getName(): string {
			return (string)($this->attributes['name'] ?? '');
		}
	}

	class HTMLTextField extends HTMLFormField {
		public function setAttribute( string $name, $value ): void {
			$this->attributes[$name] = $value;
		}

		public function loadFromObject( $obj, $field = null, $value = null ): void {
			// stub
		}
	}

	class HTMLSelectField extends HTMLFormField {
		/** @var array */
		public $options = [];

		public function setOptions( array $options ): void {
			$this->options = $options;
		}

		public function setOption( string $key, string $label ): void {
			$this->options[$key] = $label;
		}

		public function loadFromObject( $obj, $field = null, $value = null ): void {
			// stub
		}
	}
}

namespace {

	/**
	 * @param string $key
	 * @param bool|array $parameters
	 * @return object A Message object (simplified)
	 */
	function wfMessage( $key, $parameters = false ) {
		return new class( $key ) {
			private string $key;

			public function __construct( string $key ) {
				$this->key = $key;
			}

			public function param( ...$params ) {
				return $this;
			}

			public function plain(): string {
				return $this->key;
			}

			public function escaped(): string {
				return $this->key;
			}

			public function inLanguage( $lang ) {
				return $this;
			}
		};
	}
}
