<?php

namespace Kompo\Core;


class ValidationManager
{
    /**
     * Sets the field validation rules.
     *
     * @param      <type>  $field  The field
     * @param      <type>  $rules  The rules
     *
     * @return     <type>  ( description_of_the_return_value )
     */
    public static function setFieldRules($rules, $field)
    {
        $rules = is_array($rules) && array_key_exists($field->name, $rules) ? $rules : [$field->name => $rules];

        return static::setRules($rules, $field);
    }

    /**
     * Appends validation rules to the Komposer.
     *
     * @param  array  $rules  The validation rules array.
     *
     * @return void
     */
	public static function addRulesToKomposer($rules, $komposer)
	{
        return static::setRules($rules, $komposer);
	}

    /**
     * Pushes field rules to the Komposer if they were set on the field directly.
     *
     * @param      <type>  $field  The field
     * @param      <type>  $komposer   The form
     */
    public static function pushFieldRulesToKomposer($field, $komposer)
    {
        if($field->data('rules'))
            static::addRulesToKomposer($field->data('rules'), $komposer);
    }


    /**** PRIVATE ****/

    private static function setRules($rules, $el)
    {
        return $el->data([
            'rules' => static::mergeRules($rules, $el->data('rules') ?: [])
        ]);
    }

    private static function mergeRules($rules, $oldRules)
    {
        $results = [];
        foreach ($rules as $attribute => $validations)
        {
            $results[$attribute] = static::mergeAttribute($validations, $oldRules[$attribute] ?? []);
        }
        return array_replace($oldRules, $results);
    }

    private static function mergeAttribute($validations, $oldValidations = [])
    {
        return array_merge($oldValidations, is_string($validations) ? explode('|', $validations) : $validations);
    }
}