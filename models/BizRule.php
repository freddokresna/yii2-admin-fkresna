<?php

namespace mdm\admin\models;

use Yii;
use yii\rbac\Rule;
use mdm\admin\components\Configs;

/**
 * BizRule
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class BizRule extends \yii\base\Model
{
    /**
     * @var string name of the rule
     */
    public $name;

    /**
     * @var integer UNIX timestamp representing the rule creation time
     */
    public $createdAt;

    /**
     * @var integer UNIX timestamp representing the rule updating time
     */
    public $updatedAt;

    /**
     * @var string Rule classname.
     */
    public $className;

    /**
     * @var Rule
     */
    private $_item;

    /**
     * Initialize object
     * @param \yii\rbac\Rule $item
     * @param array $config
     */
    public function __construct($item, $config = [])
    {
        $this->_item = $item;
        if ($item !== null) {
            $this->name = $item->name;
            $this->className = get_class($item);
        }
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['name', 'className'], 'required'],
            [['className'], 'string'],
            [['className'], 'classExists'],
            [['name'], 'checkUniqueName', 'when' => function () {
                    return $this->isNewRecord || ($this->_item->name != $this->name);
                }],
        ];
    }

    /**
     * Validate class exists and is instantiable without constructor arguments
     */
    public function classExists()
    {
        if (!class_exists($this->className)) {
            $message = Yii::t('rbac-admin', "Unknown class '{class}'", ['class' => $this->className]);
            $this->addError('className', $message);
            return;
        }
        if (!is_subclass_of($this->className, Rule::class)) {
            $message = Yii::t('rbac-admin', "'{class}' must extend from 'yii\rbac\Rule' or its child class", [
                    'class' => $this->className]);
            $this->addError('className', $message);
            return;
        }
        // abstract / non-public-constructor classes pass class_exists() but blow
        // up (Error/HTTP 500) the moment save() runs `new $class()`.
        if (!(new \ReflectionClass($this->className))->isInstantiable()) {
            $this->addError('className', Yii::t('rbac-admin', 'Rule class must be instantiable'));
        }
    }

    /**
     * Check rule name is unique among registered rules.
     *
     * Mirrors AuthItem::checkUnique. Creating a rule whose name is already
     * registered — or renaming an existing rule onto a name owned by another
     * rule — must fail validation with an error on 'name' instead of letting
     * DbManager::add()/update() throw an IntegrityException (HTTP 500) on the
     * UNIQUE(auth_rule.name) constraint.
     */
    public function checkUniqueName()
    {
        $authManager = Configs::authManager();
        $value = $this->name;
        if ($authManager->getRule($value) !== null) {
            $message = Yii::t('rbac-admin', '{attribute} "{value}" has already been taken.');
            $params = [
                'attribute' => $this->getAttributeLabel('name'),
                'value' => $value,
            ];
            $this->addError('name', Yii::$app->getI18n()->format($message, $params, Yii::$app->language));
        }
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'name' => Yii::t('rbac-admin', 'Name'),
            'className' => Yii::t('rbac-admin', 'Class Name'),
        ];
    }

    /**
     * Check if new record.
     * @return boolean
     */
    public function getIsNewRecord()
    {
        return $this->_item === null;
    }

    /**
     * Find model by id
     * @param type $id
     * @return null|static
     */
    public static function find($id)
    {
        $item = Configs::authManager()->getRule($id);
        if ($item !== null) {
            return new static($item);
        }

        return null;
    }

    /**
     * Save model to authManager
     * @return boolean
     */
    public function save()
    {
        if ($this->validate()) {
            $manager = Configs::authManager();
            $class = $this->className;
            $oldName = null;
            if ($this->_item === null) {
                try {
                    $this->_item = new $class();
                } catch (\Throwable $e) {
                    $this->addError('className', Yii::t('rbac-admin',
                        "Failed to instantiate rule class '{class}': {reason}", [
                            'class' => $class,
                            'reason' => $e->getMessage(),
                        ]));
                    return false;
                }
                $isNew = true;
            } else {
                $isNew = false;
                $oldName = $this->_item->name;
                // className changed on an existing rule: manager->update() stores
                // whatever instance it is given (serialized into `data`), so the
                // update must run against an instance of the NEW class. Replace
                // the loaded instance with `new $class()` and copy over the public
                // properties that still exist on the new class (rule state/name).
                if (get_class($this->_item) !== $class) {
                    try {
                        $newItem = new $class();
                    } catch (\Throwable $e) {
                        $this->addError('className', Yii::t('rbac-admin',
                            "Failed to instantiate rule class '{class}': {reason}", [
                                'class' => $class,
                                'reason' => $e->getMessage(),
                            ]));
                        return false;
                    }
                    $copyable = array_flip(array_keys(get_object_vars($newItem)));
                    foreach (get_object_vars($this->_item) as $property => $value) {
                        if (isset($copyable[$property])) {
                            $newItem->$property = $value;
                        }
                    }
                    $this->_item = $newItem;
                }
            }
            $this->_item->name = $this->name;

            if ($isNew) {
                $manager->add($this->_item);
            } else {
                $manager->update($oldName, $this->_item);
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Get item
     * @return Item
     */
    public function getItem()
    {
        return $this->_item;
    }
}
