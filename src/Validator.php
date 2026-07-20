<?php
/**
 * 資料檢查
 * 
 * 資料檢查方式原則上走 MediaWiki 生態系內建機制
 * 不過下列幾項內建機制做不到, 需要自己補足
 * 
 * - 字串長度檢查
 * - bool 字串檢查
 * - bool 字串轉 bool 值
 */

namespace MediaWiki\Extension\QuickMMD;

use Html;
use MediaWiki\MediaWikiServices;

class Validator {

    /* Mermaid 語法內容上限 (1M) */
    const MAX_SYNTAX_SIZE = 1048576;

    /* 命名最短字數 */
    const NAME_LBOUND = 2;

    /* 命名最長字數 */
    const NAME_UBOUND = 25;

    /**
     * 各欄位資料限制定義
     */
    public static function getDescriptor() {
        $boolSpec = [
            'type' => 'bool',
            'options' => [ 
                'true' => 'true',
                'false' => 'false',
            ],
            'default' => 'false',
            'filter-callback' => function ($value) {
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        ];
        return [
            'syntax' => [
                'type' => 'text',
                'validation-callback' => function ($value) {
                    if (mb_strlen($value, 'UTF-8') > self::MAX_SYNTAX_SIZE) {
                        return false;
                    }
                    return true;
                }
            ],
            'name' => [
                'type' => 'text',
                'validation-callback' => function ($value) {
                    $slen = mb_strlen($value, 'UTF-8');
                    if ($slen < self::NAME_LBOUND || $slen > self::NAME_UBOUND) {
                        return false;
                    }
                    return true;
                }
            ],
            'theme' => [
                'type' => 'select',
                'options' => [ 
                    'Default' => 'default',
                    'Dark'    => 'dark',
                    'Forest'  => 'forest',
                    'Neutral' => 'neutral',
                    'Base'    => 'base',
                ],
                'default' => 'default',
            ],
            'dump-env' => $boolSpec,
            'dump-mmd' => $boolSpec,
        ];
    }

    /**
     * 生成套版後的錯誤/警示訊息
     */
    public static function buildValidationMessage($fieldName, $fieldSpec) {
        $langKey = sprintf('validation-%s', $fieldName);
        $template = wfMessage($langKey)->plain();
        $message = '';
        switch ($langKey) {
            case 'validation-syntax':
                $message = sprintf($template, self::MAX_SYNTAX_SIZE);
                break;
            case 'validation-name':
                $message = sprintf($template, self::NAME_LBOUND, self::NAME_UBOUND);
                break;
            case 'validation-theme':
                $values = array_values($fieldSpec['options']);
                $message = sprintf($template, join(', ', $values));
                break;
            default:
                $message = $template;
        }
        return $message;
    }

    /**
     * 檢查所有欄位的資料
     */
    public static function validate(array $rawArgs) {
        $descriptor = self::getDescriptor();
        $passed = true;
        $fieldResults = [];

        // 1.39+ 安全對照表：手動對應型態到實體類別（徹底擺脫 MediaWiki 內部工廠）
        $typeToClassMap = [
            'text'   => \HTMLTextField::class,
            'select' => \HTMLSelectField::class,
            'bool'   => \HTMLSelectField::class,
        ];

        foreach ( $descriptor as $name => $fieldSpec ) {
            $fieldSpec['name'] = $name;
            $fieldSpec['fieldname'] = $name;
            
            // 🎯 自製超輕量安全工廠，直接 new 物件
            $type = $fieldSpec['type'];
            if ( !isset( $typeToClassMap[$type] ) ) {
                $result = FieldResult::CreateError($name, "系統錯誤：不支援的驗證型態 [{$type}]");
                $fieldResults[] = $result;
                $passed = false;
                continue;
            }
            
            $className = $typeToClassMap[$type];
            $field = new $className( $fieldSpec );
            $rawValue = $rawArgs[$name] ?? null;
            $validationResult = $field->validate( $rawValue, $rawArgs );
            
            if ( $validationResult !== true ) {
                $langKey = sprintf('validation-%s', $name);
                if (isset($fieldSpec['default'])) {
                    $message = self::buildValidationMessage($name, $fieldSpec);
                    $result = FieldResult::CreateWarning($name, $fieldSpec['default'], $message);
                    $fieldResults[] = $result;
                } else {
                    $message = self::buildValidationMessage($name, $fieldSpec);
                    $result = FieldResult::CreateError($name, $message);
                    $fieldResults[] = $result;
                    $passed = false;
                }
            } else {
                $result = FieldResult::CreateOkay($name, $field->filter( $rawValue, $rawArgs ));
                $fieldResults[] = $result;
            }            
        }

        return [
            'Passed' => $passed,
            'Fields' => $fieldResults
        ];
    }
}