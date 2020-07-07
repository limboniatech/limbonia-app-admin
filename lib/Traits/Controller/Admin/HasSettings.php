<?php
namespace Limbonia\Traits\Controller\Admin;

trait HasSettings
{
  /**
   * Instantiate a controller
   */
  protected function adminSettingsConstruct()
  {
    $this->hMenuItems['settings'] = 'Settings';
    $this->aAllowedActions[] = 'settings';
  }

  /**
   * Process the posted settings for this controller ad save them
   *
   * @throws Exception
   */
  protected function prepareViewPostSettings()
  {
    if (!isset($this->oApp->post[$this->sType]))
    {
      throw new Exception('Nothing to save!');
    }

    foreach ($this->oApp->post[$this->sType] as $sKey => $sData)
    {
      $this->setSetting($sKey, $sData);
    }

    $this->saveSettings();
  }
}