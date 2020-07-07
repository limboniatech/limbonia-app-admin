<?php
namespace Limbonia\Controller;

/**
 * Limbonia Controller base class
 *
 * This defines all the basic parts of an Limbonia controller
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Admin extends \Limbonia\Controller implements \Limbonia\Interfaces\Controller\Admin
{
  use \Limbonia\Traits\Controller\Admin;
  use \Limbonia\Traits\DriverList;

  /**
   * Controller Factory
   *
   * @param string $sType - The type of controller to create
   * @param \Limbonia\App\Admin $oApp
   * @return \Limbonia\Traits\Controller\Admin
   */
  public static function factory($sType, \Limbonia\App\Admin $oApp)
  {
    return static::driverFactory($sType, $oApp);
  }

  /**
   * Instantiate an Admin controller
   *
   * @param \Limbonia\App\Admin $oApp
   * @param \Limbonia\Router $oRouter (optional)
   */
  protected function __construct(\Limbonia\App\Admin $oApp, \Limbonia\Router $oRouter = null)
  {
    parent::__construct($oApp, $oRouter);
    $this->adminConstruct();
  }
}