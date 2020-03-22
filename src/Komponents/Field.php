<?php

namespace Kompo\Komponents;

use Kompo\Core\ValidationManager;
use Kompo\Form;
use Kompo\Komponents\Traits\DoesFormSubmits;
use Kompo\Komponents\Traits\EloquentField;
use Kompo\Eloquent\ModelManager;
use Kompo\Utilities\Arr;
use Kompo\Utilities\Str;

abstract class Field extends Komponent
{
    use EloquentField, DoesFormSubmits;

    public $menuComponent = 'Field';


    protected $defaultTrigger = 'change';

    /**
     * The field's HTML attribute in the form (also the formData key).
     *
     * @var string
     */
    public $name;
    
    /**
     * The field's value.
     *
     * @var string|array
     */
    public $value;

    /**
     * The field's placeholder.
     *
     * @var string|array
     */
    public $placeholder;

    /**
     * The field's sluggable column.
     *
     * @var string|false
     */
    protected $slug = false;

    /**
     * Additional attributes to fill when saving the model's attribute.
     * 
     * @var array
     */
    protected $extraAttributes;


    /**
     * The field's config for internal usage. Contains submit handling configs, field relation to model, etc...
     *
     * @var array
     */
    protected $_kompo = [
        'eloquent' => [
            'ignoresModel' => false, //@var bool Doesn't interact with model on display or submit.
            'doesNotFill' => false,  //@var bool Gets the model's value on display but does not on submit.
        ]
    ];

    /**
     * Initializes a Field component.
     *
     * @param  string $label
     * @return void
     */
    protected function vlInitialize($label)
    {
        parent::vlInitialize($label);

        $this->name = Str::snake($label); //not $this->label because it could be already translated

    }

    /**
     * Appends <a href="https://laravel.com/docs/master/validation#available-validation-rules" target="_blank">Laravel input validation rules</a> for the field.
     *
     * @param  string|array $rules A | separated string of validation rules or Array of rules.
     * @return self
     */
    public function rules($rules)
    {
        return ValidationManager::setFieldRules($rules, $this);
    }

    /**
     * Sets the name for the field corresponding the attribute it will fill.
     *
     * @param  string|array $name The name attribute of the field.
     * 
     * @return self
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Sets the value of the field. 
     * <u>Note</u>: if the Form is connected to an Eloquent Model, the DB value takes precedence.
     *
     * @param  string|array $value The value to be set.
     * @return self
     */
    public function value($value)
    {
        $this->setValue($value);
        return $this;
    }

    /**
     * Sets the value directly.
     *
     * @param  string|array $value
     * @return void
     */
    protected function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * Sets the placeholder of this field. 
     * By default, the fields have no placeholder.
     *
     * @param  string $placeholder The placeholder for the field.
     * @return self
     */
    public function placeholder($placeholder)
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Sets a default value to the field. Applies if the value is empty.
     *
     * @param  string $defaultValue The default value
     * @return self
     */
    public function default($defaultValue)
    {
        if($this->pristine())
            $this->setValue($defaultValue);
        return $this;
    }

    /**
     * Determines if the field has a value or is pristine.
     *
     * @return Boolean
     */
    public function pristine()
    {
        return $this->value ? false : true;
    }

    /**
     * Adds a slug in the table from this field. For example, this will populate the `title` column with the field's value and the `slug` column with it's corresponding slug. 
     * <php>Input::form('Title')->sluggable('slug')</php>
     *
     * @param  string|null $slugColumn The name of the column that contains the slug
     * @return self
     */
    public function sluggable($slugColumn = 'slug')
    {
        $this->slug = $slugColumn;
        return $this;
    }

    /**
     * Sets a required (&#42;) indicator and adds a required validation rule to the field.
     * 
     * @param string|null The required indicator Html. The default is (&#42;).
     *
     * @return self
     */
    public function required($indicator = '*')
    {
        $this->data(['required' => $indicator]);
        $this->rules('required');
        return $this;
    }

    /**
     * Makes the field readonly (not editable).
     *
     * @return self
     */
    public function readOnly()
    {
        return $this->data(['readOnly' => true]);
    }

    /**
     * Checks if the field is readonly (not editable).
     *
     * @return Boolean
     */
    protected function isReadOnly()
    {
        return $this->data('readOnly');
    }

    /**
     * Removes the browser's default autocomplete behavior from the field.
     *
     * @return self
     */
    public function noAutocomplete()
    {
        return $this->data(['noAutocomplete' => true]);
    }
    
    /**
     * This specifies extra attributes (constant columns/values) to add to the model.
     *
     * @param      array  $attributes  Constant columns/values pairs (associative array).
     *
     * @return self  
     */
    public function extraAttributes($attributes = [])
    {
        $this->extraAttributes = $attributes;
        return $this;
    }


    /**
     * Internally used to disable the default Vue input wrapper in fields.
     *
     * @return  self
     */
    public function noInputWrapper()
    {
    	return $this->data([
    		'noInputWrapper' => true
    	]);
    }


    /**
     * Passes Form attributes to the component and sets it's value if it is a Field.
     *
     * @return void
     */
    public function prepareForDisplay($komposer)
    {
        ValidationManager::pushFieldRulesToKomposer($this, $komposer);

        $this->setValueFromDB($komposer);

        $this->checkSetReadonly($komposer);
    }

    /**
     * Passes Form attributes to the component and sets it's value if it is a Field.
     *
     * @return void
     */
    public function prepareForSave($komposer)
    {
        $komposer->components[] = $this;
        
        parent::prepareForSave($komposer);

        ValidationManager::pushFieldRulesToKomposer($this, $komposer);
    }

    /**
     * Sets the field value from the Eloquent instance.
     *
     * @return void
     */
    protected function setValueFromDB($komposer)
    {
        if(!($komposer instanceof Form) || !$komposer->model || $this->eloquentConfig('ignoresModel'))
            return;

        Arr::collect($this->name)->each(function($name) use($komposer) {

            list($model, $name) = ModelManager::parseFromFieldName($komposer->model, $name);

            $value = method_exists($this, 'getValueFromModel') ?
                $this->getValueFromModel($model, $name) :
                ModelManager::getValueFromDb($model, $name);

            if($this->shouldCastToArray($model, $name))
                $value = Arr::decode($value);

            method_exists($this, 'prepareValueForFront') ?
                $this->prepareValueForFront($name, $value, $model):
                $this->setValue($value ?: $this->value);
        });
    }

    /**
     * Checks authorization and sets a readonly field if necessary.
     *
     * @return void
     */
    protected function checkSetReadonly($komposer)
    {
        if(config('kompo.smart_readonly_fields') && method_exists($komposer, 'authorize')){

            $authorization = $komposer->authorize();

            Arr::collect($this->name)->each(function($name) {
            
                if(!$authorization || (is_array($authorization) && !in_array($name, $authorization)))
                    $this->readOnly();

            });
        }
    }

    /**
     * Gets the value from the request and fills the attributes of the eloquent record.
     *
     * @param Model $model
     * @return void
     */
    public function fillBeforeSave($request, $model)
    {
        if($this->doesNotFillCondition())
            return;

        Arr::collect($this->name)->each(function($name) use($model) {

            if(!request()->has($name))
                return;

            list($model, $name) = ModelManager::parseFromFieldName($model, $name);

            if(!ModelManager::fillsBeforeSave($model, $name))
                return;

            if($this->shouldCastToArray($model, $name))
                $model->mergeCasts([$name => 'array']);

            $value = method_exists($this, 'setAttributeFromRequest') ?
                $this->setAttributeFromRequest($name, $model) :
                request()->input($name);

            ModelManager::fillAttribute($model, $name, $value, $this->extraAttributes);
        });
    }

    /**
     * Gets the value from the request and parses it optionally (see methods overrides).
     *
     * @param Illuminate\Http\Request $request
     * @param Illuminate\Database\Eloquent\Model $model
     * 
     * @return void
     */
    public function fillAfterSave($request, $model)
    {

        if($this->doesNotFillCondition())
            return;

        Arr::collect($this->name)->each(function($name) use($model) {

            if(!request()->has($name))
                return;

            list($model, $name) = ModelManager::parseFromFieldName($model, $name);

            if(!ModelManager::fillsAfterSave($model, $name))
                return;

            $value = method_exists($this, 'setRelationFromRequest') ?
                $this->setRelationFromRequest($name, $model) :
                request()->input($name);

            ModelManager::saveAndLoadRelation($model, $name, $value, $this->extraAttributes);
        });
    }

    /**
     * Checks if the field does not fill or is readonly
     *
     * @return     Boolean  
     */
    protected function doesNotFillCondition()
    {
        return $this->eloquentConfig('doesNotFill') || $this->isReadOnly();
    }

    /**
     * Checks if the field deals with array value
     *
     * @return     Boolean  
     */
    protected function shouldCastToArray($model, $name)
    {
        return !$model->hasCast($name) && ($this->castsToArray ?? false);
    }
    
}