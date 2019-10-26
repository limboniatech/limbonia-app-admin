<?php
namespace Limbonia;

/**
 * Limbonia Controller base class
 *
 * This defines all the basic parts of an Limbonia controller
 *
 * @author Lonnie Blansett <lonnie@limbonia.tech>
 * @package Limbonia
 */
class Controller
{
  use \Limbonia\Traits\DriverList;
  use \Limbonia\Traits\HasApp;

  /**
   * The admin group that this controller belongs to
   *
   * @var string
   */
  protected static $sGroup = 'Admin';

  /**
   * List of fields used by controller settings
   *
   * @var array
   */
  protected static $hSettingsFields = [];

  /**
   * List of valid HTTP methods
   *
   * @var array
   */
  protected static $hHttpMethods =
  [
    'head' => 'search',
    'get' => 'search',
    'post' => 'create',
    'put' => 'edit',
    'delete' => 'delete',
    'options' => ''
  ];

  /**
   * A list of the actual controller settings
   *
   * @var array
   */
  protected $hSettings = [];

  /**
   * Has this controller been initialized
   *
   * @var boolean
   */
  protected $bInit = false;

  /**
   * Have this controller's settings been changed since the last save?
   *
   * @var boolean
   */
  protected $bChangedSettings = false;

  /**
   * Lists of columns to ignore when filling view data
   *
   * @var array
   */
  protected $aIgnore =
  [
    'edit' => [],
    'create' => [],
    'search' => [],
    'view' => [],
    'boolean' => []
  ];

  /**
   * List of column names that are allowed to generate "edit" links
   *
   * @var array
   */
  protected $aEditColumn = [];

  /**
   * List of column names that should remain static
   *
   * @var array
   */
  protected $aStaticColumn = ['Name'];

  /**
   * The default method for this controller
   *
   * @var string
   */
  protected $sDefaultAction = 'list';

  /**
   * The current method being used by this controller
   *
   * @var string
   */
  protected $sCurrentAction = 'list';

  /**
   * List of components that this controller contains along with their descriptions
   *
   * @var array
   */
  protected static $hComponent =
  [
    'search' => 'This is the ability to search and display data.',
    'edit' => 'The ability to edit existing data.',
    'create' => 'The ability to create new data.',
    'delete' => 'The ability to delete existing data.'
  ];

  /**
   * List of controllers this controller depends on to function correctly
   *
   * @var array
   */
  protected static $aControllerDependencies = [];

  /**
   * List of menu items that this controller should display
   *
   * @var array
   */
  protected $hMenuModels =
  [
    'list' => 'List',
    'search' => 'Search',
    'create' => 'Create'
  ];

  /**
   * List of quick search models to display
   *
   * @var array
   */
  protected $hQuickSearch = [];

  /**
   * List of sub-menu options
   *
   * @var array
   */
  protected $hSubMenuModels =
  [
    'view' => 'View',
    'edit' => 'Edit'
  ];

  /**
   * List of actions that are allowed to run
   *
   * @var array
   */
  protected $aAllowedActions = ['search', 'create', 'editcolumn', 'edit', 'list', 'view'];

  /**
   * A list of components the current user is allowed to use
   *
   * @var array
   */
  protected $hAllow = [];

  /**
   * Has the "City / State / Zip" block been output yet?
   *
   * @var boolean
   */
  protected $bCityStateZipDone = false;

  /**
   * The API object for this class to use
   *
   * @var \Limbonia\Router
   */
  protected $oRouter = null;

  /**
   * Return the list of this controller's components
   *
   * @return array
   */
  public static function getComponents()
  {
    return static::$hComponent;
  }

  /**
   * Return this controller's admin group
   *
   * @return string
   */
  public static function getGroup()
  {
    return static::$sGroup;
  }

  /**
   * Controller Factory
   *
   * @param string $sType - The type of controller to create
   * @param \Limbonia\App $oApp
   * @return \Limbonia\Controller
   */
  public static function factory($sType, \Limbonia\App $oApp)
  {
    return self::driverFactory($sType, $oApp);
  }

  /**
   * Generate and return an HTML field, in the default style, using the specified data
   *
   * @param string $sContent
   * @param string $sLabel (optional)
   * @param string $sFieldId (optional)
   * @return string
   */
  public static function field($sContent, $sLabel = '', $sFieldId = '')
  {
    $sLabelClass = empty($sLabel) ? 'blankLabel' : 'label';
    $sId = empty($sFieldId) ? '' : " id=\"{$sFieldId}Field\"";
    return "<div class=\"field\"$sId><span class=\"$sLabelClass\">$sLabel</span><span class=\"data\">$sContent</span></div>";
  }

  /**
   * Generate and return an HTML field, in the default style, using the specified widget object to generate the content and field id
   *
   * @param \Limbonia\Widget $oWiget
   * @param string $sLabel (optional)
   * @return string
   */
  public static function widgetField(\Limbonia\Widget $oWiget, $sLabel = '')
  {
    return self::field($oWiget, $sLabel, $oWiget->getId());
  }

  /**
   * Instantiate a controller
   *
   * @param \Limbonia\App $oApp
   */
  protected function __construct(\Limbonia\App $oApp, \Limbonia\Router $oRouter = null)
  {
    $this->oApp = $oApp;
    $this->oRouter = is_null($this->oRouter) ? $this->oApp->getRouter() : $oRouter;
    $this->getType();

    if (count(static::$hSettingsFields) > 0)
    {
      $this->hMenuModels['settings'] = 'Settings';
      $this->aAllowedActions[] = 'settings';
      $this->hSettings = $this->oApp->getSettings($this->sType);

      if (empty($this->hSettings))
      {
        $this->hSettings = $this->defaultSettings();
        $this->bChangedSettings = true;
        $this->saveSettings();
      }
    }

    $this->init();
    $this->sCurrentAction = in_array($this->oRouter->action, $this->aAllowedActions) ? $this->oRouter->action : $this->sDefaultAction;
  }

  /**
   * Destructor
   */
  public function __destruct()
  {
    $this->saveSettings();
  }

  /**
   * Activate this controller and any required dependencies then return a list of types that were activated
   *
   * @param array $hActiveController - the active controller list
   * @return array
   * @throws Exception on failure
   */
  public function activate(array $hActiveController)
  {
    $aNewActiveController = [$this->getType()];

    if (!empty(static::$aControllerDependencies))
    {
      foreach (static::$aControllerDependencies as $sController)
      {
        if (!isset($hActiveController[$sController]))
        {
          $this->oApp->activateController($sController);
          $aNewActiveController = array_merge($aNewActiveController, [$sController]);
        }
      }
    }

    $this->setup();
    return $aNewActiveController;
  }

  /**
   * Do whatever setup is needed to make this controller work...
   *
   * @throws Exception on failure
   */
  public function setup()
  {
  }

  /**
   * Deactivate this controller then return a list of types that were deactivated
   *
   * @param array $hActiveController - the active controller list
   * @return array
   * @throws Exception on failure
   */
  public function deactivate(array $hActiveController)
  {
    return [$this->getType()];
  }

  /**
   * Initialize this controller's custom data, if there is any
   */
  protected function init()
  {
  }

  /**
   * Remove any ignored fields of the specified type from the specified data then return it
   *
   * @param string $sIgnoreType
   * @param array $hData
   * @return array
   */
  protected function removeIgnoredFields($sIgnoreType, $hData)
  {
    if (empty($this->aIgnore[$sIgnoreType]))
    {
      return $hData;
    }

    foreach ($this->aIgnore[$sIgnoreType] as $sField)
    {
      if (isset($hData[$sField]))
      {
        unset($hData[$sField]);
      }
    }

    return $hData;
  }

  /**
   * Perform the base "GET" code then return null on success
   *
   * @return null
   * @throws \Exception
   */
  protected function processApiHead()
  {
    throw new \Limbonia\Exception\Web("Action not implemented by " . $this->getType(), null, 404);
  }

  /**
   * Perform and return the default "GET" code
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiGet()
  {
    throw new \Limbonia\Exception\Web("Action not implemented by " . $this->getType(), null, 404);
  }

  /**
   * Run the default "PUT" code and return the updated data
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiPut()
  {
    throw new \Limbonia\Exception\Web("Action not implemented by " . $this->getType(), null, 404);
  }

  /**
   * Run the default "POST" code and return the created data
   *
   * @return array
   * @throws \Exception
   */
  protected function processApiPost()
  {
    throw new \Limbonia\Exception\Web("Action not implemented by " . $this->getType(), null, 404);
  }

  /**
   * Run the default "DELETE" code and return true
   *
   * @return boolean - True on success
   * @throws \Exception
   */
  protected function processApiDelete()
  {
    throw new \Limbonia\Exception\Web("Action not implemented by " . $this->getType(), null, 404);
  }

  /**
   * Is the current user valid?
   *
   * @return boolean
   */
  protected function validUser()
  {
    $oUser = $this->oApp->user();

    if ($oUser instanceof \Limbonia\Model\User)
    {
      return $oUser->id > 0;
    }

    return false;
  }

  /**
   * Process the current API call and return the appropriate data
   *
   * @return mixed
   * @throws \Exception
   */
  public function processApi()
  {
    http_response_code(200);

    if (!$this->validUser())
    {
      throw new \Limbonia\Exception\Web('Authentication required', null, 401);
    }

    if (!in_array($this->oRouter->method, array_keys(static::$hHttpMethods)))
    {
      throw new \Limbonia\Exception\Web("HTTP method ({$this->oRouter->method}) not allowed", null, 405);
    }

    if (!empty(static::$hHttpMethods[$this->oRouter->method]) && !$this->allow(static::$hHttpMethods[$this->oRouter->method]))
    {
      throw new \Limbonia\Exception\Web("Action not allowed to user", null, 405);
    }

    switch ($this->oRouter->method)
    {
      case 'head':
        return $this->processApiHead();

      case 'get':
        return $this->processApiGet();

      case 'put':
        return $this->processApiPut();

      case 'post':
        http_response_code(201);
        return $this->processApiPost();

      case 'delete':
        http_response_code(204);
        return $this->processApiDelete();

      case 'options':
        $sMethods = implode(',', array_keys(static::$hHttpMethods));
        header('Allow: ' . strtoupper($sMethods));
        return null;
    }

    throw new \Limbonia\Exception\Web("HTTP method ({$this->oRouter->method}) not recognized", null, 405);
  }

  /**
   * Is this controller currently performing a search?
   *
   * @return boolean
   */
  public function isSearch()
  {
    return in_array($this->oRouter->action, ['search', 'list']);
  }

  /**
   * Return the list of fields used by this controller's settings
   *
   * @return array
   */
  public function getSettingsFields()
  {
    return static::$hSettingsFields;
  }

  /**
   * Should the specified component type be allowed to be used by the current user of this controller?
   *
   * @param string $sComponent
   * @return boolean
   */
  public function allow($sComponent)
  {
    if (!isset($this->hAllow[$sComponent]))
    {
      $this->hAllow[$sComponent] = $this->oApp->user()->hasResource($this->sType, $this->getComponent($sComponent));
    }

    return $this->hAllow[$sComponent];
  }

  public function getHttpMethods()
  {
    return static::$hHttpMethods;
  }

  /**
   * Generate and return the URI for the specified parameters
   *
   * @param string ...$aParam (optional) - List of parameters to place in the URI
   * @return string
   */
  public function generateUri(string ...$aParam): string
  {
    array_unshift($aParam, $this->sType);
    return $this->oApp->generateUri(...$aParam);
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

  /**
   * Prepare the view for display based on the current action and current method
   */
  public function prepareView()
  {
    $sOriginalAction = $this->sCurrentAction;
    $this->oApp->viewData('controller', $this);
    $this->oApp->viewData('method', $this->sCurrentAction);
    $aMethods = [];
    $aMethods[] = 'prepareView' . ucfirst($this->sCurrentAction) . ucfirst($this->oRouter->subAction);
    $aMethods[] = 'prepareView' . ucfirst($this->sCurrentAction);
    $aMethods[] = 'prepareView' . ucfirst($this->oRouter->method) . ucfirst($this->sCurrentAction) . ucfirst($this->oRouter->subAction);
    $aMethods[] = 'prepareView' . ucfirst($this->oRouter->method) . ucfirst($this->sCurrentAction);
    $aMethods = array_unique($aMethods);

    foreach ($aMethods as $sMethod)
    {
      //run every view method can be found
      if (method_exists($this, $sMethod))
      {
        $this->$sMethod();
      }
    }

    //If the current action has been modified from the original action
    if ($sOriginalAction !== $this->sCurrentAction)
    {
      //then prepare the views for the new action too
      $this->prepareView();
    }
  }

  /**
   * Generate and return the path of the view to display
   *
   * @return boolean|string
   */
  public function getView()
  {
    if (!$this->allow($this->sCurrentAction))
    {
      return false;
    }

    $sControllerDir = strtolower($this->getType());
    $sActionView = $this->sCurrentAction == 'list' ? 'search' : strtolower("{$this->sCurrentAction}");
    $sMethod = $this->oRouter->method == 'post' || $this->sCurrentAction == 'list' ? 'process' : 'display';
    $aViews =
    [
      $sControllerDir . '/' . $sActionView,
      $sControllerDir . '/' . $sMethod . $sActionView,
      $sActionView,
      $sMethod . $sActionView,
    ];

    foreach ($aViews as $sViewName)
    {
      $sViewFile = $this->oApp->viewFile($sViewName);

      if (empty($sViewFile))
      {
        continue;
      }

      return preg_match("/\.php$/", $sViewFile) ? $sViewFile : preg_replace("#^.*/views/#", '', $sViewFile);
    }

    throw new \Exception("The action \"{$this->sCurrentAction}\" does *not* exist in {$this->sType}!!!");
  }

  /**
   * Return an array of data that is needed to display the controller's admin output
   *
   * @return array
   */
  public function getAdminOutput()
  {
    return
    [
      'controllerType' => $this->getType(),
      'action' => $this->getCurrentAction()
    ];
  }

  /**
   * Return the list of static columns, if there are any
   *
   * @return array
   */
  protected function getStaticColumn()
  {
    return is_array($this->aStaticColumn) ? $this->aStaticColumn : [];
  }

  /**
   * Return the default settings
   *
   * @return array
   */
  protected function defaultSettings()
  {
    return [];
  }

  /**
   * Save the current settings, if any to the database
   *
   * @return boolean - True on success or false on failure
   */
  protected function saveSettings()
  {
    if (!$this->bChangedSettings)
    {
      return true;
    }

    if ($this->oApp->saveSettings($this->sType, $this->hSettings))
    {
      $this->bChangedSettings = false;
      return true;
    }

    return false;
  }

  /**
   * Return the specified setting, if it exists
   *
   * @param string $sName
   * @return mixed
   */
  public function getSetting($sName=null)
  {
    if (count($this->hSettings) == 0)
    {
      return null;
    }

    if (empty($sName))
    {
      return $this->hSettings;
    }

    return $this->hSettings[strtolower($sName)] ?? null;
  }

  /**
   * Set the specified setting to the specified value
   *
   * @param string $sName
   * @param mixed $xValue
   * @return boolean
   */
  protected function setSetting($sName, $xValue)
  {
    $sLowerName = strtolower($sName);

    if (!isset(static::$hSettingsFields[$sLowerName]))
    {
      return false;
    }

    $this->bChangedSettings = true;
    $this->hSettings[$sLowerName] = $xValue;
    return true;
  }

  /**
   * Return an array of height and width for a popup based on the specified name, if there is one
   *
   * @param string $sName
   * @return array
   */
  public function getPopupSize($sName)
  {
    return isset($this->hPopupSize[$sName]) ? $this->hPopupSize[$sName] : $this->hPopupSize['default'];
  }

  /**
   * Return a valid component name from the specified menu item
   *
   * @param string $sMenuModel
   * @return string
   */
  protected function getComponent($sMenuModel)
  {
    if ($sMenuModel == 'list')
    {
      return 'search';
    }

    if ($sMenuModel == 'editcolumn')
    {
      return 'edit';
    }

    return $sMenuModel;
  }

  /**
   * Return this controller's list of menu items
   *
   * @return array
   */
  public function getMenuModels()
  {
    return $this->hMenuModels;
  }

  /**
   * Return this controller's list of quick search models
   *
   * @return array
   */
  public function getQuickSearch()
  {
    return $this->hQuickSearch;
  }

  /**
   * Return this controller's list of sub-menu items
   *
   * @param boolean $bOnlyUserAllowed (optional) - Should the returned array only contain models that the current user has access to?
   * @return array
   */
  public function getSubMenuModels($bOnlyUserAllowed = false)
  {
    if ($bOnlyUserAllowed)
    {
      $hSubMenuModels = [];

      foreach ($this->hSubMenuModels as $sMenuAction => $sMenuTitle)
      {
        if ($this->allow($sMenuAction))
        {
          $hSubMenuModels[$sMenuAction] = $sMenuTitle;
        }
      }

      return $hSubMenuModels;
    }

    return $this->hSubMenuModels;
  }

  /**
   * Generate and return the title for this controller
   *
   * @return string
   */
  public function getTitle()
  {
    return ucwords(trim(preg_replace("/(([a-z])[A-Z])/", "$1 $2", str_replace("_", " ", $this->sType))));
  }

  /**
   * Return the current action
   *
   * @return string
   */
  public function getCurrentAction()
  {
    return $this->sCurrentAction;
  }

  /**
   * Prepare the search term array
   *
   * @param array $hArray
   * @param string $sKey
   * @return boolean
   */
  protected function processSearchTerm(&$hArray, $sKey)
  {
    if (empty($hArray[$sKey]))
    {
      unset($hArray[$sKey]);
      return true;
    }
  }

  /**
   * Process a whole array of search terms
   *
   * @param array $hArray
   * @return array
   */
  protected function processSearchTerms($hArray)
  {
    if (is_array($hArray))
    {
      foreach (array_keys($hArray) as $sKey)
      {
        $this->processSearchTerm($hArray, $sKey);
      }
    }

    return $hArray;
  }

  /**
   * Generate and return the column headers for the "Search" process
   *
   * @param array $hArray
   * @param string $sKey
   */
  protected function processSearchColumnHeader(array &$hArray, $sKey)
  {
    $hArray[$sKey] = preg_replace("/^.*?\./", "", $hArray[$sKey]);
  }

  /**
   * Generate the search results table headers in the specified grid object
   *
   * @param \Limbonia\Widget\Table $oSortGrid
   * @param string $sColumn
   */
  public function processSearchGridHeader(\Limbonia\Widget\Table $oSortGrid, $sColumn)
  {
    //any columns that need to be static can be set in the aStaticColumn array...
    if (in_array($sColumn, $this->getStaticColumn()) || !$this->allow('Edit'))
    {
      $oSortGrid->addCell(\Limbonia\Widget\Table::generateSortHeader($sColumn), false);
    }
    else
    {
      $sDisplay = \Limbonia\Widget\Table::generateSortHeader($this->getColumnTitle($sColumn));

      if (in_array($sColumn, $this->aEditColumn))
      {
        $sDisplay .= "<span class=\"LimboniaSortGridEdit\" onClick=\"document.getElementById('Limbonia_SortGrid_Edit').value='$sColumn'; document.getElementById('EditColumn').submit();\">[Edit]</span>";
      }

      $oSortGrid->addCell($sDisplay);
    }
  }

  /**
   * Generate and return the HTML needed to control the row specified by the id
   *
   * @param string $sIDColumn
   * @param integer $iID
   * @return string
   */
  public function processSearchGridRowControl($sIDColumn, $iID)
  {
    $sURL = $this->generateUri((string)$iID);
    return "<input type=\"checkbox\" class=\"LimboniaSortGridCellCheckbox\" name=\"{$sIDColumn}[$iID]\" id=\"{$sIDColumn}[$iID]\" value=\"1\"> [<a class=\"model\" href=\"$sURL\">View</a>]";
  }

  /**
   * Return the controller criteria
   *
   * @return array
   */
  protected function processSearchGetCriteria()
  {
    //unless overridden by a descendant form data will allways take precendence over URL data
    return isset($this->oApp->post[$this->sType]) ? $this->oApp->post[$this->sType] : (isset($this->oApp->get[$this->sType]) ? $this->oApp->get[$this->sType] : []);
  }

  /**
   * Generate and return the HTML for the specified form field based on the specified information
   *
   * @param string $sName
   * @param string $sValue
   * @param array $hData
   * @return string
   */
  public function getFormField($sName, $sValue = null, $hData = [])
  {
    $sLabel = preg_replace("/([a-z])([A-Z])/", "$1 $2", $sName);

    if (is_null($sValue) && isset($hData['Default']) && !$this->isSearch())
    {
      $sValue = $hData['Default'];
    }

    if ($sName == 'State' || $sName == 'City' || $sName == 'Zip')
    {
      if ($this->bCityStateZipDone)
      {
        if ($sName == 'State' && !empty($sValue))
        {
          return "<script type=\"text/javascript\" language=\"javascript\">setState('$sValue');</script>\n";
        }

        if ($sName == 'City' && !empty($sValue))
        {
          return "<script type=\"text/javascript\" language=\"javascript\">setCity('$sValue');</script>\n";
        }

        if ($sName == 'Zip' && !empty($sValue))
        {
          return "<script type=\"text/javascript\" language=\"javascript\">setZip('$sValue');</script>\n";
        }

        return null;
      }

      $oStates = $this->oApp->widgetFactory('States', "$this->sType[State]");
      $sStatesID = $oStates->getId();

      $oCities = $this->oApp->widgetFactory('Select', "$this->sType[City]");
      $sCitiesID = $oCities->getId();

      $oZips = $this->oApp->widgetFactory('Select', "$this->sType[Zip]");
      $sZipID = $oZips->getId();

      $sGetCities = $oStates->addAjaxFunction('getCitiesByState', true);
      $sGetZips = $oStates->addAjaxFunction('getZipsByCity', true);

      $sStateScript = "var stateSelect = document.getElementById('$sStatesID');\n";
      $sStateScript .= "var stateName = '';\n";
      $sStateScript .= "var cityName = '';\n";
      $sStateScript .= "function setState(state)\n";
      $sStateScript .= "{\n";
      $sStateScript .= "  stateName = state;\n";
      $sStateScript .= "  stateSelect.value = state;\n";
      $sStateScript .= '  ' . $sGetCities . "(state, '$sCitiesID', cityName);\n";
      $sStateScript .= "}\n";

      if ($sName == 'State')
      {
        $sStateScript .= "setState('" . $sValue . "');\n";
      }

      $oStates->writeJavascript($sStateScript);

      $sCityScript = "var citySelect = document.getElementById('$sCitiesID');\n";
      $sCityScript .= "var zipNum = '';\n";
      $sCityScript .= "function setCity(city)\n";
      $sCityScript .= "{\n";
      $sCityScript .= "  cityName = city;\n";
      $sCityScript .= "  if (citySelect.options.length > 1)\n";
      $sCityScript .= "  {\n";
      $sCityScript .= "    for (i = 0; i < citySelect.options.length; i++)\n";
      $sCityScript .= "    {\n";
      $sCityScript .= "      if (citySelect.options[i].value == city)\n";
      $sCityScript .= "      {\n";
      $sCityScript .= "        citySelect.options[i].selected = true;\n";
      $sCityScript .= "        break;\n";
      $sCityScript .= "      }\n";
      $sCityScript .= "    }\n";
      $sCityScript .= "  }\n";
      $sCityScript .= "  else\n";
      $sCityScript .= "  {\n";
      $sCityScript .= '    ' . $sGetCities . "(stateName, '$sCitiesID', city);\n";
      $sCityScript .= "  }\n";
      $sCityScript .= "  citySelect.options[1] = new Option(city, city, true);\n";
      $sCityScript .= '  ' . $sGetZips . "(cityName, stateName, '$sZipID', zipNum);\n";
      $sCityScript .= "}\n";

      if ($sName == 'City')
      {
        $sCityScript .= "setCity('" . $sValue . "');\n";
      }

      $oCities->writeJavascript($sCityScript);

      $sZipScript = "var zipSelect = document.getElementById('$sZipID');\n";
      $sZipScript .= "function setZip(zip)\n";
      $sZipScript .= "{\n";
      $sZipScript .= "  zipNum = zip;\n";
      $sZipScript .= "  if (zipSelect.options.length > 1)\n";
      $sZipScript .= "  {\n";
      $sZipScript .= "    for (i = 0; i < zipSelect.options.length; i++)\n";
      $sZipScript .= "    {\n";
      $sZipScript .= "      if (zipSelect.options[i].value == zip)\n";
      $sZipScript .= "      {\n";
      $sZipScript .= "        zipSelect.options[i].selected = true;\n";
      $sZipScript .= "        break;\n";
      $sZipScript .= "      }\n";
      $sZipScript .= "    }\n";
      $sZipScript .= "  }\n";
      $sZipScript .= "  else\n";
      $sZipScript .= "  {\n";
      $sZipScript .= "  zipSelect.options[1] = new Option(zip, zip, true);\n";
      $sZipScript .= '    ' . $sGetZips . "(cityName, stateName, '$sZipID', zipNum);\n";
      $sZipScript .= "  }\n";
      $sZipScript .= "}\n";

      if ($sName == 'Zip')
      {
        $sZipScript .= "setZip('" . $sValue . "');\n";
      }

      $oZips->writeJavascript($sZipScript);

      $oStates->addEvent('change', $sGetCities."(this.options[this.selectedIndex].value, '$sCitiesID', cityName)");

      $sFormField = self::widgetField($oStates, 'State');

      $oCities->addOption('Select a city', '0');
      $oCities->addEvent('change', $sGetZips."(this.options[this.selectedIndex].value, stateSelect.options[stateSelect.selectedIndex].value, '$sZipID', zipNum)");

      $sFormField .= self::widgetField($oCities, 'City');

      $oZips->addOption('Select a zip', '0');

      $sFormField .= self::widgetField($oZips, 'Zip');

      $this->bCityStateZipDone = true;
      return $sFormField;
    }

    if ($sName == 'UserID')
    {
      $oUsers = Model::search('User', ['Visible' => true, 'Active' => true]);
      $oSelect = $this->oApp->widgetFactory('Select', "$this->sType[UserID]");
      $sEmptyModelLabel = $this->isSearch() ? 'None' : 'Select a user';
      $oSelect->addOption($sEmptyModelLabel, '');

      foreach ($oUsers as $hUser)
      {
        $oSelect->addOption($hUser['Name'], $hUser['ID']);
      }

      $oSelect->setSelected($sValue);
      return self::widgetField($oSelect, 'User');
    }

    if ($sName == 'KeyID')
    {
      $oSelect = $this->oApp->widgetFactory('Select', "$this->sType[KeyID]");
      $sEmptyModelLabel = $this->isSearch() ? 'None' : 'Select a resource name';
      $oSelect->addOption($sEmptyModelLabel, '');
      $oKeys = Model::search('ResourceKey', null, 'Name');

      foreach ($oKeys as $hKey)
      {
        if ($sValue == $hKey['KeyID'])
        {
          $oSelect->setSelected($hKey['KeyID']);
        }

        $oSelect->addOption($hKey['Name'], $hKey['KeyID']);
      }

      return self::widgetField($oSelect, 'Required Key');
    }

    if (preg_match('/(.+?)id$/i', $sName, $aMatch))
    {
      try
      {
        $oTest = Model::factory($aMatch[1]);

        if (isset($oTest->name))
        {
          $oList = Model::search($aMatch[1]);

          $oSelect = $this->oApp->widgetFactory('Select', "$this->sType[$sName]");
          $sEmptyModelLabel = $this->isSearch() ? 'None' : "Select {$aMatch[1]}";
          $oSelect->addOption($sEmptyModelLabel, '');

          foreach ($oList as $oTempModel)
          {
            $oSelect->addOption($oTempModel->name, $oTempModel->id);
          }

          if (!empty($sValue))
          {
            $oSelect->setSelected($sValue);
          }

          return self::widgetField($oSelect, $aMatch[1]);
        }
      }
      catch (\Exception $e)
      {
      }
    }

    if ($sName == 'FileName')
    {
      $oFile = $this->oApp->widgetFactory('Input', "$this->sType[FileName]");
      $oFile->setParam('type', 'file');
      return self::widgetField($oSelect, 'File Name');
    }

    $sType = strtolower(preg_replace("/( |\().*/", "", $hData['Type']));

    switch ($sType)
    {
      case 'hidden':
        $oHidden = \Limbonia\Tag::factory('input');
        $oHidden->setParam('type', 'hidden');
        $oHidden->setParam('name', "$this->sType[$sName]");
        $oHidden->setParam('id', $this->sType . $sName);
        $oHidden->setParam('value', $sValue);
        return $oHidden->__toString();

      case 'enum':
        $sElements = preg_replace("/enum\((.*?)\)/", "$1", $hData['Type']);
        $sElements = str_replace("'", '"', $sElements);
        $sElements = str_replace('""', "'", $sElements);
        $sElements = str_replace('"', '', $sElements);
        $aElements = explode(",", $sElements);
        $aTitle = array_map('ucwords', $aElements);
        $hElements = array_combine($aElements, $aTitle);
        return $this->getFormField($sName, $sValue, ['Type' => 'hash', 'Extra' => $hElements]);

      case 'hash':
        $oSelect = $this->oApp->widgetFactory('select', "$this->sType[$sName]");

        if ($this->isSearch() || (isset($hData['Multiple']) && true == $hData['Multiple']))
        {
          $oSelect->isMultiple(true);
        }
        else
        {
          $oSelect->addOption("Select $sLabel", '');
        }

        if (is_array($hData['Extra']))
        {
          $oSelect->addArray($hData['Extra']);
        }

        if (!empty($sValue))
        {
          $oSelect->setSelected($sValue);
        }

        return self::widgetField($oSelect, $sLabel);

      case 'text':
      case 'mediumtext':
      case 'longtext':
      case 'textarea':
        $oText = $this->oApp->widgetFactory('Editor', "$this->sType[$sName]");
        $oText->setToolBar('Basic');
        $oText->setText($sValue);
        return self::widgetField($oText, $sLabel);

      case 'radio':
        $sFormField = '';

        foreach ($hData as $sKey => $sButtonValue)
        {
          if (preg_match("/^Value/", $sKey))
          {
            $sChecked = ($sButtonValue == $sValue ? ' checked' : null);
            $sFormField .= "$sButtonValue:  <input type=\"radio\" name=\"$this->sType[$sName]\" id=\"$this->sType$sName\"value=\"$sButtonValue\"$sChecked><br />";
          }
        }

        return self::field($sFormField, $sLabel);

      case 'float':
      case 'int':
      case 'varchar':
      case 'char':
        return self::field("<input type=\"text\" name=\"$this->sType[$sName]\" id=\"$this->sType$sName\" value=\"" . htmlentities($sValue) . "\">", $sLabel, "$this->sType$sName");

      case 'timestamp':
      case 'date':
      case 'searchdate':
        $sSearchDate = $sType == 'searchdate' ? "<select name=\"$this->sType[{$sName}Operator]\"><option> < </option><option selected> = </option><option> > </option></select>\n" : '';
        $oDate = $this->oApp->widgetFactory('Calendar', "$this->sType[$sName]");
        $oDate->button('Change');

        if (!empty($sValue))
        {
          $oDate->setStartDate($sValue);
        }

        return self::field("$sSearchDate$oDate", $sLabel, $oDate->getId());

      case 'password':
        return self::field("<input type=\"password\" name=\"$this->sType[$sName]\" id=\"$this->sType$sName\" value=\"$sValue\">", $sLabel, "$this->sType{$sName}") .
        self::field("<input type=\"password\" name=\"$this->sType[{$sName}2]\" id=\"$this->sType{$sName}2\" value=\"$sValue\">", $sLabel . '<br>(double check)', "$this->sType{$sName}2");

      case 'swing':
        return null;

      case 'boolean':
      case 'tinyint':
        $sChecked = $sValue ? ' checked="checked"' : '';
        return self::field("<input type=\"checkbox\" name=\"$this->sType[$sName]\" id=\"$this->sType$sName\" value=\"1\"$sChecked>", $sLabel, "$this->sType$sName");

      default:
        return self::field("$sName :: $sType", 'Not valid');
    }

    return '';
  }

  /**
   * Generate and return the HTML for the specified form field based on the specified information
   *
   * @param string $sName
   * @param string $sValue
   * @param array $hData
   * @return string
   */
  public function getField($sName, $sValue = null, $hData = [])
  {
    $sLabel = $this->getColumnTitle($sName);

    if (preg_match('/(.+?)id$/i', $sName, $aMatch) && Model::driver($aMatch[1]))
    {
      try
      {
        $oModel = $this->oApp->modelFromId($aMatch[1], $sValue);
        return self::field($oModel->name, $sLabel, $this->sType . $sName);
      }
      catch (\Exception $e)
      {
        return self::field('None<!-- ' . $e->getMessage() . ' -->', $sLabel, $this->sType . $sName);
      }
    }

    $sType = strtolower(preg_replace("/( |\().*/", "", $hData['Type']));

    switch ($sType)
    {
      case 'hidden':
        $oHidden = \Limbonia\Tag::factory('input');
        $oHidden->setParam('type', 'hidden');
        $oHidden->setParam('name', "$this->sType[$sName]");
        $oHidden->setParam('id', $this->sType . $sName);
        $oHidden->setParam('value', $sValue);
        return $oHidden->__toString();

      case 'password':
      case 'swing':
        return '';

      case 'tinyint':
        $sValue = (boolean)(integer)$sValue ? 'Yes' : 'No';
    }

    return self::field($sValue, $sLabel, $this->sType . $sName);
  }

  /**
   * Generate and return the column title from the specified column name
   *
   * @param string $sColumn
   * @return string
   */
  public function getColumnTitle($sColumn)
  {
    //if this is an ID column and there is a driver for it
    if (preg_match("/^(.+?)ID$/", $sColumn, $aMatch) && Model::driver($aMatch[1]))
    {
      //then use the match otherwise use the original column
      $sColumn = $aMatch[1];
    }

    return preg_replace("/([a-z])([A-Z])/", "$1 $2", $sColumn);
  }

  /**
   * Generate and return the value of the specified column
   *
   * @param \Limbonia\Model $oModel
   * @param string $sColumn
   * @return mixed
   */
  public function getColumnValue(Model $oModel, $sColumn)
  {
    if (preg_match("/(^.*?)id$/i", $sColumn, $aMatch))
    {
      try
      {
        $sType = $aMatch[1];

        if ($oModel->__isset($sType))
        {
          $oColumnModel = $oModel->__get($sType);

          if ($oColumnModel instanceof Model && $oColumnModel->__isset('name'))
          {
            return $oColumnModel->id == 0 ? 'None' : $oColumnModel->name;
          }
        }
      }
      catch (\Exception $e) { }
    }

    return $oModel->__get($sColumn);
  }

  /**
   * Generate and return the HTML for all the specified form fields
   *
   * @param array $hFields - List of the fields to generate HTML for
   * @param array $hValues (optional) - List of field data, if there is any
   * @return string
   */
  public function getFormFields($hFields, $hValues = [])
  {
    if (!is_array($hFields))
    {
      return '';
    }

    $sFormFields = '';

    foreach ($hFields as $sName => $hData)
    {
      $sValue = $hValues[$sName] ?? null;
      $sFormFields .= $this->getFormField($sName, $sValue, $hData);
    }

    return $sFormFields;
  }

  /**
   * Generate and return the HTML for all the specified form fields
   *
   * @param array $hFields - List of the fields to generate HTML for
   * @param array $hValues (optional) - List of field data, if there is any
   * @return string
   */
  public function getFields($hFields, $hValues = [])
  {
    if (!is_array($hFields))
    {
      return '';
    }

    $sFields = '';

    foreach ($hFields as $sName => $hData)
    {
      $sValue = $hValues[$sName] ?? null;
      $sFields .= $this->getField($sName, $sValue, $hData);
    }

    return $sFields;
  }

  /**
   * Echo the form generated by the specified data
   *
   * @param string $sType
   * @param array $hFields
   * @param array $hValues
   */
  public function getForm($sType, $hFields, $hValues = [])
  {
    if (strtolower($sType) == 'edit')
    {
      $sButton = "<input type=\"submit\" name=\"Update\" value=\"Update\">&nbsp;&nbsp;&nbsp;&nbsp;<input type=\"button\" name=\"No\" value=\"No\" onclick=\"parent.location='" . $this->generateUri($this->oModel->id) . "'\">";
    }
    else
    {
      $sButtonValue = ucwords($sType);
      $sButton = "<button type=\"submit\">$sButtonValue</button>";
    }

    $sType = preg_replace('/ /', '', $sType);
    return "<form name=\"$sType\" action=\"" . $this->generateUri($sType) . "\" method=\"post\">
" . $this->getFormFields($hFields, $hValues) . "
<div class=\"field\"><span class=\"blankLabel\"></span><span>$sButton</span></div>
</form>\n";
  }

  /**
   * Return the HTML needed to display the specified edit dialog box
   *
   * @param type $sText
   * @param type $sButtonName
   * @return string
   */
  protected function editDialog($sText, $sButtonName)
  {
    $sVerb = isset($_SESSION['EditData']['Delete']) ? 'Delete' : 'Edit Column';
    $sContent = "<form id=\"EditColumn\" name=\"EditColumn\" action=\"" . $this->generateUri('editcolumn') . "\" method=\"post\">\n";
    $sContent .= $sText;
    $sContent .= "<button type=\"submit\" name=\"$sButtonName\">Yes</button>&nbsp;&nbsp;&nbsp;&nbsp;<button id=\"No\" name=\"No\">No</button>";
    $sContent .= "</form>\n";
    return \Limbonia\App\Admin::getMenu($sContent, $this->getTitle() . " :: $sVerb");
  }
}