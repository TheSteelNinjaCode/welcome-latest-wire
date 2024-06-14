<?php

namespace Lib;

use Lib\StateManager;
use Lib\Validator;

class FormHandler
{
    private $data;
    private $errors;
    private $validated;
    private $isPost;
    private $pathname;
    private StateManager $stateManager;
    private const FORM_STATE = 'pphp_form_state_977A9';
    private const FORM_INPUT_REGISTER = 'pphp_form_input_register_7A16F';
    private const FORM_INPUT_ERRORS = 'pphp_form_input_errors_CBF6C';

    public function __construct($formData = [])
    {
        global $isPost, $pathname;

        $this->isPost = $isPost;
        $this->pathname = $pathname;
        $this->data = $formData;
        $this->errors = [];
        $this->validated = false;

        $this->stateManager = new StateManager();

        if ($this->stateManager->getState(self::FORM_INPUT_REGISTER)) {
            $this->getData();
        }
    }

    /**
     * Validates the form data.
     * 
     * @return bool True if the form data is valid, false otherwise.
     */
    public function validate(): bool
    {
        return empty($this->errors) && $this->validated;
    }

    public function addError($field, $message)
    {
        $this->errors[$field] = $message;
    }

    /**
     * Retrieves the form data and performs validation if the form was submitted.
     *
     * @return mixed An object containing the form data.
     */
    public function getData(): mixed
    {
        if ($this->isPost) {
            if ($inputField = $this->stateManager->getState(self::FORM_INPUT_REGISTER)) {
                foreach ($inputField as $field => $fieldData) {
                    $this->data[$field] = Validator::validateString($this->data[$field] ?? '');
                    $this->validateField($field, $fieldData['rules']);
                }
            }

            $formDataInfo = [
                'data' => $this->data,
                'errors' => $this->errors,
                'validated' => true
            ];

            $this->stateManager->resetState(self::FORM_INPUT_ERRORS, true);
            $this->stateManager->setState([self::FORM_INPUT_ERRORS => $formDataInfo], true);
            $this->stateManager->setState([self::FORM_STATE => $formDataInfo], true);

            redirect($this->pathname);
        } else {
            if ($state = $this->stateManager->getState(self::FORM_STATE)) {
                $this->data = $state['data'] ?? [];
                $this->errors = $state['errors'] ?? [];
                $this->validated = $state['validated'] ?? false;

                $this->stateManager->resetState([self::FORM_STATE, self::FORM_INPUT_REGISTER], true);
            }
        }

        return new \ArrayObject($this->data, \ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Retrieves the validation errors from the form state.
     *
     * This function provides error messages for individual fields or returns all form errors if no specific field is requested.
     * Error messages are wrapped in HTML `<span>` tags with unique IDs to facilitate identification and styling.
     *
     * - If a field name is provided:
     *     - Returns the error message for the specific field as a span element with a unique `id` attribute.
     *     - If no error message is available for the field, returns an empty span element.
     * - If no field name is provided:
     *     - Returns an associative array of all error messages, each wrapped in a span element with a unique `id` attribute.
     *     - If no errors are found, returns an empty array.
     * - If the form has not been validated yet:
     *     - Returns an empty string if a specific field name is provided.
     *     - Returns an empty array if no field name is provided.
     *
     * @param string|null $field The name of the field to retrieve errors for. If null, returns all errors.
     * - Must be a valid string or `null`.
     * - If provided, the resulting span element will have a unique `id` attribute prefixed with "fh-error-".
     * 
     * @param string $class (optional) Additional classes to assign to the error `<span>` element.
     * - Defaults to an empty string.
     * 
     * @return mixed If a field name is provided, returns the error message wrapped in a span or an empty string if no error.
     *               If no field name is provided, returns an associative array of all errors or an empty array if no errors.
     *               If the form has not been validated yet, returns an empty string.
     * 
     * @example
     * Example usage to get a specific field's error message with a custom class:
     * echo $form->getErrors('email', 'form-error');
     * This will generate: "<span class='form-error' id='fh-error-email'>Error message here</span>"
     * 
     * Example usage to get all error messages:
     * print_r($form->getErrors());
     * This will generate an associative array like:
     * [
     *   'email' => "<span class='form-error' id='fh-error-email'>Invalid email</span>",
     *   'username' => "<span class='form-error' id='fh-error-username'>Username too short</span>"
     * ]
     */
    public function getErrors(string $field = null): mixed
    {
        $wrapError = function (string $field, string $message) {
            return "id='fh-error-$field' data-error-message='$message'";
        };

        $field = Validator::validateString($field);
        $state = $this->stateManager->getState(self::FORM_INPUT_ERRORS);

        if ($this->validated && $state) {
            if ($field) {
                $errorState = $state['errors'] ?? [];
                return $wrapError($field, $errorState[$field] ?? '');
            }

            $errors = $state['errors'] ?? [];
            foreach ($errors as $fieldName => $message) {
                $errors[$fieldName] = $wrapError($fieldName, $message);
            }

            return $errors;
        }

        if ($field) {
            $fieldData = $this->data[$field] ?? '';
            return $wrapError($field, $fieldData);
        }

        return [];
    }

    public function clearErrors()
    {
        $this->stateManager->resetState(self::FORM_INPUT_ERRORS, true);
    }

    /**
     * Validates a form field based on the provided rules.
     *
     * @param string $field The name of the field to validate.
     * @param array $rules An associative array of rules to apply. Each key is the rule name, and the value is the rule options.
     * The options can be a scalar value or an array with 'value' and 'message' keys.
     * The 'value' key is the value to compare with, and the 'message' key is the custom error message.
     * 
     * Supported rules:
     * - text, search, email, password, number, date, color, range, tel, url, time, datetime-local, month, week, file
     * - required, min, max, minLength, maxLength, pattern, autocomplete, readonly, disabled, placeholder, autofocus, multiple, accept, size, step, list
     * 
     * Custom error messages can be provided for each rule. If not provided, a default message is used.
     *  
     * @example
     * $form->validateField('email', [
     *   'required' => ['value' => true, 'message' => 'Email is required.'],   
     *   'email' => ['value' => true, 'message' => 'Please enter a valid email address.']
     * ]);
     *
     * @return void
     */
    public function validateField($field, $rules)
    {
        $value = Validator::validateString($this->data[$field] ?? null);

        if (!isset($rules['required']) && empty($value)) {
            return;
        }

        foreach ($rules as $rule => $options) {
            $ruleValue = $options;
            $customMessage = null;

            if (is_array($options)) {
                $ruleValue = $options['value'];
                $customMessage = $options['message'] ?? null;
            }

            switch ($rule) {
                case 'text':
                case 'search':
                    if (!is_string($value)) $this->addError($field, $customMessage ?? 'Must be a string.');
                    break;
                case 'email':
                    if (!filter_var($value, FILTER_VALIDATE_EMAIL)) $this->addError($field, $customMessage ?? 'Invalid email format.');
                    break;
                case 'number':
                    if (!is_numeric($value)) $this->addError($field, $customMessage ?? 'Must be a number.');
                    break;
                case 'date':
                    if (!\DateTime::createFromFormat('Y-m-d', $value)) $this->addError($field, $customMessage ?? 'Invalid date format.');
                    break;
                case 'range':
                    if (!is_numeric($value) || $value < $ruleValue[0] || $value > $ruleValue[1]) $this->addError($field, $customMessage ?? "Must be between $ruleValue[0] and $ruleValue[1].");
                    break;
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) $this->addError($field, $customMessage ?? 'Invalid URL format.');
                    break;
                case 'datetime-local':
                    if (!\DateTime::createFromFormat('Y-m-d\TH:i', $value)) $this->addError($field, $customMessage ?? 'Invalid datetime-local format.');
                    break;
                case 'file':
                    if (!is_uploaded_file($value)) $this->addError($field, $customMessage ?? 'Invalid file format.');
                    break;
                case 'required':
                    if (empty($value)) $this->addError($field, $customMessage ?? 'This field is required.');
                    break;
                case 'min':
                    if ($value < $ruleValue) $this->addError($field, $customMessage ?? "Must be at least $ruleValue.");
                    break;
                case 'max':
                    if ($value > $ruleValue) $this->addError($field, $customMessage ?? "Must be at most $ruleValue.");
                    break;
                case 'minLength':
                    if (strlen($value) < $ruleValue) $this->addError($field, $customMessage ?? "Must be at least $ruleValue characters.");
                    break;
                case 'maxLength':
                    if (strlen($value) > $ruleValue) $this->addError($field, $customMessage ?? "Must be at most $ruleValue characters.");
                    break;
                case 'pattern':
                    if (!preg_match("/$ruleValue/", $value)) $this->addError($field, $customMessage ?? 'Invalid format.');
                    break;
                case 'accept':
                    if (!in_array($value, explode(',', $ruleValue))) $this->addError($field, $customMessage ?? 'Invalid file format.');
                    break;
                case 'autocomplete':
                    if (!in_array($value, ['on', 'off'])) $this->addError($field, $customMessage ?? 'Invalid autocomplete value.');
                    break;
                default:
                    // Optionally handle unknown rules or log them
                    break;
            }
        }
    }

    /**
     * Registers a form field and its validation rules, and updates the form state.
     *
     * @param string $fieldName The name of the form field.
     * @param array $rules Validation rules for the field.
     * @return string HTML attributes for the field.
     */
    public function register($fieldName, $rules = []): string
    {
        $value = Validator::validateString($this->data[$fieldName] ?? '');

        $isTypeButton = array_key_exists('button', $rules);
        $attributes = "";
        if ($isTypeButton) {
            $attributes = "id='fh-$fieldName' name='$fieldName' data-rules='" . json_encode($rules) . "'";
        } else {
            $attributes = "id='fh-$fieldName' name='$fieldName' value='$value' data-rules='" . json_encode($rules) . "'";
        }

        if (!array_intersect(array_keys($rules), ['text', 'email', 'password', 'number', 'date', 'color', 'range', 'tel', 'url', 'search', 'time', 'datetime-local', 'month', 'week', 'file', 'submit', 'checkbox', 'radio', 'hidden', 'button', 'reset'])) {
            $rules['text'] = ['value' => true];
        }

        foreach ($rules as $rule => $ruleValue) {
            $attributes .= $this->parseRule($rule, $ruleValue);
        }

        $inputField = $this->stateManager->getState(self::FORM_INPUT_REGISTER) ?? [];
        $inputField[$fieldName] = [
            'value' => $value,
            'attributes' => $attributes,
            'rules' => $rules,
        ];
        $this->stateManager->setState([self::FORM_INPUT_REGISTER => $inputField], true);

        return $attributes;
    }

    /**
     * Retrieves the registered form fields.
     * 
     * @return array An associative array of registered form fields.
     * 
     * @example
     * $form->getRegisteredFields();
     * This will return an array of registered form fields.
     */
    public function getRegisteredFields(): array
    {
        return $this->stateManager->getState(self::FORM_INPUT_REGISTER) ?? [];
    }

    private function parseRule($rule, $ruleValue)
    {
        $attribute = '';
        $ruleParam = $ruleValue;
        $ruleParam = is_array($ruleValue) ? $ruleValue['value'] : $ruleValue;

        switch ($rule) {
            case 'text':
            case 'search':
            case 'email':
            case 'password':
            case 'number':
            case 'date':
            case 'color':
            case 'range':
            case 'tel':
            case 'url':
            case 'time':
            case 'datetime-local':
            case 'month':
            case 'week':
            case 'file':
            case 'submit':
            case "checkbox":
            case "radio":
            case "hidden":
            case "button":
            case "reset":
                $attribute .= " type='$rule'";
                break;
            case 'required':
                $attribute .= " required";
                break;
            case 'min':
            case 'max':
                $attribute .= " $rule='$ruleParam'";
                break;
            case 'minLength':
            case 'maxLength':
                $attribute .= " $rule='$ruleParam'";
                break;
            case 'pattern':
                $attribute .= " pattern='$ruleParam'";
                break;
            case 'autocomplete':
                $attribute .= " autocomplete='$ruleParam'";
                break;
            case 'readonly':
                $attribute .= " readonly";
                break;
            case 'disabled':
                $attribute .= " disabled";
                break;
            case 'placeholder':
                $attribute .= " placeholder='$ruleParam'";
                break;
            case 'autofocus':
                $attribute .= " autofocus";
                break;
            case 'multiple':
                $attribute .= " multiple";
                break;
            case 'accept':
                $attribute .= " accept='$ruleParam'";
                break;
            case 'size':
                $attribute .= " size='$ruleParam'";
                break;
            case 'step':
                $attribute .= " step='$ruleParam'";
                break;
            case 'list':
                $attribute .= " list='$ruleParam'";
                break;
            default:
                // Optionally handle unknown rules or log them
                break;
        }
        return $attribute;
    }

    /**
     * Creates a watch element for a form field.
     * 
     * This function returns an HTML string for a watch element with a unique `id` attribute,
     * useful for monitoring changes in the value of a form field.
     * 
     * @param string $field The name of the field to create a watch element for.
     * 
     * @return string An HTML string representing the watch element. The element will have a unique `id` attribute prefixed with "fh-watch-" and suffixed by the field name, and it will include `data-watch-value` and `data-type` attributes.
     * 
     * @example
     * Example usage to create a watch element for a "username" field:
     * 
     * echo $form->watch('username');
     * Output: "<div id='fh-watch-username' data-watch-value='{value}' data-type='watch'></div>"
     */
    public function watch(string $field)
    {
        $field = Validator::validateString($field);
        $fieldData = $this->data[$field] ?? '';
        return "id='fh-watch-$field' data-watch-value='$fieldData' data-type='watch'";
    }
}

?>

<script>
    if (typeof FormHandler === 'undefined') {
        class FormHandler {
            constructor() {
                this.errors = [];
                this.dataRulesElements = document.querySelectorAll('[data-rules]');
                this.init();
            }

            init() {
                this.dataRulesElements.forEach(fieldElement => {
                    this.initializeFieldFromDOM(fieldElement);
                });
            }

            initializeFieldFromDOM(fieldElement) {
                if (!fieldElement) return;

                const fieldName = fieldElement.name;
                const rules = JSON.parse(fieldElement.getAttribute('data-rules') || '{}');

                const errors = this.validateField(fieldElement, fieldElement.value, rules);
                const errorContainer = document.getElementById(`fh-error-${fieldElement.name}`);
                if (errorContainer) {
                    if (errorContainer.dataset.errorMessage) {
                        errorContainer.textContent = errors.join(', ');
                    }
                }

                const immediateObserver = (e) => {
                    const target = e.target;
                    this.watch(target);

                    const errors = this.validateField(target, target.value, rules);
                    const errorContainer = document.getElementById(`fh-error-${target.name}`);
                    if (errorContainer) {
                        errorContainer.textContent = errors.join(', ');
                    }
                };

                fieldElement.addEventListener('input', immediateObserver);
            }

            updateElementDisplay(displayElement, field) {
                const tagName = field.tagName.toUpperCase();
                if (tagName === 'INPUT' || tagName === 'TEXTAREA') {
                    if (displayElement.tagName === 'INPUT' || displayElement.tagName === 'TEXTAREA') {
                        displayElement.value = field.value;
                    } else {
                        displayElement.dataset.watchValue = field.value;
                        displayElement.textContent = field.value;
                    }
                } else {
                    displayElement.textContent = field.textContent;
                }
            }

            watch(field) {
                if (!field) return;

                const watchElement = document.getElementById(`fh-watch-${field.name}`);
                if (watchElement) {
                    this.updateElementDisplay(watchElement, field);
                }
            }

            clearErrors() {
                const errorElements = document.querySelectorAll('[id^="fh-error-"]');
                errorElements.forEach(element => {
                    element.textContent = '';
                });

                this.errors = [];
            }

            getErrors(field) {
                if (field) {
                    return document.getElementById(`fh-error-${field}`).textContent;
                } else {
                    return this.errors;
                }
            }

            validateField(field, value, rules) {
                if (!rules) return [];
                this.errors = [];

                for (const [rule, options] of Object.entries(rules)) {
                    let ruleValue = options;
                    let customMessage = null;

                    if (typeof options === 'object') {
                        ruleValue = options.value;
                        customMessage = options.message || null;
                    }

                    switch (rule) {
                        case 'text':
                        case 'search':
                            if (typeof value !== 'string') {
                                this.errors.push(customMessage || 'Must be a string.');
                            }
                            break;
                        case 'email':
                            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                                this.errors.push(customMessage || 'Invalid email format.');
                            }
                            break;
                        case 'number':
                            if (isNaN(value)) {
                                this.errors.push(customMessage || 'Must be a number.');
                            }
                            break;
                        case 'date':
                            if (isNaN(Date.parse(value))) {
                                this.errors.push(customMessage || 'Invalid date format.');
                            }
                            break;
                        case 'range':
                            const [min, max] = ruleValue;
                            if (isNaN(value) || value < min || value > max) {
                                this.errors.push(customMessage || `Must be between ${min} and ${max}.`);
                            }
                            break;
                        case 'url':
                            try {
                                new URL(value);
                            } catch (e) {
                                this.errors.push(customMessage || 'Invalid URL format.');
                            }
                            break;
                        case 'required':
                            if (!value) {
                                this.errors.push(customMessage || 'This field is required.');
                            }
                            break;
                        case 'min':
                            if (Number(value) < ruleValue) {
                                this.errors.push(customMessage || `Must be at least ${ruleValue}.`);
                            }
                            break;
                        case 'max':
                            if (Number(value) > ruleValue) {
                                this.errors.push(customMessage || `Must be at most ${ruleValue}.`);
                            }
                            break;
                        case 'minLength':
                            if (value.length < ruleValue) {
                                this.errors.push(customMessage || `Must be at least ${ruleValue} characters.`);
                            }
                            break;
                        case 'maxLength':
                            if (value.length > ruleValue) {
                                this.errors.push(customMessage || `Must be at most ${ruleValue} characters.`);
                            }
                            break;
                        case 'pattern':
                            if (!new RegExp(ruleValue).test(value)) {
                                this.errors.push(customMessage || 'Invalid format.');
                            }
                            break;
                        case 'accept':
                            if (!ruleValue.split(',').includes(value)) {
                                this.errors.push(customMessage || 'Invalid file format.');
                            }
                            break;
                        default:
                            // Optionally handle unknown rules or log them
                            break;
                    }
                }

                return this.errors;
            }
        }

        let formHandler = FormHandler ? new FormHandler() : null;
        // Initialize FormHandler on initial page load
        document.addEventListener('DOMContentLoaded', function() {
            formHandler = new FormHandler();
        });
    }
</script>