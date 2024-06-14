<?php

namespace Lib;

/**
 * Manages the application state.
 */
class StateManager
{
    private const APP_STATE = 'app_state_F989A';
    private $state;
    private $listeners;

    /**
     * Constructs a new StateManager instance.
     *
     * @param array $initialState The initial state of the application.
     * @param string|array|null $keepState The keys of the state to retain if resetting.
     */
    public function __construct($initialState = [], $keepState = null)
    {
        global $isWire;

        $this->state = $initialState;
        $this->listeners = [];
        $this->loadState();

        if (!$isWire) $this->resetState($keepState);
    }

    /**
     * Retrieves the current state of the application.
     *
     * @param string|null $key The key of the state value to retrieve. If null, returns the entire state.
     * @return mixed|null The state value corresponding to the given key, or null if the key is not found.
     */
    public function getState($key = null)
    {
        if ($key === null) {
            return new \ArrayObject($this->state, \ArrayObject::ARRAY_AS_PROPS);
        }

        if (array_key_exists($key, $this->state)) {
            $value = $this->state[$key];
            return is_array($value) ? new \ArrayObject($value, \ArrayObject::ARRAY_AS_PROPS) : $value;
        }

        return null;
    }

    /**
     * Updates the application state with the given update.
     *
     * @param string|array $key The key of the state value to update, or an array of key-value pairs to update multiple values.
     */
    public function setState($key, $value = null)
    {
        $update = is_array($key) ? $key : [$key => $value];
        $this->state = array_merge($this->state, $update);
        foreach ($this->listeners as $listener) {
            call_user_func($listener, $this->state);
        }

        $this->saveState();
    }

    /**
     * Subscribes a listener to state changes.
     *
     * @param callable $listener The listener function to subscribe.
     * @return callable A function that can be called to unsubscribe the listener.
     */
    public function subscribe($listener)
    {
        $this->listeners[] = $listener;
        call_user_func($listener, $this->state);
        return function () use ($listener) {
            $this->listeners = array_filter($this->listeners, function ($l) use ($listener) {
                return $l !== $listener;
            });
        };
    }

    /**
     * Saves the current state to storage.
     */
    private function saveState()
    {
        $_SESSION[self::APP_STATE] = json_encode($this->state);
    }

    /**
     * Loads the state from storage, if available.
     */
    private function loadState()
    {
        if (isset($_SESSION[self::APP_STATE])) {
            $this->state = json_decode($_SESSION[self::APP_STATE], true);
            foreach ($this->listeners as $listener) {
                call_user_func($listener, $this->state);
            }
        }
    }

    /**
     * Resets the application state partially or completely.
     *
     * @param string|array|null $keepKeys The key(s) of the state to retain. If null, resets the entire state.
     *                                    Can be a string for a single key or an array of strings for multiple keys.
     */
    public function resetState($keepKeys = null)
    {
        if ($keepKeys === null) {
            // Reset the entire state
            $this->state = [];
        } elseif (is_array($keepKeys)) {
            // Retain only the parts of the state identified by the keys in the array
            $retainedState = [];
            foreach ($keepKeys as $key) {
                if (array_key_exists($key, $this->state)) {
                    $retainedState[$key] = $this->state[$key];
                }
            }
            $this->state = $retainedState;
        } else {
            // Retain only the part of the state identified by a single key
            if (array_key_exists($keepKeys, $this->state)) {
                $this->state = [$keepKeys => $this->state[$keepKeys]];
            } else {
                $this->state = [];
            }
        }

        // Notify all listeners about the state change
        foreach ($this->listeners as $listener) {
            call_user_func($listener, $this->state);
        }

        // Save the updated state to the session or clear it
        if (empty($this->state)) {
            unset($_SESSION[self::APP_STATE]);
        } else {
            $_SESSION[self::APP_STATE] = json_encode($this->state);
        }
    }
}
