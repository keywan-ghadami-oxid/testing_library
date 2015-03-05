<?php

/**
 * Helper class for oxUBase.
 */
class oxUBaseHelper extends oxUBase
{

    /** @var bool Was init function called. */
    public $initWasCalled = false;

    /** @var bool Was parent class called. */
    public $setParentWasCalled = false;

    /** @var bool Whether action was set. */
    public $setThisActionWasCalled = false;

    /**
     * Calls self::_processRequest(), initializes components which needs to
     * be loaded, sets current list type, calls parent::init()
     */
    public function init()
    {
        $this->initWasCalled = true;
    }

    /**
     * Cleans classes static variables.
     */
    public static function cleanup()
    {
        self::resetComponentNames();
    }

    /**
     * Sets class parent.
     *
     * @param null $oParam
     */
    public function setParent($oParam = null)
    {
        $this->setParentWasCalled = true;
    }

    /**
     * Sets action.
     *
     * @param null $oParam
     */
    public function setThisAction($oParam = null)
    {
        $this->setThisActionWasCalled = true;
    }

    /**
     * Resets collected component names.
     */
    public static function resetComponentNames()
    {
        parent::$_aCollectedComponentNames = null;
    }
}
