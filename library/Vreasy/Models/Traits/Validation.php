<?php

namespace Vreasy\Models\Traits;

use Valitron\Validator;

trait Validation
{
    private $_validator;
    private $_rules = [];
    private $_errors = [];

    // TODO: This trait needs a real
    protected function validator($data = [], $reset = false)
    {
        if ($this->_validator && !$reset) {
            return $this->_validator;
        } else {
            // FIXME: How to decouple the fields sent to validate from the knowledge
            // of being a Base object, and having to rely on the $this->attributes,
            // withouth being force to call the parent::constructor
            // on each concrete class implemetation?
            return $this->_validator = new Validator($data ?: $this->attributes());
        }
    }

    public function validates($rule, $fields, $params = null, $message = null)
    {
        $this->_rules[] = [$rule, $fields, $params, $message];
    }

    public function isValid($data = [])
    {
        $validator = $this->validator($data, true);
        foreach ($this->_rules as $value) {
            // Unfold and suppress errors because $params could not be there
            @list($rule, $attr, $params, $message) = $value;
            $validator->rule($rule, $attr, $params);
            if ($message) {
                $validator->message((string)$message);
            }
        }
        return $validator->validate() && !$this->_errors;
    }

    public function errors()
    {
        return ($v = $this->validator())
            ? array_merge_recursive($this->_errors, $v->errors())
            : $this->_errors;
    }

    public function addError($key, $text)
    {
        $this->_errors[$key][] = $text;
        return $this;
    }

    public function resetValidation()
    {
        $this->_errors = [];
    }
}
