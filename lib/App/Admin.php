<?php
namespace Limbonia\App;

/**
 * Limbonia Admin App Class
 *
 * This extends the basic app with the ability to display and react to
 * site administration pages
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Admin extends \Limbonia\App\Web
{
  /**
   * Render this app instance for output and return that data
   *
   * @return string
   */
  protected function render()
  {
    if ($this->oUser->id == 0 && !$this->oUser->isAdmin())
    {
      if (isset($this->oRouter->ajax))
      {
         return ['content' => $this->viewRender('login')];
      }

      return $this->viewRender('login');
    }

    $sControllerDriver = \Limbonia\Controller::driver((string)$this->oRouter->controller);

    if (empty($sControllerDriver))
    {
      return parent::render();
    }

    $oCurrentController = $this->controllerFactory($sControllerDriver);
    $oCurrentController->prepareView();
    $sControllerView = $oCurrentController->getView();

    if (isset($this->oRouter->ajax))
    {
       return array_merge(['content' => $this->viewRender($sControllerView)], $oCurrentController->getAdminOutput());
    }

    $this->viewData('content', $this->viewRender($sControllerView));
    return $this->viewRender('index');
  }

  /**
   * Generate and return the current user
   *
   * @return \Limbonia\Interfaces\Model\User
   * @throws \Exception
   */
  protected function generateUser()
  {
    $oUser = parent::generateUser();
    $hControllerList = $this->activeControllers();
    $_SESSION['ResourceList'] = [];
    $_SESSION['ControllerGroups'] = [];
    $aBlackList = $this->controllerBlackList ?? [];

    foreach ($hControllerList as $sController)
    {
      $sDriver = \Limbonia\Controller::driver($sController);

      if (empty($sDriver) || in_array($sDriver, $aBlackList) || !$oUser->hasResource($sDriver))
      {
        continue;
      }

      $sTypeClass = '\\Limbonia\\Controller\\' . $sDriver;
      $hComponent = $sTypeClass::getComponents();
      ksort($hComponent);
      reset($hComponent);
      $_SESSION['ResourceList'][$sDriver] = $hComponent;
      $_SESSION['ControllerGroups'][$sTypeClass::getGroup()][strtolower($sDriver)] = $sDriver;
    }

    ksort($_SESSION['ResourceList']);
    reset($_SESSION['ResourceList']);

    ksort($_SESSION['ControllerGroups']);

    foreach (array_keys($_SESSION['ControllerGroups']) as $sKey)
    {
      ksort($_SESSION['ControllerGroups'][$sKey]);
    }

    return $oUser;
  }

  /**
   * Process the basic logout
   *
   * @param string $sMessage - the message to display, if there is one
   */
  public function logOut($sMessage = '')
  {
    parent::logOut();
    $this->viewData('failure', "<h1>$sMessage</h1>\n");
  }

  /**
   * Generate and return the admin menu
   *
   * @param string $sContent
   * @param string $sHeader (optional)
   * @param string $sFooter (optional)
   * @return string
   */
  public static function getMenu($sContent, $sHeader = '', $sFooter = '')
  {
    $sMenu = '';

    if (!empty($sContent))
    {
      $sMenu .= "<section class=\"controllerMenu\">\n";

      if (!empty($sHeader))
      {
        $sMenu .= "<header>$sHeader</header>\n";
      }

      $sMenu .= "<main class=\"content\">$sContent</main>\n";

      if (!empty($sFooter))
      {
        $sMenu .= "<footer>$sFooter</footer>\n";
      }

      $sMenu .= "</section>\n";
    }

    return $sMenu;
  }
}