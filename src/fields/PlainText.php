<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\SortableFieldInterface;
use craft\helpers\Db;
use craft\helpers\Html;
use LitEmoji\LitEmoji;
use yii\db\Schema;

/**
 * PlainText represents a Plain Text field.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0.0
 */
class PlainText extends Field implements PreviewableFieldInterface, SortableFieldInterface
{
    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('app', 'Plain Text');
    }

    /**
     * @inheritdoc
     */
    public static function valueType(): string
    {
        return 'string|null';
    }

    /**
     * @var string The UI mode of the field.
     * @since 3.5.0
     */
    public string $uiMode = 'normal';

    /**
     * @var string|null The input’s placeholder text
     */
    public ?string $placeholder = null;

    /**
     * @var bool Whether the input should use monospace font
     */
    public bool $code = false;

    /**
     * @var bool Whether the input should allow line breaks
     */
    public bool $multiline = false;

    /**
     * @var int The minimum number of rows the input should have, if multi-line
     */
    public int $initialRows = 4;

    /**
     * @var int|null The maximum number of characters allowed in the field
     */
    public ?int $charLimit = null;

    /**
     * @var int|null The maximum number of bytes allowed in the field
     * @since 3.4.0
     */
    public ?int $byteLimit = null;

    /**
     * @var string|null The type of database column the field should have in the content table
     */
    public ?string $columnType = null;

    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        // Config normalization
        if (isset($config['limitUnit'], $config['fieldLimit'])) {
            if ($config['limitUnit'] === 'chars') {
                $config['charLimit'] = (int)$config['fieldLimit'] ?: null;
            } else {
                $config['byteLimit'] = (int)$config['fieldLimit'] ?: null;
            }
            unset($config['limitUnit'], $config['fieldLimit']);
        }

        foreach (['charLimit', 'byteLimit', 'placeholder', 'columnType'] as $name) {
            if (($config[$name] ?? null) === '') {
                unset($config[$name]);
            }
        }

        if (($config['columnType'] ?? null) === 'auto') {
            unset($config['columnType']);
        }

        // This existed at one point way back in the day.
        unset($config['maxLengthUnit']);

        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();

        if (isset($this->placeholder)) {
            $this->placeholder = LitEmoji::shortcodeToUnicode($this->placeholder);
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        if (isset($settings['placeholder'])) {
            $settings['placeholder'] = LitEmoji::unicodeToShortcode($settings['placeholder']);
        }
        return $settings;
    }

    /**
     * @inheritdoc
     */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['initialRows', 'charLimit', 'byteLimit'], 'integer', 'min' => 1];
        $rules[] = [['charLimit', 'byteLimit'], 'validateFieldLimit'];
        return $rules;
    }

    /**
     * Validates that the Character Limit isn't set to something higher than the Column Type will hold.
     *
     * @param string $attribute
     */
    public function validateFieldLimit(string $attribute): void
    {
        if ($bytes = $this->$attribute) {
            if ($attribute === 'charLimit') {
                $bytes *= 4;
            }
            $columnTypeMax = Db::getTextualColumnStorageCapacity($this->getContentColumnType());
            if ($columnTypeMax && $columnTypeMax < $bytes) {
                $this->addError($attribute, Craft::t('app', 'Field Limit is too big for your chosen Column Type.'));
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('_components/fieldtypes/PlainText/settings',
            [
                'field' => $this,
            ]);
    }

    /**
     * @inheritdoc
     */
    public function getContentColumnType(): string
    {
        if ($this->columnType) {
            return $this->columnType;
        }

        if ($this->byteLimit) {
            $bytes = $this->byteLimit;
        } else if ($this->charLimit) {
            $bytes = $this->charLimit * 4;
        } else {
            return Schema::TYPE_TEXT;
        }

        return Schema::TYPE_STRING . "($bytes)";
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ?ElementInterface $element = null)
    {
        if ($value !== null) {
            $value = LitEmoji::shortcodeToUnicode($value);
            $value = trim(preg_replace('/\R/u', "\n", $value));
        }

        return $value !== '' ? $value : null;
    }

    /**
     * @inheritdoc
     */
    protected function inputHtml($value, ?ElementInterface $element = null): string
    {
        return Craft::$app->getView()->renderTemplate('_components/fieldtypes/PlainText/input', [
            'id' => Html::id($this->handle),
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            [
                'string',
                'max' => $this->byteLimit ?? $this->charLimit ?? null,
                'encoding' => $this->byteLimit ? '8bit' : 'UTF-8',
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ?ElementInterface $element = null)
    {
        if ($value !== null) {
            $value = LitEmoji::unicodeToShortcode($value);
        }
        return $value;
    }

    /**
     * @inheritdoc
     */
    protected function searchKeywords($value, ElementInterface $element): string
    {
        $value = (string)$value;
        return LitEmoji::unicodeToShortcode($value);
    }
}
